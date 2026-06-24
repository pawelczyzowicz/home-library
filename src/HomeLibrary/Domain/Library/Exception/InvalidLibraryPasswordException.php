<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library\Exception;

final class InvalidLibraryPasswordException extends \DomainException
{
    public static function forName(string $name): self
    {
        return new self(\sprintf('Invalid password for library "%s".', $name));
    }
}
