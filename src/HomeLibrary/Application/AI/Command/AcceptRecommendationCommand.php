<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Command;

use Ramsey\Uuid\UuidInterface;

final class AcceptRecommendationCommand
{
    public function __construct(
        private readonly int $eventId,
        private readonly UuidInterface $bookId,
        private readonly ?string $idempotencyKey,
        private readonly ?UuidInterface $userId,
    ) {}

    public function eventId(): int
    {
        return $this->eventId;
    }

    public function bookId(): UuidInterface
    {
        return $this->bookId;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function userId(): ?UuidInterface
    {
        return $this->userId;
    }
}
