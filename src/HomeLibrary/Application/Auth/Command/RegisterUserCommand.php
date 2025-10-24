<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Auth\Command;

use Ramsey\Uuid\UuidInterface;

final class RegisterUserCommand
{
    public function __construct(
        private readonly UuidInterface $id,
        private readonly string $email,
        private readonly string $password,
        private readonly string $passwordConfirm,
    ) {}

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function passwordConfirm(): string
    {
        return $this->passwordConfirm;
    }
}
