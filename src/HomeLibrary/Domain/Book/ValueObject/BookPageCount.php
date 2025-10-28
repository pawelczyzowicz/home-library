<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class BookPageCount
{
    private const MIN_VALUE = 1;
    private const MAX_VALUE = 50000;

    #[ORM\Column(name: 'page_count', type: Types::INTEGER, nullable: true)]
    private ?int $value = null;

    public function __construct(?int $value)
    {
        if (null === $value) {
            $this->value = null;

            return;
        }

        if ($value < self::MIN_VALUE || $value > self::MAX_VALUE) {
            throw new \InvalidArgumentException(\sprintf(
                'Book page count must be between %d and %d.',
                self::MIN_VALUE,
                self::MAX_VALUE,
            ));
        }

        $this->value = $value;
    }

    public function value(): ?int
    {
        return $this->value;
    }
}
