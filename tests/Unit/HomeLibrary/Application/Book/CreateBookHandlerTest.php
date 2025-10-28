<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\Book;

use App\HomeLibrary\Application\Book\Command\CreateBookCommand;
use App\HomeLibrary\Application\Book\CreateBookHandler;
use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookRepository;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Genre\Exception\GenreNotFoundException;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreRepository;
use App\HomeLibrary\Domain\Genre\GenreName;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotFoundException;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateBookHandlerTest extends TestCase
{
    /** @var BookRepository&MockObject */
    private BookRepository $bookRepository;

    /** @var ShelfRepository&MockObject */
    private ShelfRepository $shelfRepository;

    /** @var GenreRepository&MockObject */
    private GenreRepository $genreRepository;

    private CreateBookHandler $handler;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepository::class);
        $this->shelfRepository = $this->createMock(ShelfRepository::class);
        $this->genreRepository = $this->createMock(GenreRepository::class);

        $this->handler = new CreateBookHandler(
            $this->bookRepository,
            $this->shelfRepository,
            $this->genreRepository,
        );
    }

    #[Test]
    public function itPersistsANewBook(): void
    {
        $bookId = Uuid::uuid7();
        $shelfId = Uuid::uuid4();

        $shelf = new Shelf($shelfId, new ShelfName('Fantasy Shelf'), ShelfFlag::userDefined());
        $genres = [
            new Genre(1, new GenreName('Fantasy')),
            new Genre(2, new GenreName('Adventure')),
        ];

        $this->shelfRepository
            ->expects(self::once())
            ->method('findById')
            ->with($shelfId)
            ->willReturn($shelf);

        $this->genreRepository
            ->expects(self::once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn($genres);

        $capturedBook = null;
        $this->bookRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Book $book) use (&$capturedBook): bool {
                $capturedBook = $book;

                return true;
            }));

        $command = new CreateBookCommand(
            id: $bookId,
            title: 'The Witcher',
            author: 'Andrzej Sapkowski',
            isbn: '9781234567890',
            pageCount: 384,
            shelfId: $shelfId,
            genreIds: [1, 2],
            source: BookSource::MANUAL,
            recommendationId: null,
        );

        $result = ($this->handler)($command);

        self::assertInstanceOf(Book::class, $result);
        self::assertSame($capturedBook, $result);
        self::assertSame('The Witcher', $result->title()->value());
        self::assertSame('Andrzej Sapkowski', $result->author()->value());
        self::assertSame('9781234567890', $result->isbn()->value());
        self::assertSame(384, $result->pageCount()->value());
        self::assertSame(BookSource::MANUAL, $result->source());
        self::assertSame($shelf, $result->shelf());
        self::assertCount(2, $result->genres());
    }

    #[Test]
    public function itThrowsWhenShelfNotFound(): void
    {
        $shelfId = Uuid::uuid4();

        $this->shelfRepository
            ->expects(self::once())
            ->method('findById')
            ->with($shelfId)
            ->willReturn(null);

        $this->genreRepository
            ->expects(self::never())
            ->method('findByIds');

        $this->bookRepository
            ->expects(self::never())
            ->method('save');

        $command = new CreateBookCommand(
            id: Uuid::uuid7(),
            title: 'The Witcher',
            author: 'Andrzej Sapkowski',
            isbn: null,
            pageCount: null,
            shelfId: $shelfId,
            genreIds: [1],
            source: BookSource::MANUAL,
            recommendationId: null,
        );

        $this->expectException(ShelfNotFoundException::class);

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenSomeGenresMissing(): void
    {
        $shelfId = Uuid::uuid4();
        $shelf = new Shelf($shelfId, new ShelfName('Fantasy Shelf'), ShelfFlag::userDefined());

        $this->shelfRepository
            ->expects(self::once())
            ->method('findById')
            ->with($shelfId)
            ->willReturn($shelf);

        $this->genreRepository
            ->expects(self::once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([
                new Genre(1, new GenreName('Fantasy')),
            ]);

        $this->bookRepository
            ->expects(self::never())
            ->method('save');

        $command = new CreateBookCommand(
            id: Uuid::uuid7(),
            title: 'The Witcher',
            author: 'Andrzej Sapkowski',
            isbn: null,
            pageCount: null,
            shelfId: $shelfId,
            genreIds: [1, 2],
            source: BookSource::MANUAL,
            recommendationId: null,
        );

        $this->expectException(GenreNotFoundException::class);
        $this->expectExceptionMessage('Genres with ids [2] were not found.');

        ($this->handler)($command);
    }
}
