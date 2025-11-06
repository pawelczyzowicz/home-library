<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI\Exception;

use Ramsey\Uuid\UuidInterface;

final class RecommendationAlreadyAcceptedException extends \RuntimeException
{
    public static function forBook(UuidInterface $bookId): self
    {
        return new self(\sprintf('Book "%s" is already accepted for this recommendation event.', $bookId->toString()));
    }
}
