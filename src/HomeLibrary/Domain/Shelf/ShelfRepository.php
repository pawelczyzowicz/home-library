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
    public function search(?string $searchTerm, ?bool $systemOnly = null): array;

    public function countBySearchTerm(?string $searchTerm, ?bool $systemOnly = null): int;

    public function remove(Shelf $shelf): void;
}
