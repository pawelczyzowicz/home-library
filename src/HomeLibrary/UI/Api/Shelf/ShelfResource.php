<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Shelf;

use App\HomeLibrary\Domain\Shelf\Shelf;

final class ShelfResource
{
    /**
     * @return array{ id: string, name: string, isSystem: bool, createdAt: string, updatedAt: string }
     */
    public function toArray(Shelf $shelf): array
    {
        return [
            'id' => (string) $shelf->id(),
            'name' => $shelf->name()->value(),
            'isSystem' => $shelf->systemFlag()->value(),
            'createdAt' => $shelf->createdAt()->format(DATE_ATOM),
            'updatedAt' => $shelf->updatedAt()->format(DATE_ATOM),
        ];
    }
}


