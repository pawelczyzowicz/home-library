<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf\Exception;

use Ramsey\Uuid\UuidInterface;

final class ShelfIsSystemException extends \RuntimeException
{
    public static function withId(UuidInterface $id): self
    {
        return new self(\sprintf('Shelf with id "%s" is marked as system and cannot be removed.', $id->toString()));
    }
}
