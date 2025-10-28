<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book\Resource;

use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\UI\Api\Shelf\ShelfResource;

final class BookResource
{
    public function __construct(
        private readonly ShelfResource $shelfResource,
        private readonly GenreResource $genreResource,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     author: string,
     *     isbn: string|null,
     *     pageCount: int|null,
     *     source: string,
     *     recommendationId: int|null,
     *     shelf: array,
     *     genres: array,
     *     createdAt: string,
     *     updatedAt: string,
     * }
     */
    public function toArray(Book $book): array
    {
        return [
            'id' => (string) $book->id(),
            'title' => $book->title()->value(),
            'author' => $book->author()->value(),
            'isbn' => $book->isbn()->value(),
            'pageCount' => $book->pageCount()->value(),
            'source' => $book->source()->value,
            'recommendationId' => $book->recommendationId(),
            'shelf' => $this->shelfResource->toArray($book->shelf()),
            'genres' => array_map(
                fn ($genre) => $this->genreResource->toArray($genre),
                $book->genres()->toArray(),
            ),
            'createdAt' => $book->createdAt()->format(\DATE_ATOM),
            'updatedAt' => $book->updatedAt()->format(\DATE_ATOM),
        ];
    }
}
