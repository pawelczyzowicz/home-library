<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library;

use Ramsey\Uuid\UuidInterface;

interface LibraryRepository
{
    public function save(Library $library): void;

    public function findById(UuidInterface $id): ?Library;

    public function existsByName(string $name): bool;

    public function findByName(string $name): ?Library;
}
