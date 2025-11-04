<?php

declare(strict_types=1);

namespace App\Tests\E2E\POM;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverElement;
use Symfony\Component\Panther\Client as PantherClient;

final class BooksCreatePage
{
    public const URL = '/books/new';

    public const ROOT = '[data-controller="books-create"]';
    public const FORM = '[data-e2e="book-create-form"]';
    public const TITLE_INPUT = '[data-e2e="book-title-input"]';
    public const AUTHOR_INPUT = '[data-e2e="book-author-input"]';
    public const SHELF_SELECT = '[data-e2e="book-shelf-select"]';
    public const GENRES_CONTAINER = '[data-e2e="book-genres-container"]';
    public const ISBN_INPUT = '[data-e2e="book-isbn-input"]';
    public const PAGE_COUNT_INPUT = '[data-e2e="book-page-count-input"]';
    public const SUBMIT_BUTTON = '[data-e2e="book-submit-button"]';
    public const SUMMARY = '[data-books-create-target="fieldSummary"]';
    public const BANNER = '.books-create-view__banner .banner';
    public const BANNER_MESSAGE = '.books-create-view__banner .banner__message';

    public function __construct(private readonly PantherClient $client) {}

    public static function open(PantherClient $client): self
    {
        $client->request('GET', self::URL);
        $client->waitFor(self::ROOT);

        return new self($client);
    }

    public function waitUntilLoaded(): self
    {
        $this->client->waitFor(self::SHELF_SELECT);
        $this->client->wait(15)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::cssSelector(self::SHELF_SELECT . ' option:not([disabled])'),
        ));

        $this->client->waitFor(self::GENRES_CONTAINER);
        $this->client->wait(15)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::cssSelector(self::GENRES_CONTAINER . ' input[type="checkbox"]'),
        ));

        return $this;
    }

    public function typeTitle(string $value): self
    {
        $this->clearAndType(self::TITLE_INPUT, $value);

        return $this;
    }

    public function typeAuthor(string $value): self
    {
        $this->clearAndType(self::AUTHOR_INPUT, $value);

        return $this;
    }

    public function selectShelfByName(string $name): self
    {
        $select = $this->findElementByCss(self::SHELF_SELECT);
        $option = $select->findElement(WebDriverBy::xpath(
            \sprintf('.//option[normalize-space()="%s"]', $name),
        ));
        $option->click();

        return $this;
    }

    public function toggleGenreByName(string $name): self
    {
        $container = $this->findElementByCss(self::GENRES_CONTAINER);
        $checkbox = $container->findElement(WebDriverBy::xpath(
            \sprintf('.//label[contains(normalize-space(), "%s")]/input[@type="checkbox"]', $name),
        ));
        $checkbox->click();

        return $this;
    }

    public function typeIsbn(string $isbn): self
    {
        $this->clearAndType(self::ISBN_INPUT, $isbn);

        return $this;
    }

    public function typePageCount(string $pageCount): self
    {
        $this->clearAndType(self::PAGE_COUNT_INPUT, $pageCount);

        return $this;
    }

    public function submit(): self
    {
        $this->findElementByCss(self::SUBMIT_BUTTON)->click();

        return $this;
    }

    public function waitForBanner(): self
    {
        $this->client->waitFor(self::BANNER);

        return $this;
    }

    public function getBannerMessage(): string
    {
        $elements = $this->client->findElements(WebDriverBy::cssSelector(self::BANNER_MESSAGE));

        if ([] === $elements) {
            return '';
        }

        return trim((string) $elements[0]->getText());
    }

    public function isFieldSummaryVisible(): bool
    {
        $elements = $this->client->findElements(WebDriverBy::cssSelector(self::SUMMARY));

        if ([] === $elements) {
            return false;
        }

        $summary = $elements[0];
        $hidden = $summary->getAttribute('hidden');

        return null === $hidden || '' === $hidden;
    }

    public function getFieldErrorText(string $fieldId): string
    {
        $elements = $this->client->findElements(WebDriverBy::cssSelector($fieldId));

        if ([] === $elements) {
            return '';
        }

        $element = $elements[0];
        $hidden = $element->getAttribute('hidden');

        if (null !== $hidden && '' !== $hidden) {
            return '';
        }

        return trim((string) $element->getText());
    }

    /**
     * @return list<string>
     */
    public function getSummaryMessages(): array
    {
        $items = $this->client->findElements(WebDriverBy::cssSelector(self::SUMMARY . ' li'));

        return array_values(array_filter(array_map(static fn (WebDriverElement $element): string => trim((string) $element->getText()), $items)));
    }

    public function waitForShelfOption(string $shelfName): self
    {
        $this->client->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath(
                \sprintf('//select[@data-e2e="book-shelf-select"]/option[normalize-space()="%s"]', $shelfName),
            ),
        ));

        return $this;
    }

    public function waitForGenre(string $genreName): self
    {
        $this->client->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath(
                \sprintf('//div[@data-e2e="book-genres-container"]//label[contains(normalize-space(), "%s")]', $genreName),
            ),
        ));

        return $this;
    }

    private function clearAndType(string $selector, string $value): void
    {
        $element = $this->findElementByCss($selector);
        $element->clear();
        $element->sendKeys($value);
    }

    private function findElementByCss(string $selector): WebDriverElement
    {
        return $this->client->findElement(WebDriverBy::cssSelector($selector));
    }
}
