<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Genre;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class GenreName
{
    private const MAX_LENGTH = 100;

    #[ORM\Column(name: 'name', type: Types::STRING, length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Genre name cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Genre name cannot exceed %d characters.',
                self::MAX_LENGTH,
            ));
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
