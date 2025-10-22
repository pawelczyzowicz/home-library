<?php

declare(strict_types=1);

namespace App\Tests\E2E\POM;

use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client as PantherClient;

final class ShelvesPage
{
    public const URL = '/shelves';

    // Root and key UI hooks
    public const ROOT = '[data-controller="shelves"]';
    public const NAME_INPUT = '[data-e2e="shelf-name-input"]';
    public const CREATE_BUTTON = '[data-e2e="shelf-create-button"]';
    public const TABLE_BODY = '[data-shelves-target="tableBody"]';

    public function __construct(private readonly PantherClient $client) {}

    public static function open(PantherClient $client): self
    {
        $client->request('GET', self::URL);
        $client->waitFor(self::ROOT);

        return new self($client);
    }

    public function typeName(string $name): self
    {
        $input = $this->client->findElement(WebDriverBy::cssSelector(self::NAME_INPUT));
        $input->clear();
        $input->sendKeys($name);

        return $this;
    }

    public function submitCreate(): self
    {
        $this->client->findElement(WebDriverBy::cssSelector(self::CREATE_BUTTON))->click();

        return $this;
    }

    public function waitUntilLoaded(): self
    {
        $this->client->waitFor(self::ROOT);

        return $this;
    }

    public function getTableBodyHtml(): string
    {
        $element = $this->client->findElement(WebDriverBy::cssSelector(self::TABLE_BODY));

        return (string) $element->getAttribute('innerHTML');
    }
}
