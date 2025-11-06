<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Application\AI\ReadModel\BookReadModel;
use App\HomeLibrary\Application\AI\ReadModel\BookReadRepository;
use App\HomeLibrary\Domain\Book\BookSource;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\UuidInterface;

final class DoctrineBookReadRepository implements BookReadRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function find(UuidInterface $id): ?BookReadModel
    {
        $sql = 'SELECT id, source, recommendation_id FROM books WHERE id = :id LIMIT 1';

        $row = $this->connection->fetchAssociative($sql, ['id' => $id->toString()]);

        if (false === $row) {
            return null;
        }

        $source = BookSource::from($row['source']);
        $recommendationId = null === $row['recommendation_id'] ? null : (int) $row['recommendation_id'];

        return new BookReadModel($id, $source, $recommendationId);
    }
}
