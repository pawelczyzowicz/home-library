<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book\Resource;

use App\HomeLibrary\Domain\Genre\Genre;

final class GenreResource
{
    public function toArray(Genre $genre): array
    {
        return [
            'id' => $genre->id(),
            'name' => $genre->name()->value(),
        ];
    }
}
