<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Panther\Client as PantherClient;

final class AuthFlowTest extends PantherTestCase
{
    public function testRegisterAutoLoginAndLogout(): void
    {
        $client = static::createPantherClient();

        $csrfTokenNode = $client->request('GET', '/')->filter('meta[name="csrf-token-authenticate"]');
        self::assertGreaterThan(0, $csrfTokenNode->count(), 'CSRF meta tag for authenticate should exist.');

        $csrfToken = (string) $csrfTokenNode->attr('content');

        $email = 'e2e-user-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123';

        $client->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrfToken,
            ],
            content: json_encode([
                'email' => $email,
                'password' => $password,
                'passwordConfirm' => $password,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/auth/me');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $mePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($email, $mePayload['user']['email']);

        $logoutCsrf = $this->fetchCsrfToken($client, 'logout');

        $client->request(
            'POST',
            '/api/auth/logout',
            server: [
                'HTTP_X_CSRF_TOKEN' => $logoutCsrf,
            ],
        );

        self::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/auth/me');
        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    private function fetchCsrfToken(PantherClient $client, string $tokenId): string
    {
        $node = $client->request('GET', '/')->filter(\sprintf('meta[name="csrf-token-%s"]', $tokenId));

        self::assertGreaterThan(0, $node->count(), \sprintf('CSRF meta tag for %s must exist.', $tokenId));

        return (string) $node->attr('content');
    }
}
