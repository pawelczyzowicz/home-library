<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Idempotency;

use Ramsey\Uuid\UuidInterface;

interface IdempotencyRepository
{
    public function hasKey(int $eventId, string $idempotencyKey): bool;

    public function hasBook(int $eventId, UuidInterface $bookId): bool;

    public function record(int $eventId, string $idempotencyKey, UuidInterface $bookId): void;
}
