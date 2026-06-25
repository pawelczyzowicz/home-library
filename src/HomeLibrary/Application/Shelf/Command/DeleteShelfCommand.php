<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf\Command;

use Ramsey\Uuid\UuidInterface;

final class DeleteShelfCommand
{
    public function __construct(
        private readonly UuidInterface $id,
        private readonly UuidInterface $libraryId,
    ) {}

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function libraryId(): UuidInterface
    {
        return $this->libraryId;
    }
}
