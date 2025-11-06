<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Exception;

final class RecommendationProviderException extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
