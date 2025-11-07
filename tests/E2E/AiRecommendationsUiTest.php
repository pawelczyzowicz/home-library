<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\E2E\POM\AiRecommendationsFormPage;

final class AiRecommendationsUiTest extends E2ETestCase
{
    public function testGenerateRecommendationsRedirectsToResults(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-ai-success-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $this->registerUser(null, $email, $password);
        $this->loginWithPantherClient($client, $email, $password);

        $page = AiRecommendationsFormPage::open($client);

        $page
            ->typeInput(0, 'Wiedźmin Andrzej Sapkowski')
            ->submit()
            ->waitForUrlContaining('/ai/recommendations/');

        $currentUrl = $client->getCurrentURL();
        self::assertMatchesRegularExpression('#/ai/recommendations/\d+#', (string) $currentUrl);
    }

    public function testEmptySubmitShowsValidationErrors(): void
    {
        $client = static::createPantherClient([], ['environment' => 'test', 'debug' => true]);

        $email = 'e2e-ai-validate-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $this->registerUser(null, $email, $password);
        $this->loginWithPantherClient($client, $email, $password);

        $page = AiRecommendationsFormPage::open($client);

        $page->submit()->waitForBanner();

        self::assertTrue($page->isFieldSummaryVisible(), 'Field summary should be visible when validation fails.');

        $messages = $page->getSummaryMessages();
        self::assertContains('Podaj co najmniej jeden tytuł lub autora.', $messages);

        self::assertSame('Podaj co najmniej jeden tytuł lub autora.', $page->getGeneralErrorMessage());
        self::assertSame('Popraw błędy w formularzu i spróbuj ponownie.', $page->getBannerMessage());
    }
}
