<?php

declare(strict_types=1);

namespace App\HomeLibrary\Infrastructure\AI;

use App\HomeLibrary\Application\AI\Idempotency\IdempotencyRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\UuidInterface;

final class DoctrineIdempotencyRepository implements IdempotencyRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function hasKey(int $eventId, string $idempotencyKey): bool
    {
        $sql = 'SELECT 1 FROM ai_recommendation_accept_requests WHERE event_id = :event_id AND idempotency_key = :key LIMIT 1';

        return false !== $this->connection->fetchOne($sql, [
            'event_id' => $eventId,
            'key' => $idempotencyKey,
        ]);
    }

    public function hasBook(int $eventId, UuidInterface $bookId): bool
    {
        $sql = 'SELECT 1 FROM ai_recommendation_accept_requests WHERE event_id = :event_id AND book_id = :book_id LIMIT 1';

        return false !== $this->connection->fetchOne($sql, [
            'event_id' => $eventId,
            'book_id' => $bookId->toString(),
        ]);
    }

    public function record(int $eventId, string $idempotencyKey, UuidInterface $bookId): void
    {
        $this->connection->insert('ai_recommendation_accept_requests', [
            'event_id' => $eventId,
            'book_id' => $bookId->toString(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
