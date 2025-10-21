<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\E2E\POM\ShelvesPage;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\PantherTestCase;

final class ShelfCreateUiTest extends PantherTestCase
{
    public function testCreateShelfViaUiShowsInTable(): void
    {
        $client = static::createPantherClient();
        $page = ShelvesPage::open($client);

        $name = 'E2E UI Shelf ' . bin2hex(random_bytes(3));

        $page->typeName($name)->submitCreate();

        // Wait for success banner and list refresh
        $client->waitFor('.banner--success');

        // Verify the new shelf appears in the table (first column contains the name)
        $rows = $client->findElements(WebDriverBy::cssSelector('[data-shelves-target="tableBody"] tr'));
        $found = false;
        foreach ($rows as $row) {
            $cells = $row->findElements(WebDriverBy::cssSelector('td'));
            if (count($cells) > 0 && trim((string) $cells[0]->getText()) === $name) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, sprintf('Table should contain a row with shelf name "%s"', $name));
    }
}


