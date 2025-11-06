<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI;

use Ramsey\Uuid\UuidInterface;

interface RecommendationEventRepository
{
    public function save(AiRecommendationEvent $event): void;

    public function findById(int $id): ?AiRecommendationEvent;

    public function findOwnedBy(int $id, ?UuidInterface $userId): ?AiRecommendationEvent;
}
