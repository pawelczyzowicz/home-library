<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\Analytics;

use App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRate;
use App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRateCalculator;
use App\HomeLibrary\Domain\AI\RecommendationEventRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AiRecommendationSuccessRateCalculatorTest extends TestCase
{
    private RecommendationEventRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RecommendationEventRepository::class);
    }

    public function testItReturnsSuccessRateWithoutFilters(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countSuccessRate')
            ->with(null, null)
            ->willReturn([
                'total_events' => '28',
                'accepted_events' => '22',
            ]);

        $calculator = new AiRecommendationSuccessRateCalculator($this->repository);
        $result = $calculator->calculate();

        self::assertInstanceOf(AiRecommendationSuccessRate::class, $result);
        self::assertSame(28, $result->totalEvents());
        self::assertSame(22, $result->acceptedEvents());
        self::assertSame(78.6, $result->successRate());
    }

    public function testItAppliesDateFilters(): void
    {
        $from = new \DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $to = new \DateTimeImmutable('2025-01-31T23:59:59+00:00');

        $this->repository
            ->expects(self::once())
            ->method('countSuccessRate')
            ->with($from, $to)
            ->willReturn([
                'total_events' => 0,
                'accepted_events' => 0,
            ]);

        $calculator = new AiRecommendationSuccessRateCalculator($this->repository);
        $result = $calculator->calculate($from, $to);

        self::assertFalse($result->hasEvents());
        self::assertNull($result->successRate());
    }

    public function testItNormalizesAcceptedEvents(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('countSuccessRate')
            ->with(null, null)
            ->willReturn([
                'total_events' => 5,
                'accepted_events' => 7,
            ]);

        $calculator = new AiRecommendationSuccessRateCalculator($this->repository);
        $result = $calculator->calculate();

        self::assertSame(5, $result->totalEvents());
        self::assertSame(5, $result->acceptedEvents());
        self::assertSame(100.0, $result->successRate());
    }
}
