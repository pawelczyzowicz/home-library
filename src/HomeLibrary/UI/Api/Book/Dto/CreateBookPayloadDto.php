<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book\Dto;

final class CreateBookPayloadDto
{
    public function __construct(
        private readonly mixed $title,
        private readonly mixed $author,
        private readonly mixed $shelfId,
        private readonly mixed $genreIds,
        private readonly mixed $isbn,
        private readonly mixed $pageCount,
        private readonly mixed $source,
        private readonly mixed $recommendationId,
    ) {}

    public function title(): mixed
    {
        return $this->title;
    }

    public function author(): mixed
    {
        return $this->author;
    }

    public function shelfId(): mixed
    {
        return $this->shelfId;
    }

    public function genreIds(): mixed
    {
        return $this->genreIds;
    }

    public function isbn(): mixed
    {
        return $this->isbn;
    }

    public function pageCount(): mixed
    {
        return $this->pageCount;
    }

    public function source(): mixed
    {
        return $this->source;
    }

    public function recommendationId(): mixed
    {
        return $this->recommendationId;
    }
}
