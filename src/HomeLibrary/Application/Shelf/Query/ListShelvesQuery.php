<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Query;

class ListShelvesQuery
{
    public function __construct(
        private readonly ?string $searchTerm,
        private readonly ?bool $systemOnly,
    ) {}

    public function searchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function systemOnly(): ?bool
    {
        return $this->systemOnly;
    }
}
