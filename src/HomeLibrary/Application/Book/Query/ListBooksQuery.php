<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Query;

use Ramsey\Uuid\UuidInterface;

final class ListBooksQuery
{
    /**
     * @param int[] $genreIds
     */
    public function __construct(
        private readonly ?string $searchTerm,
        private readonly ?UuidInterface $shelfId,
        private readonly array $genreIds,
        private readonly int $limit,
        private readonly int $offset,
        private readonly string $sort,
        private readonly string $order,
    ) {}

    public function searchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function shelfId(): ?UuidInterface
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

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function order(): string
    {
        return $this->order;
    }
}
