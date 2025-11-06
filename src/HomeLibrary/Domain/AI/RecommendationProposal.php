<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI;

use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationProposalException;

final class RecommendationProposal
{
    private string $tempId;

    private string $title;

    private string $author;

    /**
     * @var int[]
     */
    private array $genresId;

    private string $reason;

    /**
     * @param int[] $genresId
     */
    public function __construct(string $tempId, string $title, string $author, array $genresId, string $reason)
    {
        $this->tempId = $this->assertNonEmptyString($tempId, 'tempId');
        $this->title = $this->assertNonEmptyString($title, 'title');
        $this->author = $this->assertNonEmptyString($author, 'author');
        $this->genresId = $this->normalizeGenres($genresId);
        $this->reason = $this->assertNonEmptyString($reason, 'reason');
    }

    public static function fromArray(array $data): self
    {
        foreach (['tempId', 'title', 'author', 'genresId', 'reason'] as $key) {
            if (!\array_key_exists($key, $data)) {
                throw InvalidRecommendationProposalException::because(\sprintf('Missing "%s" field in recommendation proposal data.', $key));
            }
        }

        if (!\is_array($data['genresId'])) {
            throw InvalidRecommendationProposalException::because('Field "genresId" must be an array.');
        }

        return new self(
            (string) $data['tempId'],
            (string) $data['title'],
            (string) $data['author'],
            $data['genresId'],
            (string) $data['reason'],
        );
    }

    public function tempId(): string
    {
        return $this->tempId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    /**
     * @return int[]
     */
    public function genresId(): array
    {
        return $this->genresId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'tempId' => $this->tempId,
            'title' => $this->title,
            'author' => $this->author,
            'genresId' => $this->genresId,
            'reason' => $this->reason,
        ];
    }

    private function assertNonEmptyString(mixed $value, string $field): string
    {
        if (!\is_string($value)) {
            throw InvalidRecommendationProposalException::because(\sprintf('Field "%s" must be a string.', $field));
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw InvalidRecommendationProposalException::because(\sprintf('Field "%s" must be a non-empty string.', $field));
        }

        return $trimmed;
    }

    /**
     * @param mixed[] $genres
     *
     * @return int[]
     */
    private function normalizeGenres(array $genres): array
    {
        $count = \count($genres);

        if ($count < 1 || $count > 3) {
            throw InvalidRecommendationProposalException::because('Field "genresId" must contain between 1 and 3 values.');
        }

        $normalized = [];

        foreach ($genres as $genre) {
            if (!\is_int($genre) && !\is_string($genre)) {
                throw InvalidRecommendationProposalException::because('Each genresId value must be an integer.');
            }

            if (\is_string($genre)) {
                $trimmed = trim($genre);

                if ('' === $trimmed || !ctype_digit($trimmed)) {
                    throw InvalidRecommendationProposalException::because('Each genresId value must be an integer.');
                }

                $genreId = (int) $trimmed;
            } else {
                $genreId = $genre;
            }

            if ($genreId < 1 || $genreId > 15) {
                throw InvalidRecommendationProposalException::because('Each genresId value must be between 1 and 15.');
            }

            $normalized[$genreId] = $genreId;
        }

        return array_values($normalized);
    }
}
