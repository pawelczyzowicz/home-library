<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Analytics;

final class AiRecommendationSuccessRate
{
    private const PRECISION = 1;

    public function __construct(
        private readonly int $totalEvents,
        private int $acceptedEvents,
    ) {
        if ($this->totalEvents < 0) {
            throw new \InvalidArgumentException('Total events cannot be negative.');
        }

        if ($this->acceptedEvents < 0) {
            throw new \InvalidArgumentException('Accepted events cannot be negative.');
        }

        if ($this->acceptedEvents > $this->totalEvents) {
            $this->acceptedEvents = $this->totalEvents;
        }
    }

    public function totalEvents(): int
    {
        return $this->totalEvents;
    }

    public function acceptedEvents(): int
    {
        return $this->acceptedEvents;
    }

    public function hasEvents(): bool
    {
        return 0 !== $this->totalEvents;
    }

    public function successRate(): ?float
    {
        if (!$this->hasEvents()) {
            return null;
        }

        $ratio = ($this->acceptedEvents / $this->totalEvents) * 100;

        return round($ratio, self::PRECISION);
    }
}
