<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

final class AcceptRecommendationRequestDTO
{
    public function __construct(
        private readonly string $bookId,
    ) {}

    public function bookId(): string
    {
        return $this->bookId;
    }
}
