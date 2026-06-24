<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Auth;

use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRepository;
use App\HomeLibrary\Domain\User\UserRoles;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthApiTest extends WebTestCase
{
    private Connection $connection;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::createClient();

        $container = self::getContainer();

        $this->connection = $container->get(Connection::class);
        $this->csrfTokenManager = $container->get(CsrfTokenManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $this->truncateUsers();

        self::ensureKernelShutdown();
    }

    #[Test]
    public function itRegistersUserAndReturnsCurrentUser(): void
    {
        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'new-user@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Test Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = $this->decodeResponse($response);

        self::assertArrayHasKey('user', $payload);
        self::assertSame('new-user@example.com', $payload['user']['email']);

        $client->request('GET', '/api/auth/me');
        $meResponse = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $meResponse->getStatusCode());

        $mePayload = $this->decodeResponse($meResponse);
        self::assertSame($payload['user']['id'], $mePayload['user']['id']);
        self::assertSame('new-user@example.com', $mePayload['user']['email']);
    }

    #[Test]
    public function itRejectsRegistrationWhenEmailAlreadyExists(): void
    {
        $this->createUser('duplicate@example.com', 'password123');

        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
            'libraryName' => 'Dup Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/user-conflict', $payload['type']);
    }

    #[Test]
    public function itRejectsRegistrationWhenLibraryNameAlreadyExists(): void
    {
        $client = static::createClient();

        // First registration creates the library
        $this->postJson($client, '/api/auth/register', [
            'email' => 'first@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Shared Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::ensureKernelShutdown();
        $client = static::createClient();

        // Second registration with same library name should fail
        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'second@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Shared Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/library-conflict', $payload['type']);
    }

    #[Test]
    public function itRejectsRegistrationWhenLibraryModeIsMissing(): void
    {
        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'nomode@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Some Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => '',
        ], 'authenticate');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/validation-error', $payload['type']);
    }

    #[Test]
    public function itAuthenticatesExistingUserViaJsonLogin(): void
    {
        $this->createUser('login@example.com', 'Password123');

        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123',
        ], 'authenticate');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('login@example.com', $payload['user']['email']);

        $client->request('GET', '/api/auth/me');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function itRejectsInvalidCredentials(): void
    {
        $this->createUser('login@example.com', 'Password123');

        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ], 'authenticate');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/invalid-credentials', $payload['type']);
    }

    #[Test]
    public function itRegistersUserByJoiningExistingLibrary(): void
    {
        $client = static::createClient();

        // First: create the library via a "create" registration
        $this->postJson($client, '/api/auth/register', [
            'email' => 'creator@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Join Target Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::ensureKernelShutdown();
        $client = static::createClient();

        // Second: join the library
        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'joiner@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Join Target Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'join',
        ], 'authenticate');

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertArrayHasKey('user', $payload);
        self::assertSame('joiner@example.com', $payload['user']['email']);
    }

    #[Test]
    public function itRejectsJoinWhenLibraryDoesNotExist(): void
    {
        $client = static::createClient();

        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'joiner@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Nonexistent Library',
            'libraryPassword' => 'LibPass123',
            'libraryMode' => 'join',
        ], 'authenticate');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/library-not-found', $payload['type']);
    }

    #[Test]
    public function itRejectsJoinWhenLibraryPasswordIsWrong(): void
    {
        $client = static::createClient();

        // First: create the library
        $this->postJson($client, '/api/auth/register', [
            'email' => 'creator@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Protected Library',
            'libraryPassword' => 'CorrectPass1',
            'libraryMode' => 'create',
        ], 'authenticate');

        self::ensureKernelShutdown();
        $client = static::createClient();

        // Second: try to join with wrong password
        $response = $this->postJson($client, '/api/auth/register', [
            'email' => 'joiner@example.com',
            'password' => 'SecurePa55',
            'passwordConfirm' => 'SecurePa55',
            'libraryName' => 'Protected Library',
            'libraryPassword' => 'WrongPass11',
            'libraryMode' => 'join',
        ], 'authenticate');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/invalid-library-password', $payload['type']);
    }

    private function truncateUsers(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE libraries RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email, string $plainPassword): void
    {
        $library = new Library(
            Uuid::uuid7(),
            new LibraryName('Test Library ' . $email),
            LibraryPasswordHash::fromString('$2y$13$testhashedpassword000000000000000000000000000000000'),
        );

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
    }

    private function postJson(
        $client,
        string $uri,
        array $payload,
        ?string $csrfTokenId = null,
    ): Response {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if (null !== $csrfTokenId) {
            $server['HTTP_X_CSRF_TOKEN'] = $this->csrfTokenManager->getToken($csrfTokenId)->getValue();
        }

        $client->request(
            'POST',
            $uri,
            [],
            [],
            $server,
            json_encode($payload, \JSON_THROW_ON_ERROR),
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
