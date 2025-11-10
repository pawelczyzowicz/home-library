<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Shelf;

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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ListShelvesApiTest extends WebTestCase
{
    private Connection $connection;

    private EntityManagerInterface $entityManager;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

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

        self::ensureKernelShutdown();
    }

    #[Test]
    public function itListsOnlySystemShelvesWhenIncludeSystemIsTrue(): void
    {
        $email = 'list-shelves@example.com';
        $password = 'SecurePass123!';

        $this->createUser($email, $password);

        $systemShelfA = new Shelf(Uuid::uuid7(), new ShelfName('System Shelf A'), ShelfFlag::system());
        $systemShelfB = new Shelf(Uuid::uuid7(), new ShelfName('System Shelf B'), ShelfFlag::system());
        $userShelf = new Shelf(Uuid::uuid7(), new ShelfName('User Shelf'), ShelfFlag::userDefined());

        $this->entityManager->persist($systemShelfA);
        $this->entityManager->persist($systemShelfB);
        $this->entityManager->persist($userShelf);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $client = static::createClient();
        $this->authenticate($client, $email, $password);

        $client->request(
            'GET',
            '/api/shelves?includeSystem=true',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertArrayHasKey('total', $payload['meta']);

        $entries = $payload['data'];

        self::assertCount(2, $entries);
        self::assertSame(2, $payload['meta']['total']);

        $returnedIds = array_map(static fn (array $item): string => (string) $item['id'], $entries);

        self::assertContains((string) $systemShelfA->id(), $returnedIds);
        self::assertContains((string) $systemShelfB->id(), $returnedIds);
        self::assertNotContains((string) $userShelf->id(), $returnedIds);

        foreach ($entries as $entry) {
            self::assertTrue($entry['isSystem']);
        }
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
    }

    private function createUser(string $email, string $plainPassword): void
    {
        $user = new User(
            Uuid::uuid7(),
            UserEmail::fromString($email),
            UserPasswordHash::fromString('pre-hash'),
            UserRoles::fromArray(['ROLE_USER']),
        );

        $hash = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->updatePasswordHash(UserPasswordHash::fromString($hash));

        $this->userRepository->save($user);
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
