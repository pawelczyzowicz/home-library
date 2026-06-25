<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class LibraryEdgeCasesE2ETest extends E2ETestCase
{
    public function testNoEndpointToDeleteLibrary(): void
    {
        $email = 'e2e-edge-del-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        $httpClient->request('DELETE', '/api/libraries/00000000-0000-0000-0000-000000000001');

        $status = $httpClient->getResponse()->getStatusCode();
        self::assertContains($status, [404, 405], \sprintf(
            'DELETE /api/libraries/{id} should return 404 or 405, got %d.',
            $status,
        ));
    }

    public function testNoEndpointToUpdateLibrary(): void
    {
        $email = 'e2e-edge-upd-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        $httpClient->request(
            'PUT',
            '/api/libraries/00000000-0000-0000-0000-000000000001',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Hacked Library'], \JSON_THROW_ON_ERROR),
        );

        $status = $httpClient->getResponse()->getStatusCode();
        self::assertContains($status, [404, 405], \sprintf(
            'PUT /api/libraries/{id} should return 404 or 405, got %d.',
            $status,
        ));
    }

    public function testNoEndpointToPatchLibrary(): void
    {
        $email = 'e2e-edge-patch-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        $httpClient->request(
            'PATCH',
            '/api/libraries/00000000-0000-0000-0000-000000000001',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Patched Library'], \JSON_THROW_ON_ERROR),
        );

        $status = $httpClient->getResponse()->getStatusCode();
        self::assertContains($status, [404, 405], \sprintf(
            'PATCH /api/libraries/{id} should return 404 or 405, got %d.',
            $status,
        ));
    }

    public function testNoEndpointToListLibraries(): void
    {
        $email = 'e2e-edge-list-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        $httpClient->request('GET', '/api/libraries');

        $status = $httpClient->getResponse()->getStatusCode();
        self::assertContains($status, [404, 405], \sprintf(
            'GET /api/libraries should return 404 or 405, got %d.',
            $status,
        ));
    }

    public function testUserCannotChangeLibraryAfterRegistration(): void
    {
        $email = 'e2e-edge-change-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        // Attempt to change library via a hypothetical endpoint
        $httpClient->request(
            'POST',
            '/api/auth/change-library',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'libraryName' => 'Another Library',
                'libraryPassword' => 'SomePass!',
            ], \JSON_THROW_ON_ERROR),
        );

        $status = $httpClient->getResponse()->getStatusCode();
        self::assertContains($status, [404, 405], \sprintf(
            'POST /api/auth/change-library should return 404 or 405, got %d.',
            $status,
        ));
    }
}
