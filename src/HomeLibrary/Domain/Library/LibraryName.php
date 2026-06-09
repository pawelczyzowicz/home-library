<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class LibraryName
{
    private const MAX_LENGTH = 255;

    #[ORM\Column(name: 'name', type: Types::STRING, length: self::MAX_LENGTH, unique: true)]
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Library name cannot be empty.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Library name cannot exceed %d characters.',
                self::MAX_LENGTH,
            ));
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
