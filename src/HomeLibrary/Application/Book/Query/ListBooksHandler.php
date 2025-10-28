<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Query;

use App\HomeLibrary\Domain\Book\BookRepository;

final class ListBooksHandler
{
    public function __construct(private readonly BookRepository $repository) {}

    public function __invoke(ListBooksQuery $query): ListBooksResult
    {
        return $this->repository->search(
            searchTerm: $query->searchTerm(),
            shelfId: $query->shelfId(),
            genreIds: $query->genreIds(),
            limit: $query->limit(),
            offset: $query->offset(),
            sort: $query->sort(),
            order: $query->order(),
        );
    }
}
