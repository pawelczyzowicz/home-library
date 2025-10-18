<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

interface ShelfRepository
{
    public function save(Shelf $shelf): void;
}


