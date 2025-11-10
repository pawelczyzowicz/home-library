<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Cli\Analytics;

use App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRateCalculatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analytics:ai-success-rate',
    description: 'Displays the acceptance rate of AI recommendations.',
)]
final class CalculateAiRecommendationSuccessRateCommand extends Command
{
    public function __construct(
        private readonly AiRecommendationSuccessRateCalculatorInterface $calculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date/time (inclusive) interpreted in the selected timezone (ISO 8601).')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date/time (inclusive) interpreted in the selected timezone (ISO 8601).')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone identifier used to interpret the provided dates.', 'UTC');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $this->preparePeriod($input);

        $result = $this->calculator->calculate($period['fromUtc'], $period['toUtc']);

        $this->renderResult(
            $io,
            $result,
            $period['from'],
            $period['to'],
            $period['timezone'],
        );

        return Command::SUCCESS;
    }

    /**
     * @return array{
     *     timezone: \DateTimeZone,
     *     from: ?\DateTimeImmutable,
     *     to: ?\DateTimeImmutable,
     *     fromUtc: ?\DateTimeImmutable,
     *     toUtc: ?\DateTimeImmutable
     * }
     */
    private function preparePeriod(InputInterface $input): array
    {
        $timezone = $this->resolveTimezone((string) $input->getOption('timezone'));

        $from = $this->buildDateTime($input->getOption('from'), $timezone, 'from');
        $to = $this->buildDateTime($input->getOption('to'), $timezone, 'to');

        $this->assertValidPeriod($from, $to);

        return [
            'timezone' => $timezone,
            'from' => $from,
            'to' => $to,
            'fromUtc' => $this->toUtc($from),
            'toUtc' => $this->toUtc($to),
        ];
    }

    private function resolveTimezone(string $timezoneId): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezoneId);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(\sprintf('Invalid timezone "%s".', $timezoneId), 0, $exception);
        }
    }

    private function assertValidPeriod(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): void
    {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException('Option "from" must be earlier than or equal to option "to".');
        }
    }

    private function toUtc(?\DateTimeImmutable $value): ?\DateTimeImmutable
    {
        return null === $value ? null : $value->setTimezone(new \DateTimeZone('UTC'));
    }

    private function renderResult(
        SymfonyStyle $io,
        \App\HomeLibrary\Application\Analytics\AiRecommendationSuccessRate $result,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        \DateTimeZone $timezone,
    ): void {
        $io->title('AI Recommendation Success Rate');

        if (!$result->hasEvents()) {
            $io->warning('No AI recommendation events found for the selected timeframe.');

            return;
        }

        $rate = $result->successRate();
        $rateText = null === $rate ? 'N/A' : \sprintf('%.1f%%', $rate);

        $io->definitionList(
            ['Timeframe' => $this->formatTimeframe($from, $to, $timezone)],
            ['Total recommendation events' => (string) $result->totalEvents()],
            ['Accepted recommendation events' => (string) $result->acceptedEvents()],
            ['Success rate' => $rateText],
        );

        if (null !== $rate && $rate >= 75.0) {
            $io->success('Success threshold (75%) met or exceeded.');

            return;
        }

        $io->note('Success threshold (75%) not met.');
    }

    private function buildDateTime(mixed $value, \DateTimeZone $timezone, string $optionName): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === trim($value)) {
            throw new \InvalidArgumentException(\sprintf('Option "%s" must be a non-empty string.', $optionName));
        }

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException(\sprintf('Option "%s" must be a valid date/time string.', $optionName), 0, $exception);
        }
    }

    private function formatTimeframe(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, \DateTimeZone $timezone): string
    {
        if (null === $from && null === $to) {
            return 'Entire dataset';
        }

        $parts = [];

        if (null !== $from) {
            $parts[] = \sprintf('from %s (%s)', $from->format(\DATE_ATOM), $timezone->getName());
        }

        if (null !== $to) {
            $parts[] = \sprintf('to %s (%s)', $to->format(\DATE_ATOM), $timezone->getName());
        }

        return implode(' ', $parts);
    }
}
