<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Shelf;

use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRepository;
use App\HomeLibrary\Domain\User\UserRoles;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ShelfIsolationTest extends WebTestCase
{
    private Connection $connection;

    private EntityManagerInterface $entityManager;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    private UuidInterface $shelfLibraryA;

    private UuidInterface $shelfLibraryB;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::createClient();

        $container = self::getContainer();

        $this->connection = $container->get(Connection::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->csrfTokenManager = $container->get(CsrfTokenManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->truncateTables();
        $this->seedData();

        self::ensureKernelShutdown();
    }

    #[Test]
    public function itListsOnlyShelvesFromOwnLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'shelf-user-a@example.com', 'SecurePass123!');

        $client->request(
            'GET',
            '/api/shelves',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);
        self::assertSame('Shelf A', $payload['data'][0]['name']);
    }

    #[Test]
    public function itDoesNotListShelvesFromAnotherLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'shelf-user-b@example.com', 'SecurePass123!');

        $client->request(
            'GET',
            '/api/shelves',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);
        self::assertSame('Shelf B', $payload['data'][0]['name']);
    }

    #[Test]
    public function itReturns404WhenDeletingShelfFromAnotherLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'shelf-user-a@example.com', 'SecurePass123!');

        $client->request(
            'DELETE',
            '/api/shelves/' . $this->shelfLibraryB->toString(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function itDeletesShelfFromOwnLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'shelf-user-a@example.com', 'SecurePass123!');

        $client->request(
            'DELETE',
            '/api/shelves/' . $this->shelfLibraryA->toString(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    #[Test]
    public function itAllowsSameShelfNameInDifferentLibraries(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'shelf-user-a@example.com', 'SecurePass123!');

        $client->request(
            'POST',
            '/api/shelves',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
            content: json_encode(['name' => 'Shelf B'], \JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    private function truncateTables(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE book_genre RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE books RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE shelves RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE libraries RESTART IDENTITY CASCADE');
    }

    private function seedData(): void
    {
        $libraryA = new Library(
            Uuid::uuid7(),
            new LibraryName('Library A'),
            LibraryPasswordHash::fromString('$2y$13$testhashedpassword000000000000000000000000000000000'),
        );

        $libraryB = new Library(
            Uuid::uuid7(),
            new LibraryName('Library B'),
            LibraryPasswordHash::fromString('$2y$13$testhashedpassword000000000000000000000000000000000'),
        );

        $this->entityManager->persist($libraryA);
        $this->entityManager->persist($libraryB);
        $this->entityManager->flush();

        $this->createUser('shelf-user-a@example.com', 'SecurePass123!', $libraryA);
        $this->createUser('shelf-user-b@example.com', 'SecurePass123!', $libraryB);

        $this->shelfLibraryA = Uuid::uuid7();
        $shelfA = new Shelf($this->shelfLibraryA, new ShelfName('Shelf A'), ShelfFlag::userDefined(), $libraryA);

        $this->shelfLibraryB = Uuid::uuid7();
        $shelfB = new Shelf($this->shelfLibraryB, new ShelfName('Shelf B'), ShelfFlag::userDefined(), $libraryB);

        $this->entityManager->persist($shelfA);
        $this->entityManager->persist($shelfB);
        $this->entityManager->flush();
    }

    private function createUser(string $email, string $plainPassword, Library $library): User
    {
        $user = new User(
            Uuid::uuid7(),
            UserEmail::fromString($email),
            UserPasswordHash::fromString('pre-hash'),
            UserRoles::fromArray(['ROLE_USER']),
            $library,
        );

        $hash = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->updatePasswordHash(UserPasswordHash::fromString($hash));

        $this->userRepository->save($user);

        return $user;
    }

    private function authenticate(KernelBrowser $client, string $email, string $password): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
            content: json_encode([
                'email' => $email,
                'password' => $password,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
