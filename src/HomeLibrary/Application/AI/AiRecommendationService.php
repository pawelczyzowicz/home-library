<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI;

use App\HomeLibrary\Application\AI\Command\AcceptRecommendationCommand;
use App\HomeLibrary\Application\AI\Command\GenerateRecommendationsCommand;
use App\HomeLibrary\Application\AI\Exception\RecommendationConflictException;
use App\HomeLibrary\Application\AI\Exception\RecommendationEventNotFoundException;
use App\HomeLibrary\Application\AI\Exception\RecommendationProviderException;
use App\HomeLibrary\Application\AI\Idempotency\IdempotencyRepository;
use App\HomeLibrary\Application\AI\ReadModel\BookReadRepository;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\AI\AiRecommendationEvent;
use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationEventException;
use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationProposalException;
use App\HomeLibrary\Domain\AI\Exception\RecommendationAlreadyAcceptedException;
use App\HomeLibrary\Domain\AI\RecommendationEventRepository;
use App\HomeLibrary\Domain\AI\RecommendationProposal;
use App\HomeLibrary\Domain\Book\BookSource;
use Ramsey\Uuid\UuidInterface;

final class AiRecommendationService
{
    public function __construct(
        private readonly RecommendationEventRepository $eventRepository,
        private readonly IRecommendationProvider $recommendationProvider,
        private readonly BookReadRepository $bookReadRepository,
        private readonly IdempotencyRepository $idempotencyRepository,
    ) {}

    public function generate(GenerateRecommendationsCommand $command): AiRecommendationEvent
    {
        $inputTitles = $this->normalizeTitles($command->inputs(), 'inputs', false);

        try {
            $proposals = $this->recommendationProvider->generate($inputTitles);
        } catch (\Throwable $exception) {
            throw RecommendationProviderException::because('Failed to generate recommendations.', $exception);
        }

        $proposals = $this->assertRecommendationProposals($proposals);

        try {
            $event = AiRecommendationEvent::create(
                $command->userId(),
                $inputTitles,
                $proposals,
                $command->model(),
            );
        } catch (InvalidRecommendationEventException $exception) {
            throw ValidationException::withMessage('inputs', $exception->getMessage());
        } catch (InvalidRecommendationProposalException $exception) {
            throw RecommendationProviderException::because('Provider returned invalid recommendation proposals.', $exception);
        }

        $this->eventRepository->save($event);

        return $event;
    }

    public function accept(AcceptRecommendationCommand $command): AiRecommendationEvent
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($command->idempotencyKey());
        $event = $this->loadOwnedEvent($command->eventId(), $command->userId());
        $eventId = $this->eventIdOrFail($event);

        if (null !== $idempotencyKey && $this->idempotencyRepository->hasKey($eventId, $idempotencyKey)) {
            throw RecommendationConflictException::forIdempotencyKey($idempotencyKey);
        }

        $book = $this->bookReadRepository->find($command->bookId());

        if (null === $book
            || BookSource::AI_RECOMMENDATION !== $book->source()
            || $book->recommendationId() !== $eventId) {
            throw RecommendationEventNotFoundException::forBook($command->bookId());
        }

        if ($this->idempotencyRepository->hasBook($eventId, $command->bookId())) {
            throw RecommendationConflictException::forBookAlreadyAccepted($command->bookId());
        }

        try {
            $event->acceptBook($command->bookId());
        } catch (RecommendationAlreadyAcceptedException $exception) {
            throw RecommendationConflictException::forBookAlreadyAccepted($command->bookId());
        }

        $this->eventRepository->save($event);

        if (null !== $idempotencyKey) {
            $this->idempotencyRepository->record($eventId, $idempotencyKey, $command->bookId());
        }

        return $event;
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return string[]
     */
    private function normalizeTitles(array $values, string $field, bool $allowEmpty): array
    {
        $normalized = [];

        foreach ($values as $index => $value) {
            if (!\is_string($value)) {
                throw ValidationException::withMessage($field . '[' . $index . ']', 'Value must be a string.');
            }

            $trimmed = trim($value);

            if ('' === $trimmed) {
                if ($allowEmpty) {
                    continue;
                }

                throw ValidationException::withMessage($field . '[' . $index . ']', 'Value must be a non-empty string.');
            }

            $normalized[strtolower($trimmed)] = $trimmed;
        }

        if (!$allowEmpty && [] === $normalized) {
            throw ValidationException::withMessage($field, 'At least one value must be provided.');
        }

        return array_values($normalized);
    }

    /**
     * @return RecommendationProposal[]
     */
    private function assertRecommendationProposals(mixed $proposals): array
    {
        if (!\is_array($proposals)) {
            throw RecommendationProviderException::because('Provider returned an invalid response.');
        }

        if (3 !== \count($proposals)) {
            throw RecommendationProviderException::because('Provider must return exactly 3 recommendation proposals.');
        }

        $validated = [];

        foreach ($proposals as $proposal) {
            if (!$proposal instanceof RecommendationProposal) {
                throw RecommendationProviderException::because('Provider returned invalid recommendation proposal objects.');
            }

            $validated[] = $proposal;
        }

        return $validated;
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        if (null === $key) {
            return null;
        }

        $trimmed = trim($key);

        if ('' === $trimmed) {
            throw ValidationException::withMessage('Idempotency-Key', 'Idempotency key must not be empty when provided.');
        }

        if (\strlen($trimmed) > 128) {
            throw ValidationException::withMessage('Idempotency-Key', 'Idempotency key must not exceed 128 characters.');
        }

        return $trimmed;
    }

    private function loadOwnedEvent(int $eventId, ?UuidInterface $userId): AiRecommendationEvent
    {
        $event = $this->eventRepository->findOwnedBy($eventId, $userId);

        if (null === $event) {
            throw RecommendationEventNotFoundException::forEvent($eventId);
        }

        if (!$event->isOwnedBy($userId)) {
            throw RecommendationEventNotFoundException::forEvent($eventId);
        }

        return $event;
    }

    private function eventIdOrFail(AiRecommendationEvent $event): int
    {
        $id = $event->id();

        if (null === $id) {
            throw new \LogicException('Recommendation event must be persisted before continuing the workflow.');
        }

        return $id;
    }
}
