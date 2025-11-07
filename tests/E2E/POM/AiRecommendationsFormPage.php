<?php

declare(strict_types=1);

namespace App\Tests\E2E\POM;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverElement;
use Symfony\Component\Panther\Client as PantherClient;

final class AiRecommendationsFormPage
{
    public const URL = '/ai/recommendations';

    private const ROOT = '[data-controller="ai-recommendations-form"]';
    private const INPUT_SELECTOR = 'input[name="inputs[]"]';
    private const SUBMIT_BUTTON = '[data-e2e="ai-reco-submit"]';
    private const SUMMARY = '[data-ai-recommendations-form-target="fieldSummary"]';
    private const SUMMARY_ITEMS = '[data-ai-recommendations-form-target="fieldSummary"] li';
    private const GENERAL_ERROR = '[data-ai-recommendations-form-target="inputsGeneralError"]';
    private const BANNER = '.ai-recommendations-view__banner .banner';
    private const BANNER_MESSAGE = '.ai-recommendations-view__banner .banner__message';

    public function __construct(private readonly PantherClient $client) {}

    public static function open(PantherClient $client): self
    {
        $client->request('GET', self::URL);
        $client->waitFor(self::ROOT);

        return (new self($client))->waitUntilLoaded();
    }

    public function waitUntilLoaded(): self
    {
        $this->client->waitFor(self::INPUT_SELECTOR);
        $client = $this->client;
        $this->client->wait(5)->until(static fn (): bool => \count($client->findElements(WebDriverBy::cssSelector(self::INPUT_SELECTOR))) >= 3);

        return $this;
    }

    public function typeInput(int $index, string $value): self
    {
        $input = $this->getInputByIndex($index);
        $input->clear();
        $input->sendKeys($value);

        return $this;
    }

    public function submit(): self
    {
        $this->client->findElement(WebDriverBy::cssSelector(self::SUBMIT_BUTTON))->click();

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

    /**
     * @return list<string>
     */
    public function getSummaryMessages(): array
    {
        $items = $this->client->findElements(WebDriverBy::cssSelector(self::SUMMARY_ITEMS));

        return array_values(array_filter(array_map(static fn (WebDriverElement $element): string => trim((string) $element->getText()), $items)));
    }

    public function getGeneralErrorMessage(): string
    {
        $elements = $this->client->findElements(WebDriverBy::cssSelector(self::GENERAL_ERROR));

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

    public function getInputError(int $index): string
    {
        $errorId = \sprintf('#ai-input-%d-error', $index);
        $elements = $this->client->findElements(WebDriverBy::cssSelector($errorId));

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

    public function waitForUrlContaining(string $substring, int $timeout = 10): self
    {
        $this->client->wait($timeout)->until(WebDriverExpectedCondition::urlContains($substring));

        return $this;
    }

    private function getInputByIndex(int $index): WebDriverElement
    {
        $inputs = $this->client->findElements(WebDriverBy::cssSelector(self::INPUT_SELECTOR));

        if (!isset($inputs[$index])) {
            throw new \OutOfBoundsException(\sprintf('Input at index %d was not found.', $index));
        }

        return $inputs[$index];
    }
}
