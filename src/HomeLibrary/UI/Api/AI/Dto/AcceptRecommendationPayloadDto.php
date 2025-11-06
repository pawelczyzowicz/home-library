<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\AI\Dto;

final class AcceptRecommendationPayloadDto
{
    public function __construct(private readonly mixed $bookId) {}

    public function bookId(): mixed
    {
        return $this->bookId;
    }
}
