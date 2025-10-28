<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Query;

use App\HomeLibrary\Domain\Book\Book;

final class ListBooksResult
{
    /**
     * @param Book[] $books
     */
    public function __construct(
        private readonly array $books,
        private readonly int $total,
        private readonly int $limit,
        private readonly int $offset,
    ) {}

    /**
     * @return Book[]
     */
    public function books(): array
    {
        return $this->books;
    }

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
}
