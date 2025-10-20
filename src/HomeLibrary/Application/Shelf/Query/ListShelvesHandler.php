<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Query;

use App\HomeLibrary\Domain\Shelf\ShelfRepository;

class ListShelvesHandler
{
    public function __construct(private readonly ShelfRepository $repository) {}

    public function __invoke(ListShelvesQuery $query): ListShelvesResult
    {
        $shelves = $this->repository->search($query->searchTerm());

        return new ListShelvesResult(
            shelves: $shelves,
            total: $this->repository->countBySearchTerm($query->searchTerm()),
        );
    }
}
