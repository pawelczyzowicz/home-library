<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\UI\Api\Auth;

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
        ], 'authenticate');

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $payload = $this->decodeResponse($response);
        self::assertSame('https://example.com/problems/user-conflict', $payload['type']);
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

    private function truncateUsers(): void
    {
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
