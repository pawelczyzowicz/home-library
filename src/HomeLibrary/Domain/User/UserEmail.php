<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class UserEmail
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 255;

    #[ORM\Column(name: 'email', type: Types::STRING, length: self::MAX_LENGTH)]
    private string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));
        $length = mb_strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Email must have between %d and %d characters.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
            ));
        }

        if (false === filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email format is invalid.');
        }

        $this->value = $normalized;
    }

    public static function fromString(string $email): self
    {
        return new self($email);
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
