<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

final class GenerateRecommendationsRequestDTO
{
    /**
     * @param string[] $inputs
     */
    public function __construct(
        private readonly array $inputs,
        private readonly ?string $model,
    ) {}

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
