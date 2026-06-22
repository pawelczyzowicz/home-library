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
        private readonly string $libraryName,
        private readonly string $libraryPassword,
        private readonly string $libraryMode,
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

    public function libraryName(): string
    {
        return $this->libraryName;
    }

    public function libraryPassword(): string
    {
        return $this->libraryPassword;
    }

    public function libraryMode(): string
    {
        return $this->libraryMode;
    }
}
