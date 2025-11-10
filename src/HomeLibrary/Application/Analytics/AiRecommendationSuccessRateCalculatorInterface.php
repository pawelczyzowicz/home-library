<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Analytics;

interface AiRecommendationSuccessRateCalculatorInterface
{
    public function calculate(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): AiRecommendationSuccessRate;
}
