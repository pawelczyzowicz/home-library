<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class AuthFlowTest extends E2ETestCase
{
    public function testRegisterAutoLoginAndLogout(): void
    {
        $email = 'e2e-user-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123';

        $httpClient = $this->registerUser(null, $email, $password);

        $httpClient->request('GET', '/api/auth/me');
        self::assertSame(200, $httpClient->getResponse()->getStatusCode());

        $mePayload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($email, $mePayload['user']['email']);

        $logoutCsrf = $this->fetchCsrfToken($httpClient, 'logout');

        $httpClient->followRedirects(false);
        $httpClient->request(
            'POST',
            '/api/auth/logout',
            parameters: [
                '_csrf_token' => $logoutCsrf,
            ],
            server: [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
        $httpClient->followRedirects(true);

        $logoutStatus = $httpClient->getResponse()->getStatusCode();
        self::assertSame(
            302,
            $logoutStatus,
            \sprintf('Unexpected logout status %d with body: %s', $logoutStatus, (string) $httpClient->getResponse()->getContent()),
        );

        $logoutLocation = (string) $httpClient->getResponse()->getHeader('Location');
        self::assertNotSame('', $logoutLocation, 'Logout response should contain a Location header.');
        self::assertSame(
            '/',
            parse_url($logoutLocation, \PHP_URL_PATH) ?? '',
            'Logout redirect should target root path.',
        );

        $httpClient->request('GET', '/api/auth/me');
        self::assertSame(401, $httpClient->getResponse()->getStatusCode());
    }
}
