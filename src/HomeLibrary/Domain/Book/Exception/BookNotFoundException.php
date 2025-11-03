<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book\Exception;

use Ramsey\Uuid\UuidInterface;

final class BookNotFoundException extends \RuntimeException
{
    public static function withId(UuidInterface $id): self
    {
        return new self(\sprintf('Book with id "%s" was not found.', $id->toString()));
    }
}
