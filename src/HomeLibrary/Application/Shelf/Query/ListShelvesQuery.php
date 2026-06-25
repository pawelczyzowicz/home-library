<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Query;

use Ramsey\Uuid\UuidInterface;

class ListShelvesQuery
{
    public function __construct(
        private readonly UuidInterface $libraryId,
        private readonly ?string $searchTerm,
        private readonly ?bool $systemOnly,
    ) {}

    public function libraryId(): UuidInterface
    {
        return $this->libraryId;
    }

    public function searchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function systemOnly(): ?bool
    {
        return $this->systemOnly;
    }
}
