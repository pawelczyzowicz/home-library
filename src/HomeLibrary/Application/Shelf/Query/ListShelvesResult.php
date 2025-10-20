<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Query;

use App\HomeLibrary\Domain\Shelf\Shelf;

class ListShelvesResult
{
    /** @param Shelf[] $shelves */
    public function __construct(private readonly array $shelves, private readonly int $total) {}

    /** @return Shelf[] */
    public function shelves(): array
    {
        return $this->shelves;
    }

    public function total(): int
    {
        return $this->total;
    }
}
