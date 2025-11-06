<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Exception;

use Ramsey\Uuid\UuidInterface;

final class RecommendationConflictException extends \RuntimeException
{
    public static function forBookAlreadyAccepted(UuidInterface $bookId): self
    {
        return new self(\sprintf('Book "%s" is already accepted for this recommendation event.', $bookId->toString()));
    }

    public static function forIdempotencyKey(string $key): self
    {
        return new self(\sprintf('Idempotency key "%s" is already used for this recommendation event.', $key));
    }
}
