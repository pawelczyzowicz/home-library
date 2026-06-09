<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library;

interface LibraryRepository
{
    public function save(Library $library): void;

    public function existsByName(string $name): bool;

    public function findByName(string $name): ?Library;
}
