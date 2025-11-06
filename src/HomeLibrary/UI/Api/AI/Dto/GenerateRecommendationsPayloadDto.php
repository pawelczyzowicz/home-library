<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\AI\Dto;

final class GenerateRecommendationsPayloadDto
{
    public function __construct(
        private readonly mixed $inputs,
        private readonly mixed $excludeTitles,
        private readonly mixed $model,
    ) {}

    public function inputs(): mixed
    {
        return $this->inputs;
    }

    public function excludeTitles(): mixed
    {
        return $this->excludeTitles;
    }

    public function model(): mixed
    {
        return $this->model;
    }
}
