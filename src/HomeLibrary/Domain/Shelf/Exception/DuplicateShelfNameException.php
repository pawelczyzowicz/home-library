<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf\Exception;

use DomainException;

class DuplicateShelfNameException extends DomainException
{
    public static function withName(string $name): self
    {
        return new self(sprintf('Shelf with name "%s" already exists.', $name));
    }
}


