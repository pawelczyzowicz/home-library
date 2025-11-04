<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Process\Process;
use Symfony\Component\Panther\Client as PantherClient;
use Symfony\Component\Panther\PantherTestCase;

abstract class E2ETestCase extends PantherTestCase
{
    private static bool $assetsCompiled = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$assetsCompiled) {
            $this->compileFrontendAssets();
            self::$assetsCompiled = true;
        }
    }

    protected function createHttpBrowser(): HttpBrowser
    {
        /** @var HttpBrowser $browser */
        $browser = static::createHttpBrowserClient([], ['environment' => 'test', 'debug' => true]);
        $browser->restart();

        return $browser;
    }

    protected function fetchCsrfToken(HttpBrowser $httpClient, string $tokenId): string
    {
        $crawler = $httpClient->request('GET', '/auth/login');
        $node = $crawler->filter(\sprintf('meta[name="csrf-token-%s"]', $tokenId));

        self::assertGreaterThan(
            0,
            $node->count(),
            \sprintf('CSRF meta tag for %s must exist.', $tokenId),
        );

        return (string) $node->attr('content');
    }

    protected function registerUser(?PantherClient $pantherClient, string $email, string $password): HttpBrowser
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
                'email' => $email,
                'password' => $password,
                'passwordConfirm' => $password,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClient->getResponse()->getStatusCode(), 'Register endpoint should return 201.');

        if (null !== $pantherClient) {
            $this->syncHttpCookiesToPanther($pantherClient, $httpClient);
        }

        return $httpClient;
    }

    /**
     * @return array{id: string, name: string, isSystem?: bool}
     */
    protected function createShelf(HttpBrowser $httpClient, ?string $name = null): array
    {
        $shelfName = $name ?? ('E2E Shelf ' . bin2hex(random_bytes(4)));

        $httpClient->request(
            'POST',
            '/api/shelves',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => $shelfName], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClient->getResponse()->getStatusCode(), 'Shelf creation must return 201.');

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($payload, 'Shelf payload must be an array.');
        self::assertArrayHasKey('id', $payload, 'Shelf payload must include id.');

        return [
            'id' => (string) $payload['id'],
            'name' => isset($payload['name']) ? (string) $payload['name'] : $shelfName,
            'isSystem' => isset($payload['isSystem']) ? (bool) $payload['isSystem'] : false,
        ];
    }

    protected function deleteShelf(HttpBrowser $httpClient, string $shelfId): void
    {
        $httpClient->request('DELETE', \sprintf('/api/shelves/%s', $shelfId));

        self::assertSame(204, $httpClient->getResponse()->getStatusCode(), 'Shelf deletion must return 204.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function listGenres(HttpBrowser $httpClient): array
    {
        $httpClient->request('GET', '/api/genres');

        self::assertSame(200, $httpClient->getResponse()->getStatusCode(), 'Listing genres must return 200.');

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $entries = isset($payload['data']) && \is_array($payload['data']) ? $payload['data'] : [];

        return array_values(array_filter(
            array_map(static function ($item): ?array {
                if (!\is_array($item) || !isset($item['id'], $item['name'])) {
                    return null;
                }

                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                ];
            }, $entries),
        ));
    }

    protected function syncHttpCookiesToPanther(PantherClient $pantherClient, HttpBrowser $httpClient): void
    {
        $pantherClient->get('/');
        $currentUrl = $pantherClient->getWebDriver()->getCurrentURL();
        $parsed = parse_url($currentUrl) ?: [];
        $host = $parsed['host'] ?? 'localhost';

        $pantherCookieJar = $pantherClient->getCookieJar();
        $pantherCookieJar->clear();

        foreach ($httpClient->getCookieJar()->all() as $cookie) {
            $pantherCookieJar->set(new Cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                '/',
                $host,
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
            ));
        }
    }

    private function compileFrontendAssets(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $process = new Process(['php', 'bin/console', 'asset-map:compile', '--env=test'], $projectDir);
        $process->mustRun();
    }
}
