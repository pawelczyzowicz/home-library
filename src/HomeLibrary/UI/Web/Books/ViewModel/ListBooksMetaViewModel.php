<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books\ViewModel;

/** @SuppressWarnings("PHPMD.TooManyPublicMethods") */
final class ListBooksMetaViewModel
{
    public function __construct(
        private readonly int $total,
        private readonly int $limit,
        private readonly int $offset,
    ) {}

    public function total(): int
    {
        return $this->total;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function shownCount(int $resultsCount): int
    {
        return min($this->total, $this->offset + $resultsCount);
    }

    public function currentPage(): int
    {
        if (0 === $this->limit) {
            return 1;
        }

        return intdiv($this->offset, max(1, $this->limit)) + 1;
    }

    public function totalPages(): int
    {
        if (0 === $this->limit) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->limit));
    }

    public function hasPrevious(): bool
    {
        return $this->offset > 0;
    }

    public function hasNext(): bool
    {
        return $this->offset + $this->limit < $this->total;
    }

    public function previousOffset(): int
    {
        return max(0, $this->offset - $this->limit);
    }

    public function nextOffset(): int
    {
        return $this->offset + $this->limit;
    }
}
