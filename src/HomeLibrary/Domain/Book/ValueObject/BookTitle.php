<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class BookTitle
{
    private const MIN_LENGTH = 1;
    private const MAX_LENGTH = 255;

    #[ORM\Column(name: 'title', type: Types::STRING, length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);

        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Book title must have between %d and %d characters.',
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
