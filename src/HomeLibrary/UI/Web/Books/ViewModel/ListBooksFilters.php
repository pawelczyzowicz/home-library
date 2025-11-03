<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books\ViewModel;

use Symfony\Component\HttpFoundation\Request;

final class ListBooksFilters
{
    /**
     * @param int[] $genreIds
     */
    public function __construct(
        private readonly ?string $q,
        private readonly ?string $qDisplay,
        private readonly ?string $shelfId,
        private readonly array $genreIds,
        private readonly string $sort,
        private readonly string $order,
        private readonly int $limit,
        private readonly int $offset,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $rawQuery = $request->query->get('q');
        $qDisplay = null;
        $q = null;

        if (\is_string($rawQuery)) {
            $normalized = trim($rawQuery);
            if ('' !== $normalized) {
                $truncated = mb_substr($normalized, 0, 255);
                $q = $truncated;
                $qDisplay = $truncated;
            }
        }

        $rawShelfId = $request->query->get('shelfId');
        $shelfId = \is_string($rawShelfId) && '' !== trim($rawShelfId) ? trim($rawShelfId) : null;

        $genreIds = self::extractGenreIds($request);

        $sort = self::normalizeSort($request->query->get('sort'));
        $order = self::normalizeOrder($request->query->get('order'));
        $limit = self::normalizeLimit($request->query->get('limit'));
        $offset = self::normalizeOffset($request->query->get('offset'));

        return new self(
            q: $q,
            qDisplay: $qDisplay,
            shelfId: $shelfId,
            genreIds: $genreIds,
            sort: $sort,
            order: $order,
            limit: $limit,
            offset: $offset,
        );
    }

    public function q(): ?string
    {
        return $this->q;
    }

    public function qDisplay(): ?string
    {
        return $this->qDisplay;
    }

    public function shelfId(): ?string
    {
        return $this->shelfId;
    }

    /**
     * @return int[]
     */
    public function genreIds(): array
    {
        return $this->genreIds;
    }

    public function hasGenre(int $genreId): bool
    {
        return \in_array($genreId, $this->genreIds, true);
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function order(): string
    {
        return $this->order;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function withOffset(int $offset): self
    {
        return new self(
            $this->q,
            $this->qDisplay,
            $this->shelfId,
            $this->genreIds,
            $this->sort,
            $this->order,
            $this->limit,
            $offset,
        );
    }

    public function withLimit(int $limit): self
    {
        return new self(
            $this->q,
            $this->qDisplay,
            $this->shelfId,
            $this->genreIds,
            $this->sort,
            $this->order,
            $limit,
            0,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toApiQuery(): array
    {
        $query = [
            'limit' => (string) $this->limit,
            'offset' => (string) $this->offset,
            'sort' => $this->sort,
            'order' => $this->order,
        ];

        if (null !== $this->q) {
            $query['q'] = $this->q;
        }

        if (null !== $this->shelfId) {
            $query['shelfId'] = $this->shelfId;
        }

        if ([] !== $this->genreIds) {
            $query['genreIds'] = implode(',', $this->genreIds);
        }

        return $query;
    }

    /**
     * @return int[]
     */
    private static function extractGenreIds(Request $request): array
    {
        $genreIds = [];

        $rawQueryAll = $request->query->all('genreIds');

        if ([] !== $rawQueryAll) {
            foreach ($rawQueryAll as $value) {
                if (\is_scalar($value) && ctype_digit((string) $value)) {
                    $genreIds[] = (int) $value;
                }
            }
        } else {
            $raw = $request->query->get('genreIds');
            if (\is_string($raw)) {
                $tokens = array_filter(array_map('trim', explode(',', $raw)), static fn (string $token): bool => '' !== $token);
                foreach ($tokens as $token) {
                    if (ctype_digit($token)) {
                        $genreIds[] = (int) $token;
                    }
                }
            }
        }

        return array_values(array_unique($genreIds));
    }

    private static function normalizeSort(mixed $raw): string
    {
        $allowed = ['title', 'author', 'createdAt'];
        if (\is_string($raw)) {
            foreach ($allowed as $option) {
                if ($option === $raw) {
                    return $option;
                }
            }
        }

        return 'createdAt';
    }

    private static function normalizeOrder(mixed $raw): string
    {
        $allowed = ['asc', 'desc'];
        if (\is_string($raw)) {
            $normalized = strtolower($raw);
            foreach ($allowed as $option) {
                if ($option === $normalized) {
                    return $option;
                }
            }
        }

        return 'desc';
    }

    private static function normalizeLimit(mixed $raw): int
    {
        $allowed = [10, 20, 50];
        if (is_numeric($raw)) {
            $int = (int) $raw;
            if (\in_array($int, $allowed, true)) {
                return $int;
            }
        }

        return 20;
    }

    private static function normalizeOffset(mixed $raw): int
    {
        if (is_numeric($raw)) {
            $int = (int) $raw;

            return max(0, $int);
        }

        return 0;
    }
}
