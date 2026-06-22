<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\Library\LibraryRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineLibraryRepositoryTest extends KernelTestCase
{
    private LibraryRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(LibraryRepository::class);

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        $connection->executeStatement('TRUNCATE TABLE libraries RESTART IDENTITY CASCADE');
    }

    #[Test]
    public function itSavesAndFindsByName(): void
    {
        $library = new Library(
            Uuid::uuid7(),
            new LibraryName('My Home Library'),
            LibraryPasswordHash::fromString('$2y$13$hashedpassword'),
        );

        $this->repository->save($library);

        $found = $this->repository->findByName('My Home Library');

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($library->id()));
        self::assertSame('My Home Library', $found->name()->value());
    }

    #[Test]
    public function itReturnsTrueForExistingName(): void
    {
        $library = new Library(
            Uuid::uuid7(),
            new LibraryName('Existing Library'),
            LibraryPasswordHash::fromString('$2y$13$hashedpassword'),
        );

        $this->repository->save($library);

        self::assertTrue($this->repository->existsByName('Existing Library'));
    }

    #[Test]
    public function itReturnsFalseForNonExistingName(): void
    {
        self::assertFalse($this->repository->existsByName('Non Existing Library'));
    }

    #[Test]
    public function itReturnsNullWhenNameNotFound(): void
    {
        self::assertNull($this->repository->findByName('Unknown'));
    }
}
