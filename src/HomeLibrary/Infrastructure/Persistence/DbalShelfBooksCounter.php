<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Application\Shelf\ShelfBooksCounter;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\UuidInterface;

// TODO temporary, will be in BooksRepository, delete also in services.yaml
final class DbalShelfBooksCounter implements ShelfBooksCounter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function countForShelf(UuidInterface $shelfId): int
    {
        return 0;
        $sql = 'SELECT COUNT(*) FROM books WHERE shelf_id = :shelf_id';

        return (int) $this->connection->fetchOne($sql, ['shelf_id' => $shelfId->toString()]);
    }
}


