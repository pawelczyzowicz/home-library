<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\AI;

use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationProposalException;
use App\HomeLibrary\Domain\AI\RecommendationProposal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecommendationProposalTest extends TestCase
{
    #[Test]
    public function itBuildsValidProposal(): void
    {
        $proposal = new RecommendationProposal(
            'temp-1',
            'The Left Hand of Darkness',
            'Ursula K. Le Guin',
            [5, 9, 13],
            'Classic speculative fiction.',
        );

        self::assertSame('temp-1', $proposal->tempId());
        self::assertSame('The Left Hand of Darkness', $proposal->title());
        self::assertSame('Ursula K. Le Guin', $proposal->author());
        self::assertSame([5, 9, 13], $proposal->genresId());
        self::assertSame('Classic speculative fiction.', $proposal->reason());
    }

    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function itGuardsAgainstInvalidPayload(array $payload, string $expectedMessage): void
    {
        $this->expectException(InvalidRecommendationProposalException::class);
        $this->expectExceptionMessage($expectedMessage);

        new RecommendationProposal(...$payload);
    }

    /**
     * @return iterable<string, array{0: array, 1: string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'empty temp id' => [
            [' ', 'Title', 'Author', [1], 'Reason'],
            'Field "tempId" must be a non-empty string.',
        ];

        yield 'empty title' => [
            ['temp', '', 'Author', [1], 'Reason'],
            'Field "title" must be a non-empty string.',
        ];

        yield 'empty author' => [
            ['temp', 'Title', ' ', [1], 'Reason'],
            'Field "author" must be a non-empty string.',
        ];

        yield 'empty reason' => [
            ['temp', 'Title', 'Author', [1], ''],
            'Field "reason" must be a non-empty string.',
        ];

        yield 'too few genres' => [
            ['temp', 'Title', 'Author', [], 'Reason'],
            'Field "genresId" must contain between 1 and 3 values.',
        ];

        yield 'too many genres' => [
            ['temp', 'Title', 'Author', [1, 2, 3, 4], 'Reason'],
            'Field "genresId" must contain between 1 and 3 values.',
        ];

        yield 'genre not numeric' => [
            ['temp', 'Title', 'Author', ['foo'], 'Reason'],
            'Each genresId value must be an integer.',
        ];

        yield 'genre out of range' => [
            ['temp', 'Title', 'Author', [16], 'Reason'],
            'Each genresId value must be between 1 and 15.',
        ];
    }

    #[Test]
    public function itNormalizesPayloadFromArray(): void
    {
        $proposal = RecommendationProposal::fromArray([
            'tempId' => 'id-1',
            'title' => 'Title',
            'author' => 'Author',
            'genresId' => ['5', 9],
            'reason' => 'Reason',
        ]);

        self::assertSame(['tempId' => 'id-1', 'title' => 'Title', 'author' => 'Author', 'genresId' => [5, 9], 'reason' => 'Reason'], $proposal->toArray());
    }
}
