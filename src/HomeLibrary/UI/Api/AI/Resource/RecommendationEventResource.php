<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\AI\Resource;

use App\HomeLibrary\Application\AI\Dto\AcceptRecommendationResponseDTO;
use App\HomeLibrary\Application\AI\Dto\RecommendationEventResponseDTO;
use App\HomeLibrary\Domain\AI\AiRecommendationEvent;

final class RecommendationEventResource
{
    public function toArray(AiRecommendationEvent $event): array
    {
        return RecommendationEventResponseDTO::fromEvent($event)->toArray();
    }

    public function toAcceptArray(AiRecommendationEvent $event): array
    {
        return AcceptRecommendationResponseDTO::fromEvent($event)->toArray();
    }
}
