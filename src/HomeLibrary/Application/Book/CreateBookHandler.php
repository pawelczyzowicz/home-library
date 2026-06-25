<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book;

use App\HomeLibrary\Application\Book\Command\CreateBookCommand;
use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookRepository;
use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Genre\Exception\GenreNotFoundException;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreRepository;
use App\HomeLibrary\Domain\Library\LibraryRepository;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotFoundException;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;

final class CreateBookHandler
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly GenreRepository $genreRepository,
        private readonly LibraryRepository $libraryRepository,
    ) {}

    public function __invoke(CreateBookCommand $command): Book
    {
        $library = $this->libraryRepository->findById($command->libraryId());

        if (null === $library) {
            throw new \RuntimeException(\sprintf('Library "%s" not found.', $command->libraryId()->toString()));
        }

        $shelf = $this->shelfRepository->findById($command->shelfId(), $command->libraryId());

        if (null === $shelf) {
            throw ShelfNotFoundException::withId($command->shelfId());
        }

        $genres = $this->genreRepository->findByIds($command->genreIds());
        $this->assertAllGenresFound($command->genreIds(), $genres);

        $book = new Book(
            $command->id(),
            new BookTitle($command->title()),
            new BookAuthor($command->author()),
            new BookIsbn($command->isbn()),
            new BookPageCount($command->pageCount()),
            $command->source(),
            $command->recommendationId(),
            $shelf,
            $library,
            $genres,
        );

        $this->bookRepository->save($book);

        return $book;
    }

    /**
     * @param int[]   $expectedIds
     * @param Genre[] $genres
     */
    private function assertAllGenresFound(array $expectedIds, array $genres): void
    {
        $foundIds = array_map(static fn (Genre $genre): int => $genre->id(), $genres);
        $missingIds = array_values(array_diff($expectedIds, $foundIds));

        if ([] !== $missingIds) {
            throw GenreNotFoundException::withIds($missingIds);
        }
    }
}
