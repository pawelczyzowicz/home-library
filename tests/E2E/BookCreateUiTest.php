<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\E2E\POM\BooksCreatePage;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

final class BookCreateUiTest extends E2ETestCase
{
    public function testCreateBookViaUiShowsBannerOnList(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-book-ui-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);
        $shelf = $this->createShelf($httpClient, 'UI Shelf ' . bin2hex(random_bytes(3)));
        $genres = $this->listGenres($httpClient);

        self::assertGreaterThanOrEqual(2, \count($genres), 'Test expects at least two genres to be available.');

        $this->loginWithPantherClient($client, $email, $password);

        $page = BooksCreatePage::open($client)->waitUntilLoaded();
        $page->waitForShelfOption($shelf['name']);
        $page->waitForGenre($genres[0]['name']);

        $title = 'UI Flow Book ' . random_int(1000, 9999);

        $page
            ->typeTitle($title)
            ->typeAuthor('E2E Author')
            ->selectShelfByName($shelf['name'])
            ->toggleGenreByName($genres[0]['name'])
            ->toggleGenreByName($genres[1]['name'])
            ->typeIsbn('9788324674603')
            ->typePageCount('384')
            ->submit();

        $client->wait(10)->until(WebDriverExpectedCondition::urlContains('/books?notice=book-created'));
        $client->waitFor('[data-controller="books-page"]');

        $client->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(
            WebDriverBy::xpath(
                \sprintf('//table//tbody/tr/td[normalize-space()="%s"]', $title),
            ),
        ));

        $bannerElements = $client->findElements(WebDriverBy::cssSelector('[data-testid="banner-alert"] .alert__message'));
        self::assertNotEmpty($bannerElements, 'Books list should show success banner after creation.');
        self::assertSame('Książka została dodana.', trim((string) $bannerElements[0]->getText()));
    }

    public function testValidationErrorsAreShownForEmptyForm(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-book-validate-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);
        $this->createShelf($httpClient, 'Validation Shelf ' . bin2hex(random_bytes(3)));
        $this->listGenres($httpClient);

        $this->loginWithPantherClient($client, $email, $password);

        $page = BooksCreatePage::open($client)->waitUntilLoaded();

        $page->submit()->waitForBanner();

        self::assertTrue($page->isFieldSummaryVisible(), 'Field summary should be visible after validation errors.');

        $messages = $page->getSummaryMessages();
        self::assertNotEmpty($messages, 'Field summary should list validation errors.');
        self::assertContains('Tytuł jest wymagany.', $messages);
        self::assertContains('Autor jest wymagany.', $messages);
        self::assertContains('Wybierz regał.', $messages);
        self::assertContains('Wybierz co najmniej 1 gatunek.', $messages);

        self::assertSame('Tytuł jest wymagany.', $page->getFieldErrorText('#book-title-error'));
        self::assertSame('Autor jest wymagany.', $page->getFieldErrorText('#book-author-error'));
        self::assertSame('Wybierz regał.', $page->getFieldErrorText('#book-shelf-error'));
        self::assertSame('Wybierz co najmniej 1 gatunek.', $page->getFieldErrorText('#book-genres-error'));

        $bannerMessage = $page->getBannerMessage();
        self::assertSame('Popraw błędy w formularzu i spróbuj ponownie.', $bannerMessage);
    }

    public function testBannerShownWhenShelfBecomesUnavailable(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-book-404-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);
        $shelf = $this->createShelf($httpClient, '404 Shelf ' . bin2hex(random_bytes(3)));
        $genres = $this->listGenres($httpClient);

        self::assertNotEmpty($genres, 'At least one genre is required for the 404 scenario.');

        $this->loginWithPantherClient($client, $email, $password);

        $page = BooksCreatePage::open($client)->waitUntilLoaded();
        $page->waitForShelfOption($shelf['name']);
        $page->waitForGenre($genres[0]['name']);

        $page
            ->typeTitle('Missing Shelf Book')
            ->typeAuthor('E2E Author')
            ->selectShelfByName($shelf['name'])
            ->toggleGenreByName($genres[0]['name']);

        $this->deleteShelf($httpClient, $shelf['id']);

        $page->submit()->waitForBanner();

        self::assertSame(
            'Nie znaleziono wybranego regału lub gatunku. Odśwież listy i spróbuj ponownie.',
            $page->getBannerMessage(),
        );

        self::assertFalse($page->isFieldSummaryVisible(), 'Field summary should remain hidden for backend 404 error.');
    }
}
