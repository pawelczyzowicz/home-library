<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Book;

use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreName;
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

final class BookIsolationTest extends WebTestCase
{
    private Connection $connection;

    private EntityManagerInterface $entityManager;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    private UuidInterface $bookLibraryA;

    private UuidInterface $bookLibraryB;

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
    public function itListsOnlyBooksFromOwnLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'user-a@example.com', 'SecurePass123!');

        $client->request(
            'GET',
            '/api/books',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);
        self::assertSame('Book A', $payload['data'][0]['title']);
    }

    #[Test]
    public function itDoesNotListBooksFromAnotherLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'user-b@example.com', 'SecurePass123!');

        $client->request(
            'GET',
            '/api/books',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('data', $payload);
        self::assertCount(1, $payload['data']);
        self::assertSame('Book B', $payload['data'][0]['title']);
    }

    #[Test]
    public function itReturns404WhenDeletingBookFromAnotherLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'user-a@example.com', 'SecurePass123!');

        $client->request(
            'DELETE',
            '/api/books/' . $this->bookLibraryB->toString(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function itDeletesBookFromOwnLibrary(): void
    {
        $client = static::createClient();
        $this->authenticate($client, 'user-a@example.com', 'SecurePass123!');

        $client->request(
            'DELETE',
            '/api/books/' . $this->bookLibraryA->toString(),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ],
        );

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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
        $this->connection->executeStatement('TRUNCATE TABLE genres RESTART IDENTITY CASCADE');
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

        $this->createUser('user-a@example.com', 'SecurePass123!', $libraryA);
        $this->createUser('user-b@example.com', 'SecurePass123!', $libraryB);

        $genre = new Genre(1, new GenreName('Fiction'));
        $this->entityManager->persist($genre);

        $shelfA = new Shelf(Uuid::uuid7(), new ShelfName('Shelf A'), ShelfFlag::userDefined(), $libraryA);
        $shelfB = new Shelf(Uuid::uuid7(), new ShelfName('Shelf B'), ShelfFlag::userDefined(), $libraryB);

        $this->entityManager->persist($shelfA);
        $this->entityManager->persist($shelfB);
        $this->entityManager->flush();

        $this->bookLibraryA = Uuid::uuid7();
        $bookA = new Book(
            $this->bookLibraryA,
            new BookTitle('Book A'),
            new BookAuthor('Author A'),
            new BookIsbn(null),
            new BookPageCount(100),
            BookSource::MANUAL,
            null,
            $shelfA,
            $libraryA,
            [$genre],
        );

        $this->bookLibraryB = Uuid::uuid7();
        $bookB = new Book(
            $this->bookLibraryB,
            new BookTitle('Book B'),
            new BookAuthor('Author B'),
            new BookIsbn(null),
            new BookPageCount(200),
            BookSource::MANUAL,
            null,
            $shelfB,
            $libraryB,
            [$genre],
        );

        $this->entityManager->persist($bookA);
        $this->entityManager->persist($bookB);
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
