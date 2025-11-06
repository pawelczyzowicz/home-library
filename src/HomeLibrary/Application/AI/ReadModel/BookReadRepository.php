<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\ReadModel;

use Ramsey\Uuid\UuidInterface;

interface BookReadRepository
{
    public function find(UuidInterface $id): ?BookReadModel;
}
