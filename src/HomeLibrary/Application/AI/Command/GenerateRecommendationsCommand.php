<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Command;

use Ramsey\Uuid\UuidInterface;

final class GenerateRecommendationsCommand
{
    /**
     * @param string[] $inputs
     */
    public function __construct(
        private readonly ?UuidInterface $userId,
        private readonly array $inputs,
        private readonly ?string $model,
    ) {}

    public function userId(): ?UuidInterface
    {
        return $this->userId;
    }

    /**
     * @return string[]
     */
    public function inputs(): array
    {
        return $this->inputs;
    }

    public function model(): ?string
    {
        return $this->model;
    }
}
