<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Service;

use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\UI\Api\Book\Dto\CreateBookPayloadDto;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CreateBookPayloadValidator
{
    private const MAX_TEXT_LENGTH = 255;
    private const MIN_GENRES = 1;
    private const MAX_GENRES = 3;
    private const PAGE_COUNT_MIN = 1;
    private const PAGE_COUNT_MAX = 50000;

    /**
     * @return array{
     *     title: string,
     *     author: string,
     *     shelfId: UuidInterface,
     *     genreIds: int[],
     *     isbn: string|null,
     *     pageCount: int|null,
     *     source: BookSource,
     *     recommendationId: int|null,
     * }
     */
    public function validate(CreateBookPayloadDto $payload): array
    {
        $issues = [];

        $title = $this->validateTextField($payload->title(), 'title', $issues);
        $author = $this->validateTextField($payload->author(), 'author', $issues);
        $shelfId = $this->validateShelfId($payload->shelfId(), $issues);
        $genreIds = $this->validateGenreIds($payload->genreIds(), $issues);
        $isbn = $this->validateIsbn($payload->isbn(), $issues);
        $pageCount = $this->validatePageCount($payload->pageCount(), $issues);
        $source = $this->validateSource($payload->source(), $issues);
        $recommendationId = $this->validateRecommendationId($payload->recommendationId(), $issues);

        if (BookSource::AI_RECOMMENDATION === $source && null === $recommendationId) {
            $issues[] = ['parameter' => 'recommendationId', 'message' => 'This value is required for AI recommendations.'];
        }

        if (BookSource::AI_RECOMMENDATION !== $source) {
            $recommendationId = null;
        }

        if ([] !== $issues) {
            throw ValidationException::withIssues($issues);
        }

        return [
            'title' => $title,
            'author' => $author,
            'shelfId' => $shelfId,
            'genreIds' => $genreIds,
            'isbn' => $isbn,
            'pageCount' => $pageCount,
            'source' => $source,
            'recommendationId' => $recommendationId,
        ];
    }

    private function validateTextField(mixed $value, string $field, array &$issues): string
    {
        if (!\is_string($value)) {
            $issues[] = ['parameter' => $field, 'message' => 'This value should be of type string.'];

            return '';
        }

        $normalized = trim($value);

        if ('' === $normalized) {
            $issues[] = ['parameter' => $field, 'message' => 'This value should not be blank.'];

            return '';
        }

        if (mb_strlen($normalized) > self::MAX_TEXT_LENGTH) {
            $issues[] = ['parameter' => $field, 'message' => \sprintf('This value is too long. It should have %d characters or less.', self::MAX_TEXT_LENGTH)];

            return '';
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed>|null $raw
     *
     * @return int[]
     */
    private function validateGenreIds(mixed $raw, array &$issues): array
    {
        if (!\is_array($raw)) {
            $issues[] = ['parameter' => 'genreIds', 'message' => 'This value should be of type array.'];

            return [];
        }

        if (\count($raw) < self::MIN_GENRES || \count($raw) > self::MAX_GENRES) {
            $issues[] = ['parameter' => 'genreIds', 'message' => \sprintf('This collection should contain between %d and %d elements.', self::MIN_GENRES, self::MAX_GENRES)];

            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (!\is_int($value)) {
                $issues[] = ['parameter' => 'genreIds', 'message' => 'Each genre identifier must be an integer.'];

                return [];
            }

            if ($value < 1) {
                $issues[] = ['parameter' => 'genreIds', 'message' => 'Each genre identifier must be a positive integer.'];

                return [];
            }

            $ids[] = $value;
        }

        if (\count($ids) !== \count(array_unique($ids))) {
            $issues[] = ['parameter' => 'genreIds', 'message' => 'Genre identifiers must be unique.'];

            return [];
        }

        return $ids;
    }

    private function validateShelfId(mixed $value, array &$issues): ?UuidInterface
    {
        if (!\is_string($value)) {
            $issues[] = ['parameter' => 'shelfId', 'message' => 'This value should be of type string.'];

            return null;
        }

        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException) {
            $issues[] = ['parameter' => 'shelfId', 'message' => 'This is not a valid UUID.'];

            return null;
        }
    }

    private function validateIsbn(mixed $value, array &$issues): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            $issues[] = ['parameter' => 'isbn', 'message' => 'This value should be of type string.'];

            return null;
        }

        $normalized = preg_replace('/[^\d]/', '', $value) ?? '';

        if ('' === $normalized) {
            return null;
        }

        $length = \strlen($normalized);

        if (10 !== $length && 13 !== $length) {
            $issues[] = ['parameter' => 'isbn', 'message' => 'ISBN must contain 10 or 13 digits.'];

            return null;
        }

        return $normalized;
    }

    private function validatePageCount(mixed $value, array &$issues): ?int
    {
        if (null === $value) {
            return null;
        }

        if (\is_string($value) && '' === trim($value)) {
            return null;
        }

        if (!\is_int($value)) {
            if (\is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            } else {
                $issues[] = ['parameter' => 'pageCount', 'message' => 'This value should be an integer.'];

                return null;
            }
        }

        if ($value < self::PAGE_COUNT_MIN || $value > self::PAGE_COUNT_MAX) {
            $issues[] = ['parameter' => 'pageCount', 'message' => \sprintf('This value should be between %d and %d.', self::PAGE_COUNT_MIN, self::PAGE_COUNT_MAX)];

            return null;
        }

        return $value;
    }

    private function validateSource(mixed $value, array &$issues): BookSource
    {
        if (null === $value) {
            return BookSource::MANUAL;
        }

        if (!\is_string($value)) {
            $issues[] = ['parameter' => 'source', 'message' => 'This value should be of type string.'];

            return BookSource::MANUAL;
        }

        $normalized = strtolower(trim($value));
        $source = BookSource::tryFrom($normalized);

        if (null === $source) {
            $issues[] = ['parameter' => 'source', 'message' => 'This value is not valid.'];

            return BookSource::MANUAL;
        }

        return $source;
    }

    private function validateRecommendationId(mixed $value, array &$issues): ?int
    {
        if (null === $value) {
            return null;
        }

        if (\is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!\is_int($value)) {
            $issues[] = ['parameter' => 'recommendationId', 'message' => 'This value should be an integer.'];

            return null;
        }

        if ($value <= 0) {
            $issues[] = ['parameter' => 'recommendationId', 'message' => 'This value should be a positive integer.'];

            return null;
        }

        return $value;
    }
}
