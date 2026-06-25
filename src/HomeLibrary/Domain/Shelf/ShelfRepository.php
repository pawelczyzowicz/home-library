<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

use Ramsey\Uuid\UuidInterface;

interface ShelfRepository
{
    public function save(Shelf $shelf): void;

    public function findById(UuidInterface $id, ?UuidInterface $libraryId = null): ?Shelf;

    /**
     * @return Shelf[]
     */
    public function search(UuidInterface $libraryId, ?string $searchTerm, ?bool $systemOnly = null): array;

    public function countBySearchTerm(UuidInterface $libraryId, ?string $searchTerm, ?bool $systemOnly = null): int;

    public function remove(Shelf $shelf): void;
}
