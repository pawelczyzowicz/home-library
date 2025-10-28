<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookRepository;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreName;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineBookRepositoryTest extends KernelTestCase
{
    private BookRepository $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(BookRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE book_genre RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE books RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE shelves RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE genres RESTART IDENTITY CASCADE');

        $this->seedGenres();
        $this->seedShelves();
        $this->seedBooks();
    }

    public function testSearchWithoutFiltersReturnsPagedResults(): void
    {
        $result = $this->repository->search(
            searchTerm: null,
            shelfId: null,
            genreIds: [],
            limit: 2,
            offset: 0,
            sort: 'createdAt',
            order: 'desc',
        );

        self::assertSame(2, $result->limit());
        self::assertSame(0, $result->offset());
        self::assertSame(3, $result->total());
        self::assertCount(2, $result->books());
    }

    public function testSearchByShelfAndGenreFilters(): void
    {
        $shelfId = $this->findShelfId('Półka A');

        $result = $this->repository->search(
            searchTerm: null,
            shelfId: $shelfId,
            genreIds: [1],
            limit: 10,
            offset: 0,
            sort: 'title',
            order: 'asc',
        );

        self::assertSame(1, $result->total());
        self::assertCount(1, $result->books());
        self::assertSame('Alpha', $result->books()[0]->title()->value());
    }

    public function testSearchByQueryMatchesTitleOrAuthor(): void
    {
        $result = $this->repository->search(
            searchTerm: 'gamma author',
            shelfId: null,
            genreIds: [],
            limit: 10,
            offset: 0,
            sort: 'title',
            order: 'asc',
        );

        self::assertSame(1, $result->total());
        self::assertSame('Gamma', $result->books()[0]->title()->value());
    }

    private function seedGenres(): void
    {
        $genres = [
            new Genre(1, new GenreName('kryminał')),
            new Genre(2, new GenreName('fantasy')),
            new Genre(3, new GenreName('sensacja')),
        ];

        foreach ($genres as $genre) {
            $this->entityManager->persist($genre);
        }

        $this->entityManager->flush();
    }

    private function seedShelves(): void
    {
        $shelves = [
            new Shelf(Uuid::uuid7(), new ShelfName('Półka A'), ShelfFlag::userDefined()),
            new Shelf(Uuid::uuid7(), new ShelfName('Półka B'), ShelfFlag::userDefined()),
        ];

        foreach ($shelves as $shelf) {
            $this->entityManager->persist($shelf);
        }

        $this->entityManager->flush();
    }

    private function seedBooks(): void
    {
        $shelves = $this->entityManager->getRepository(Shelf::class)->findAll();
        \assert([] !== $shelves);

        $genres = $this->entityManager->getRepository(Genre::class)->findAll();

        $bookA = new Book(
            Uuid::uuid7(),
            new BookTitle('Alpha'),
            new BookAuthor('Author A'),
            new BookIsbn(null),
            new BookPageCount(123),
            BookSource::MANUAL,
            null,
            $shelves[0],
            [$genres[0]],
        );

        $bookB = new Book(
            Uuid::uuid7(),
            new BookTitle('Beta'),
            new BookAuthor('Author B'),
            new BookIsbn('1234567890'),
            new BookPageCount(null),
            BookSource::MANUAL,
            null,
            $shelves[1],
            [$genres[1]],
        );

        $bookC = new Book(
            Uuid::uuid7(),
            new BookTitle('Gamma'),
            new BookAuthor('Gamma Author'),
            new BookIsbn(null),
            new BookPageCount(200),
            BookSource::MANUAL,
            null,
            $shelves[1],
            [$genres[2]],
        );

        foreach ([$bookA, $bookB, $bookC] as $book) {
            $this->entityManager->persist($book);
        }

        $this->entityManager->flush();
    }

    private function findShelfId(string $name): \Ramsey\Uuid\UuidInterface
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Shelf::class, 's')
            ->andWhere('LOWER(s.name.value) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1);

        $shelf = $qb->getQuery()->getOneOrNullResult();
        \assert($shelf instanceof Shelf);

        return $shelf->id();
    }
}
