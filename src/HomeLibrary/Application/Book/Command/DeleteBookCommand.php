<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book\Command;

use Ramsey\Uuid\UuidInterface;

final class DeleteBookCommand
{
    public function __construct(private readonly UuidInterface $id) {}

    public function id(): UuidInterface
    {
        return $this->id;
    }
}
