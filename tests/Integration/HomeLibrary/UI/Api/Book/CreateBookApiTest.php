<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Book;

use App\HomeLibrary\Domain\Book\BookRepository;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreName;
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

final class CreateBookApiTest extends WebTestCase
{
    private Connection $connection;

    private EntityManagerInterface $entityManager;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    private BookRepository $bookRepository;

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
        $this->bookRepository = $container->get(BookRepository::class);

        $this->truncateTables();

        self::ensureKernelShutdown();
    }

    #[Test]
    public function itCreatesBookWithAiRecommendationPayload(): void
    {
        $email = 'create-book-ai@example.com';
        $password = 'SecurePass123!';

        $user = $this->createUser($email, $password);

        $shelf = new Shelf(Uuid::uuid7(), new ShelfName('Do zakupu'), ShelfFlag::system());
        $genreOne = new Genre(1, new GenreName('Science Fiction'));
        $genreTwo = new Genre(2, new GenreName('Adventure'));

        $this->entityManager->persist($shelf);
        $this->entityManager->persist($genreOne);
        $this->entityManager->persist($genreTwo);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $client = static::createClient();
        $this->authenticate($client, $email, $password);

        $payload = [
            'title' => 'Diuna',
            'author' => 'Frank Herbert',
            'shelfId' => (string) $shelf->id(),
            'genreIds' => [1, 2],
            'isbn' => null,
            'pageCount' => null,
            'source' => 'ai_recommendation',
            'recommendationId' => 321,
        ];

        $response = $this->postJson($client, '/api/books', $payload);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $bookData = $this->decodeResponse($response);
        self::assertSame('ai_recommendation', $bookData['source']);
        self::assertSame(321, $bookData['recommendationId']);

        $bookId = Uuid::fromString($bookData['id']);
        $book = $this->bookRepository->findById($bookId);

        self::assertNotNull($book);
        self::assertSame(BookSource::AI_RECOMMENDATION, $book->source());
        self::assertSame(321, $book->recommendationId());
        self::assertSame('Diuna', $book->title()->value());
        self::assertSame('Frank Herbert', $book->author()->value());
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    private function truncateTables(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE ai_recommendation_accept_requests RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE ai_recommendation_events RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE book_genre RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE books RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE genres RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE shelves RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email, string $plainPassword): User
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

        return $user;
    }

    private function authenticate(KernelBrowser $client, string $email, string $password): void
    {
        $response = $this->postJson($client, '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function postJson(KernelBrowser $client, string $uri, array $payload): Response
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CSRF_TOKEN_AUTHENTICATE' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            'HTTP_X_CSRF_TOKEN' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
        ];

        $client->request(
            'POST',
            $uri,
            server: $server,
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
