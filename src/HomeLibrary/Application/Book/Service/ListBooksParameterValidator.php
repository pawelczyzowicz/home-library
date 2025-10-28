<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Service;

use App\HomeLibrary\Application\Exception\ValidationException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ListBooksParameterValidator
{
    private const MAX_QUERY_LENGTH = 255;
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 100;

    private const ALLOWED_SORT = ['title', 'author', 'createdAt'];
    private const ALLOWED_ORDER = ['asc', 'desc'];

    /**
     * @param array{q: mixed, shelfId: mixed, genreIds: mixed, limit: mixed, offset: mixed, sort: mixed, order: mixed} $params
     *
     * @return array{
     *     q: ?string,
     *     shelfId: ?UuidInterface,
     *     genreIds: int[],
     *     limit: int,
     *     offset: int,
     *     sort: string,
     *     order: string,
     * }
     */
    public function validate(array $params): array
    {
        $issues = [];

        $searchTerm = null;
        $rawQuery = $params['q'] ?? null;
        if (null !== $rawQuery) {
            if (!\is_string($rawQuery)) {
                $issues[] = ['parameter' => 'q', 'message' => 'Parameter "q" must be a string.'];
            } else {
                $normalized = trim($rawQuery);
                if ('' !== $normalized) {
                    if (mb_strlen($normalized) > self::MAX_QUERY_LENGTH) {
                        $issues[] = ['parameter' => 'q', 'message' => \sprintf('Parameter "q" must not exceed %d characters.', self::MAX_QUERY_LENGTH)];
                    } else {
                        $searchTerm = $normalized;
                    }
                }
            }
        }

        $shelfId = null;
        $rawShelfId = $params['shelfId'] ?? null;
        if (null !== $rawShelfId && '' !== $rawShelfId) {
            if (!\is_string($rawShelfId)) {
                $issues[] = ['parameter' => 'shelfId', 'message' => 'Parameter "shelfId" must be a string UUID.'];
            } else {
                try {
                    $shelfId = Uuid::fromString($rawShelfId);
                } catch (\InvalidArgumentException $exception) {
                    $issues[] = ['parameter' => 'shelfId', 'message' => 'Parameter "shelfId" must be a valid UUID.'];
                }
            }
        }

        $genreIds = $this->parseGenreIds($params['genreIds'] ?? null, $issues);

        $limit = $this->sanitizeToInt($params['limit'] ?? null, self::MIN_LIMIT, self::MAX_LIMIT, 'limit', $issues, default: 20);
        $offset = $this->sanitizeOffset($params['offset'] ?? null, $issues);

        $sort = $this->sanitizeEnum($params['sort'] ?? null, 'sort', self::ALLOWED_SORT, 'createdAt', $issues);
        $order = $this->sanitizeEnum($params['order'] ?? null, 'order', self::ALLOWED_ORDER, 'desc', $issues);

        if ([] !== $issues) {
            throw ValidationException::withIssues($issues);
        }

        return [
            'q' => $searchTerm,
            'shelfId' => $shelfId,
            'genreIds' => $genreIds,
            'limit' => $limit,
            'offset' => $offset,
            'sort' => $sort,
            'order' => $order,
        ];
    }

    private function parseGenreIds(mixed $raw, array &$issues): array
    {
        if (null === $raw || '' === $raw) {
            return [];
        }

        if (!\is_string($raw)) {
            $issues[] = ['parameter' => 'genreIds', 'message' => 'Parameter "genreIds" must be a comma-separated string of integers.'];

            return [];
        }

        $tokens = array_filter(array_map('trim', explode(',', $raw)), static fn (string $token): bool => '' !== $token);

        $ids = [];
        foreach ($tokens as $token) {
            if (!ctype_digit($token) || (int) $token < 1) {
                $issues[] = ['parameter' => 'genreIds', 'message' => 'Parameter "genreIds" must contain positive integers.'];

                return [];
            }

            $ids[] = (int) $token;
        }

        return array_values(array_unique($ids));
    }

    private function sanitizeToInt(mixed $value, int $min, int $max, string $parameter, array &$issues, int $default): int
    {
        if (null === $value || '' === $value) {
            return $default;
        }

        if (!is_numeric($value)) {
            $issues[] = ['parameter' => $parameter, 'message' => \sprintf('Parameter "%s" must be an integer.', $parameter)];

            return $default;
        }

        $int = (int) $value;

        if ($int < $min) {
            $issues[] = ['parameter' => $parameter, 'message' => \sprintf('Parameter "%s" must be at least %d.', $parameter, $min)];
        }

        if ($int > $max) {
            $int = $max;
        }

        return max($min, $int);
    }

    private function sanitizeOffset(mixed $value, array &$issues): int
    {
        if (null === $value || '' === $value) {
            return 0;
        }

        if (!is_numeric($value)) {
            $issues[] = ['parameter' => 'offset', 'message' => 'Parameter "offset" must be a non-negative integer.'];

            return 0;
        }

        $int = (int) $value;

        if ($int < 0) {
            $issues[] = ['parameter' => 'offset', 'message' => 'Parameter "offset" must be a non-negative integer.'];

            return 0;
        }

        return $int;
    }

    private function sanitizeEnum(mixed $value, string $parameter, array $allowed, string $default, array &$issues): string
    {
        if (null === $value || '' === $value) {
            return $default;
        }

        if (!\is_string($value)) {
            $issues[] = ['parameter' => $parameter, 'message' => \sprintf('Parameter "%s" must be one of: %s.', $parameter, implode(', ', $allowed))];

            return $default;
        }

        $valueLower = strtolower($value);
        $allowedLower = array_map(static fn (string $option): string => strtolower($option), $allowed);
        $index = array_search($valueLower, $allowedLower, true);

        if (false === $index) {
            $issues[] = ['parameter' => $parameter, 'message' => \sprintf('Parameter "%s" must be one of: %s.', $parameter, implode(', ', $allowed))];

            return $default;
        }

        return $allowed[$index];
    }
}
