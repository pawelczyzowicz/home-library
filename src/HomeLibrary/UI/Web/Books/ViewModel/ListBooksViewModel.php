<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books\ViewModel;

/** @SuppressWarnings("PHPMD.TooManyPublicMethods") */
final class ListBooksViewModel
{
    /**
     * @param array<int, array<string, mixed>> $books
     * @param array<int, array<string, mixed>> $shelves
     * @param array<int, array<string, mixed>> $genres
     */
    public function __construct(
        private readonly ListBooksFilters $filters,
        private readonly ListBooksMetaViewModel $meta,
        private readonly array $books,
        private readonly array $shelves,
        private readonly array $genres,
        private readonly ?ProblemDetailsViewModel $problem = null,
    ) {}

    public function filters(): ListBooksFilters
    {
        return $this->filters;
    }

    public function meta(): ListBooksMetaViewModel
    {
        return $this->meta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function books(): array
    {
        return $this->books;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shelves(): array
    {
        return $this->shelves;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function genres(): array
    {
        return $this->genres;
    }

    public function problem(): ?ProblemDetailsViewModel
    {
        return $this->problem;
    }

    public function hasProblem(): bool
    {
        return null !== $this->problem;
    }

    public function showIsbn(): bool
    {
        foreach ($this->books as $book) {
            if (\array_key_exists('isbn', $book) && null !== $book['isbn'] && '' !== $book['isbn']) {
                return true;
            }
        }

        return false;
    }

    public function showPages(): bool
    {
        foreach ($this->books as $book) {
            if (\array_key_exists('pageCount', $book) && null !== $book['pageCount']) {
                return true;
            }
        }

        return false;
    }

    public function shownCount(): int
    {
        return $this->meta->shownCount(\count($this->books));
    }
}
