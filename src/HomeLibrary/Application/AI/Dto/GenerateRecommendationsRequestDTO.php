<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

final class GenerateRecommendationsRequestDTO
{
    /**
     * @param string[]      $inputs
     * @param string[]|null $excludeTitles
     */
    public function __construct(
        private readonly array $inputs,
        private readonly ?array $excludeTitles,
        private readonly ?string $model,
    ) {}

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
        return $this->excludeTitles ?? [];
    }

    public function model(): ?string
    {
        return $this->model;
    }
}
