<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Book\ValueObject;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class BookIsbn
{
    private const PATTERN = '/^\d{10}(\d{3})?$/';

    #[ORM\Column(name: 'isbn', type: Types::STRING, length: 13, nullable: true)]
    private ?string $value = null;

    public function __construct(?string $value)
    {
        if (null === $value) {
            $this->value = null;

            return;
        }

        $normalized = preg_replace('/[^\d]/', '', $value);

        if (null === $normalized || 1 !== preg_match(self::PATTERN, $normalized)) {
            throw new \InvalidArgumentException('Book ISBN must consist of 10 or 13 digits.');
        }

        $this->value = $normalized;
    }

    public function value(): ?string
    {
        return $this->value;
    }
}
