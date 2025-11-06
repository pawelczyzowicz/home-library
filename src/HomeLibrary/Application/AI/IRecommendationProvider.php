<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI;

use App\HomeLibrary\Domain\AI\RecommendationProposal;

interface IRecommendationProvider
{
    /**
     * @param string[] $inputs
     *
     * @return RecommendationProposal[]
     */
    public function generate(array $inputs): array;
}
