<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf;

use Ramsey\Uuid\UuidInterface;

interface ShelfBooksCounter
{
    public function countForShelf(UuidInterface $shelfId): int;
}
