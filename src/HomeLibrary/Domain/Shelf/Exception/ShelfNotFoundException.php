<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf\Exception;

use Ramsey\Uuid\UuidInterface;

final class ShelfNotFoundException extends 
\RuntimeException
{
    public static function withId(UuidInterface $id): self
    {
        return new self(sprintf('Shelf with id "%s" was not found.', $id->toString()));
    }
}


