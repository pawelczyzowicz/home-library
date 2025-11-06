<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Application\AI\IRecommendationProvider;
use App\HomeLibrary\Domain\AI\RecommendationProposal;

final class MockOpenRouterRecommendationProvider implements IRecommendationProvider
{
    /**
     * @var array<int, array{tempId: string, title: string, author: string, genresId: int[], reason: string}>
     */
    private const DATASET = [
        [
            'tempId' => 'mock-1',
            'title' => 'The Name of the Wind',
            'author' => 'Patrick Rothfuss',
            'genresId' => [2, 5],
            'reason' => 'Expansive fantasy world with a gifted protagonist.',
        ],
        [
            'tempId' => 'mock-2',
            'title' => 'Ancillary Justice',
            'author' => 'Ann Leckie',
            'genresId' => [5, 12],
            'reason' => 'Space opera blending identity and justice themes.',
        ],
        [
            'tempId' => 'mock-3',
            'title' => 'The Left Hand of Darkness',
            'author' => 'Ursula K. Le Guin',
            'genresId' => [5, 9],
            'reason' => 'Classic speculative fiction about culture and gender.',
        ],
        [
            'tempId' => 'mock-4',
            'title' => 'The City & The City',
            'author' => 'China Miéville',
            'genresId' => [1, 3, 12],
            'reason' => 'Murder mystery layered with surreal worldbuilding.',
        ],
        [
            'tempId' => 'mock-5',
            'title' => 'Kindred',
            'author' => 'Octavia E. Butler',
            'genresId' => [5, 8],
            'reason' => 'Time-travel narrative examining history and identity.',
        ],
        [
            'tempId' => 'mock-6',
            'title' => 'The Lies of Locke Lamora',
            'author' => 'Scott Lynch',
            'genresId' => [2, 3],
            'reason' => 'Heist-driven fantasy with sharp dialogue.',
        ],
        [
            'tempId' => 'mock-7',
            'title' => 'The Calculating Stars',
            'author' => 'Mary Robinette Kowal',
            'genresId' => [5, 9],
            'reason' => 'Alternate history of women pioneers in space.',
        ],
        [
            'tempId' => 'mock-8',
            'title' => 'Station Eleven',
            'author' => 'Emily St. John Mandel',
            'genresId' => [5, 9, 13],
            'reason' => 'Post-apocalyptic story about art, memory, and hope.',
        ],
    ];

    public function generate(array $inputs): array
    {
        $seed = $this->computeSeed($inputs);
        $offset = $seed % \count(self::DATASET);

        $proposals = [];

        for ($i = 0; $i < 3; ++$i) {
            $index = ($offset + $i) % \count(self::DATASET);
            $data = self::DATASET[$index];
            $proposals[] = new RecommendationProposal(
                $this->hydrateTempId($data['tempId'], $i),
                $data['title'],
                $data['author'],
                $data['genresId'],
                $this->adaptReason($data['reason'], $inputs),
            );
        }

        return $proposals;
    }

    private function computeSeed(array $inputs): int
    {
        $payload = json_encode([
            'inputs' => array_values($inputs),
        ], \JSON_THROW_ON_ERROR);

        return (int) (abs(crc32($payload)) ?: 1);
    }

    private function hydrateTempId(string $base, int $index): string
    {
        return \sprintf('%s-%d', $base, $index + 1);
    }

    private function adaptReason(string $baseReason, array $inputs): string
    {
        $firstInput = $inputs[0] ?? null;

        if (null === $firstInput) {
            return $baseReason;
        }

        return \sprintf('%s Inspired by "%s".', $baseReason, $firstInput);
    }
}
