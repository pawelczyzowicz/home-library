<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class ShelfName
{
    private const MIN_LENGTH = 1;
    private const MAX_LENGTH = 50;

    #[ORM\Column(name: 'name', type: Types::STRING, length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Shelf name must have between %d and %d characters.',
                self::MIN_LENGTH,
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
