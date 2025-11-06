<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Exception;

use Ramsey\Uuid\UuidInterface;

final class RecommendationEventNotFoundException extends \RuntimeException
{
    public static function forEvent(int $eventId): self
    {
        return new self(\sprintf('Recommendation event "%d" was not found.', $eventId));
    }

    public static function forBook(UuidInterface $bookId): self
    {
        return new self(\sprintf('Book "%s" does not belong to the recommendation event.', $bookId->toString()));
    }
}
