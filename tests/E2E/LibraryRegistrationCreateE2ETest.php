<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class LibraryRegistrationCreateE2ETest extends E2ETestCase
{
    public function testRegisterWithCreateModeCreatesUserAndLibrary(): void
    {
        $email = 'e2e-lib-create-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';
        $libraryName = 'E2E Create Library ' . bin2hex(random_bytes(4));
        $libraryPassword = 'LibraryPass123!';

        $httpClient = $this->registerUser(
            null,
            $email,
            $password,
            $libraryName,
            $libraryPassword,
            'create',
        );

        $httpClient->request('GET', '/api/auth/me');
        self::assertSame(200, $httpClient->getResponse()->getStatusCode());

        $mePayload = json_decode(
            (string) $httpClient->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertSame($email, $mePayload['user']['email']);
        self::assertArrayHasKey('library', $mePayload['user']);
        self::assertSame($libraryName, $mePayload['user']['library']['name']);
    }

    public function testRegisterWithDuplicateLibraryNameReturns422(): void
    {
        $libraryName = 'E2E Unique Library ' . bin2hex(random_bytes(4));
        $libraryPassword = 'LibraryPass123!';

        $this->registerUser(
            null,
            'e2e-lib-first-' . bin2hex(random_bytes(4)) . '@example.com',
            'SecurePass123!',
            $libraryName,
            $libraryPassword,
            'create',
        );

        $httpClient = $this->createHttpBrowser();
        $csrfToken = $this->fetchCsrfToken($httpClient, 'authenticate');

        $httpClient->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode([
                'email' => 'e2e-lib-dup-' . bin2hex(random_bytes(4)) . '@example.com',
                'password' => 'SecurePass123!',
                'passwordConfirm' => 'SecurePass123!',
                'libraryName' => $libraryName,
                'libraryPassword' => $libraryPassword,
                'libraryMode' => 'create',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(422, $httpClient->getResponse()->getStatusCode());

        $payload = json_decode(
            (string) $httpClient->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertSame('https://example.com/problems/library-conflict', $payload['type']);
    }

    public function testRegisterWithMissingLibraryModeReturns422(): void
    {
        $httpClient = $this->createHttpBrowser();
        $csrfToken = $this->fetchCsrfToken($httpClient, 'authenticate');

        $httpClient->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode([
                'email' => 'e2e-nomode-' . bin2hex(random_bytes(4)) . '@example.com',
                'password' => 'SecurePass123!',
                'passwordConfirm' => 'SecurePass123!',
                'libraryName' => 'Some Library',
                'libraryPassword' => 'SomePass123!',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(422, $httpClient->getResponse()->getStatusCode());
    }
}
