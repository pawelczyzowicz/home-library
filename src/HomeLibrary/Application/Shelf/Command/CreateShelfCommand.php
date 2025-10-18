<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Command;

use Ramsey\Uuid\UuidInterface;

class CreateShelfCommand
{
    public function __construct(
        private readonly UuidInterface $id,
        private readonly string $name,
        private readonly bool $isSystem,
    ) {
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }
}


