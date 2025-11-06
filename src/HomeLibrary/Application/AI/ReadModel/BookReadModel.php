<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\ReadModel;

use App\HomeLibrary\Domain\Book\BookSource;
use Ramsey\Uuid\UuidInterface;

final class BookReadModel
{
    public function __construct(
        private readonly UuidInterface $id,
        private readonly BookSource $source,
        private readonly ?int $recommendationId,
    ) {}

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function source(): BookSource
    {
        return $this->source;
    }

    public function recommendationId(): ?int
    {
        return $this->recommendationId;
    }
}
