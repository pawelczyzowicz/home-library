<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Command;

use Ramsey\Uuid\UuidInterface;

final class GenerateRecommendationsCommand
{
    /**
     * @param string[] $inputs
     * @param string[] $excludeTitles
     */
    public function __construct(
        private readonly ?UuidInterface $userId,
        private readonly array $inputs,
        private readonly array $excludeTitles,
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

    /**
     * @return string[]
     */
    public function excludeTitles(): array
    {
        return $this->excludeTitles;
    }

    public function model(): ?string
    {
        return $this->model;
    }
}
