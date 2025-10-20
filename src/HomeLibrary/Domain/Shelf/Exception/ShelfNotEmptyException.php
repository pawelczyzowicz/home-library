<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf\Exception;

use Ramsey\Uuid\UuidInterface;

final class ShelfNotEmptyException extends \RuntimeException
{
    public static function withId(UuidInterface $id): self
    {
        return new self(sprintf('Shelf with id "%s" contains books and cannot be removed.', $id->toString()));
    }
}


