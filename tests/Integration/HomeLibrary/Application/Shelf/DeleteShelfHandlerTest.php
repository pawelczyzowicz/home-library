<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\Application\Shelf;

use App\HomeLibrary\Application\Shelf\Command\DeleteShelfCommand;
use App\HomeLibrary\Application\Shelf\DeleteShelfHandler;
use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotEmptyException;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeleteShelfHandlerTest extends KernelTestCase
{
    private DeleteShelfHandler $handler;

    private EntityManagerInterface $entityManager;

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->handler = $container->get(DeleteShelfHandler::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get(Connection::class);

        $this->truncateTables();
    }

    #[Test]
    public function itThrowsWhenShelfContainsBooks(): void
    {
        $library = new Library(
            Uuid::uuid7(),
            new LibraryName('Test Library'),
            LibraryPasswordHash::fromString('$2y$13$testhashedpassword000000000000000000000000000000000'),
        );
        $this->entityManager->persist($library);

        $shelfId = Uuid::uuid7();
        $shelf = new Shelf($shelfId, new ShelfName('To Remove'), ShelfFlag::userDefined(), $library);

        $this->entityManager->persist($shelf);

        $book = new Book(
            Uuid::uuid7(),
            new BookTitle('Sample Book'),
            new BookAuthor('Test Author'),
            new BookIsbn('9781234567897'),
            new BookPageCount(320),
            BookSource::MANUAL,
            null,
            $shelf,
            $library,
        );

        $this->entityManager->persist($book);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->expectException(ShelfNotEmptyException::class);
        $this->expectExceptionMessageMatches('/cannot be removed/');

        ($this->handler)(new DeleteShelfCommand($shelfId, $library->id()));
    }

    private function truncateTables(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE book_genre RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE books RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE shelves RESTART IDENTITY CASCADE');
        $this->connection->executeStatement('TRUNCATE TABLE libraries RESTART IDENTITY CASCADE');
    }
}
