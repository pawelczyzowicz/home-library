<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

interface UserRepository
{
    public function save(User $user): void;

    public function existsByEmail(string $emailLower): bool;

    public function findByEmail(string $emailLower): ?User;
}
