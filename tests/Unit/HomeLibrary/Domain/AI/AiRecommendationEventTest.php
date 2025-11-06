<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\AI;

use App\HomeLibrary\Domain\AI\AiRecommendationEvent;
use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationEventException;
use App\HomeLibrary\Domain\AI\Exception\RecommendationAlreadyAcceptedException;
use App\HomeLibrary\Domain\AI\RecommendationProposal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class AiRecommendationEventTest extends TestCase
{
    #[Test]
    public function itCreatesEventAndAcceptsBook(): void
    {
        $userId = Uuid::uuid4();
        $proposal = new RecommendationProposal('temp', 'Title', 'Author', [1], 'Reason');

        $event = AiRecommendationEvent::create(
            $userId,
            ['  Book Title  '],
            [$proposal, clone $proposal, clone $proposal],
            'model-name',
        );

        self::assertSame(['Book Title'], $event->inputTitles());
        self::assertCount(3, $event->recommended());
        self::assertSame('model-name', $event->model());
        self::assertTrue($event->isOwnedBy($userId));

        $bookId = Uuid::uuid7();
        $event->acceptBook($bookId);

        self::assertCount(1, $event->acceptedBookIds());
        self::assertTrue($event->acceptedBookIds()[0]->equals($bookId));
    }

    #[Test]
    public function itPreventsDuplicatedBookAcceptance(): void
    {
        $event = AiRecommendationEvent::create(
            null,
            ['Title'],
            [
                new RecommendationProposal('t1', 'T1', 'A1', [1], 'R1'),
                new RecommendationProposal('t2', 'T2', 'A2', [2], 'R2'),
                new RecommendationProposal('t3', 'T3', 'A3', [3], 'R3'),
            ],
            null,
        );

        $bookId = Uuid::uuid7();
        $event->acceptBook($bookId);

        $this->expectException(RecommendationAlreadyAcceptedException::class);
        $event->acceptBook($bookId);
    }

    #[Test]
    #[DataProvider('invalidInputProvider')]
    public function itGuardsAgainstInvalidInputs(array $inputTitles, string $expectedMessage): void
    {
        $proposal = new RecommendationProposal('temp', 'Title', 'Author', [1], 'Reason');

        $this->expectException(InvalidRecommendationEventException::class);
        $this->expectExceptionMessage($expectedMessage);

        AiRecommendationEvent::create(
            null,
            $inputTitles,
            [$proposal, clone $proposal, clone $proposal],
            null,
        );
    }

    /**
     * @return iterable<string, array{0: array, 1: string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'empty list' => [
            [],
            'Recommendation event must contain at least one input title.',
        ];

        yield 'non string entry' => [
            [123],
            'Each input title must be a string.',
        ];

        yield 'blank entry' => [
            [''],
            'Each input title must be a non-empty string.',
        ];
    }

    #[Test]
    public function itGuardsAgainstInvalidProposalList(): void
    {
        $this->expectException(InvalidRecommendationEventException::class);
        $this->expectExceptionMessage('Recommendation event must contain exactly 3 proposals.');

        AiRecommendationEvent::create(
            null,
            ['Book'],
            [new RecommendationProposal('t', 'T', 'A', [1], 'R')],
            null,
        );
    }
}
