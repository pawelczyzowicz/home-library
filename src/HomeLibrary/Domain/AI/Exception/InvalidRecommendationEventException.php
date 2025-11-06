<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI\Exception;

final class InvalidRecommendationEventException extends \InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
