<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book;

use App\HomeLibrary\Application\Book\Query\ListBooksResult;
use Ramsey\Uuid\UuidInterface;

interface BookRepository
{
    /**
     * @param int[] $genreIds
     */
    public function search(
        ?string $searchTerm,
        ?UuidInterface $shelfId,
        array $genreIds,
        int $limit,
        int $offset,
        string $sort,
        string $order,
    ): ListBooksResult;
}
