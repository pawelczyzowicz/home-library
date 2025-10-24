<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class UserPasswordHash
{
    #[ORM\Column(name: 'password_hash', type: Types::TEXT)]
    private string $value;

    public function __construct(string $value)
    {
        if ('' === trim($value)) {
            throw new \InvalidArgumentException('Password hash cannot be empty.');
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
