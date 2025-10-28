<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Command;

use App\HomeLibrary\Domain\Book\BookSource;
use Ramsey\Uuid\UuidInterface;

final class CreateBookCommand
{
    /**
     * @param int[] $genreIds
     */
    public function __construct(
        private readonly UuidInterface $id,
        private readonly string $title,
        private readonly string $author,
        private readonly ?string $isbn,
        private readonly ?int $pageCount,
        private readonly UuidInterface $shelfId,
        private readonly array $genreIds,
        private readonly BookSource $source,
        private readonly ?int $recommendationId,
    ) {}

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): ?string
    {
        return $this->isbn;
    }

    public function pageCount(): ?int
    {
        return $this->pageCount;
    }

    public function shelfId(): UuidInterface
    {
        return $this->shelfId;
    }

    /**
     * @return int[]
     */
    public function genreIds(): array
    {
        return $this->genreIds;
    }

    public function source(): BookSource
    {
        return $this->source;
    }

    public function recommendationId(): ?int
    {
        return $this->recommendationId;
    }
}
