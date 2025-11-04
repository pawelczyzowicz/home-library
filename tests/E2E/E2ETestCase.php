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
