<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library\Exception;

final class LibraryAlreadyExistsException extends \DomainException
{
    public static function forName(string $name): self
    {
        return new self(\sprintf('Library with name "%s" already exists.', $name));
    }
}
