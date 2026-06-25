<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class LibraryRegistrationJoinE2ETest extends E2ETestCase
{
    public function testRegisterWithJoinModeAssignsUserToExistingLibrary(): void
    {
        $libraryName = 'E2E Shared Library ' . bin2hex(random_bytes(4));
        $libraryPassword = 'SharedPass123!';

        $emailA = 'e2e-join-owner-' . bin2hex(random_bytes(4)) . '@example.com';
        $emailB = 'e2e-join-member-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        // User A creates the library
        $httpClientA = $this->registerUser(null, $emailA, $password, $libraryName, $libraryPassword, 'create');

        $httpClientA->request('GET', '/api/auth/me');
        $meA = json_decode((string) $httpClientA->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // User B joins the existing library
        $httpClientB = $this->registerUser(null, $emailB, $password, $libraryName, $libraryPassword, 'join');

        $httpClientB->request('GET', '/api/auth/me');
        self::assertSame(200, $httpClientB->getResponse()->getStatusCode());

        $meB = json_decode((string) $httpClientB->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame($emailB, $meB['user']['email']);
        self::assertArrayHasKey('library', $meB['user']);
        self::assertSame($libraryName, $meB['user']['library']['name']);

        // Both users belong to the same library
        self::assertSame($meA['user']['library']['id'], $meB['user']['library']['id']);
    }

    public function testJoinWithWrongPasswordReturns422(): void
    {
        $libraryName = 'E2E WrongPass Library ' . bin2hex(random_bytes(4));
        $libraryPassword = 'CorrectPass123!';

        $this->registerUser(
            null,
            'e2e-join-setup-' . bin2hex(random_bytes(4)) . '@example.com',
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
                'email' => 'e2e-join-wrong-' . bin2hex(random_bytes(4)) . '@example.com',
                'password' => 'SecurePass123!',
                'passwordConfirm' => 'SecurePass123!',
                'libraryName' => $libraryName,
                'libraryPassword' => 'WrongPassword!',
                'libraryMode' => 'join',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(422, $httpClient->getResponse()->getStatusCode());

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://example.com/problems/invalid-library-password', $payload['type']);
    }

    public function testJoinWithNonExistentLibraryReturns422(): void
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
                'email' => 'e2e-join-none-' . bin2hex(random_bytes(4)) . '@example.com',
                'password' => 'SecurePass123!',
                'passwordConfirm' => 'SecurePass123!',
                'libraryName' => 'NonExistent Library ' . bin2hex(random_bytes(8)),
                'libraryPassword' => 'SomePass123!',
                'libraryMode' => 'join',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(422, $httpClient->getResponse()->getStatusCode());

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://example.com/problems/library-not-found', $payload['type']);
    }
}
