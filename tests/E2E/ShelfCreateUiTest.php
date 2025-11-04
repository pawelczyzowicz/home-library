<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\E2E\POM\ShelvesPage;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

final class ShelfCreateUiTest extends E2ETestCase
{
    public function testCreateShelfViaUiShowsInTable(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-shelf-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $this->registerUser(null, $email, $password);

        $client->request('GET', '/auth/login');
        $client->waitFor('meta[name="csrf-token-authenticate"]');

        $csrfToken = (string) $client->executeScript(
            'return document.querySelector("meta[name=\"csrf-token-authenticate\"]")?.getAttribute("content") ?? "";',
        );

        self::assertNotSame('', $csrfToken, 'Login CSRF token should be available.');

        $loginStatus = (int) $client->executeScript(
            'const token = arguments[0];
             const email = arguments[1];
             const password = arguments[2];
             const xhr = new XMLHttpRequest();
             xhr.open("POST", "/api/auth/login", false);
             xhr.setRequestHeader("Accept", "application/json");
             xhr.setRequestHeader("Content-Type", "application/json");
             xhr.setRequestHeader("X-CSRF-Token", token);
             xhr.send(JSON.stringify({ email, password }));
             return xhr.status;',
            [$csrfToken, $email, $password],
        );

        self::assertSame(200, $loginStatus, \sprintf('Login failed with status: %s', (string) $loginStatus));

        $client->request('GET', '/books');
        $client->waitFor('[data-controller="books-page"]');

        $page = ShelvesPage::open($client)->waitUntilLoaded();

        $name = 'E2E UI Shelf ' . bin2hex(random_bytes(3));

        $page->typeName($name)->submitCreate();

        $client->wait(30)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath(
                \sprintf('//tbody[@data-shelves-target="tableBody"]/tr/td[1][normalize-space()="%s"]', $name),
            ),
        ));

        $cells = $client->findElements(WebDriverBy::xpath(
            \sprintf('//tbody[@data-shelves-target="tableBody"]/tr/td[1][normalize-space()="%s"]', $name),
        ));

        self::assertNotEmpty($cells, \sprintf('Table should contain a row with shelf name "%s"', $name));
    }
}
