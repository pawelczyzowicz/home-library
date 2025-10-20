<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Query;

class ListShelvesQuery
{
    public function __construct(private readonly ?string $searchTerm) {}

    public function searchTerm(): ?string
    {
        return $this->searchTerm;
    }
}
