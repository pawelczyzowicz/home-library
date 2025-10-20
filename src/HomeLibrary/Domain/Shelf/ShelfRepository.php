<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

use Ramsey\Uuid\UuidInterface;

interface ShelfRepository
{
    public function save(Shelf $shelf): void;

    public function findById(UuidInterface $id): ?Shelf;

    /**
     * @return Shelf[]
     */
    public function search(?string $searchTerm): array;

    public function countBySearchTerm(?string $searchTerm): int;

    public function remove(Shelf $shelf): void;
}


