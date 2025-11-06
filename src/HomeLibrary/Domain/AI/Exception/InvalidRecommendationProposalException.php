<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI\Exception;

final class InvalidRecommendationProposalException extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
