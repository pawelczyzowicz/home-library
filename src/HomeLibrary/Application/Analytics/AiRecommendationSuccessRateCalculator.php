<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Analytics;

use App\HomeLibrary\Domain\AI\RecommendationEventRepository;

final class AiRecommendationSuccessRateCalculator implements AiRecommendationSuccessRateCalculatorInterface
{
    public function __construct(
        private readonly RecommendationEventRepository $eventRepository,
    ) {}

    public function calculate(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): AiRecommendationSuccessRate
    {
        $row = $this->eventRepository->countSuccessRate($from, $to);

        $total = (int) $row['total_events'];
        $accepted = (int) $row['accepted_events'];

        return new AiRecommendationSuccessRate($total, $accepted);
    }
}
