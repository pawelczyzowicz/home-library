<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\UI\Cli\Analytics;

use App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRate;
use App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRateCalculatorInterface;
use App\HomeLibrary\UI\Cli\Analytics\CalculateAiRecommendationSuccessRateCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CalculateAiRecommendationSuccessRateCommandTest extends TestCase
{
    private AiRecommendationSuccessRateCalculatorInterface&MockObject $calculator;

    protected function setUp(): void
    {
        $this->calculator = $this->createMock(AiRecommendationSuccessRateCalculatorInterface::class);
    }

    public function testItWarnsWhenNoEventsFound(): void
    {
        $this->calculator
            ->expects(self::once())
            ->method('calculate')
            ->with(null, null)
            ->willReturn(new AiRecommendationSuccessRate(0, 0));

        $command = new CalculateAiRecommendationSuccessRateCommand($this->calculator);
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('No AI recommendation events found', $tester->getDisplay());
    }

    public function testItDisplaysSuccessRate(): void
    {
        $fromInput = '2025-01-01T00:00:00';
        $toInput = '2025-01-31T23:59:59';
        $timezone = 'Europe/Warsaw';

        $this->calculator
            ->expects(self::once())
            ->method('calculate')
            ->with(
                self::callback(static function (?\DateTimeImmutable $from): bool {
                    self::assertNotNull($from);

                    return '2024-12-31T23:00:00+00:00' === $from->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
                }),
                self::callback(static function (?\DateTimeImmutable $to): bool {
                    self::assertNotNull($to);

                    return '2025-01-31T22:59:59+00:00' === $to->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
                }),
            )
            ->willReturn(new AiRecommendationSuccessRate(28, 22));

        $command = new CalculateAiRecommendationSuccessRateCommand($this->calculator);
        $tester = new CommandTester($command);

        $status = $tester->execute([
            '--from' => $fromInput,
            '--to' => $toInput,
            '--timezone' => $timezone,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Success rate', $tester->getDisplay());
        self::assertStringContainsString('78.6%', $tester->getDisplay());
        self::assertStringContainsString('Success threshold (75%) met or exceeded.', $tester->getDisplay());
    }

    public function testItThrowsExceptionForInvalidTimezone(): void
    {
        $command = new CalculateAiRecommendationSuccessRateCommand($this->calculator);
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['--timezone' => 'Invalid/Timezone']);
    }

    public function testItThrowsExceptionWhenFromIsAfterTo(): void
    {
        $command = new CalculateAiRecommendationSuccessRateCommand($this->calculator);
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $tester->execute([
            '--from' => '2025-02-01T00:00:00',
            '--to' => '2025-01-01T00:00:00',
        ]);
    }
}
