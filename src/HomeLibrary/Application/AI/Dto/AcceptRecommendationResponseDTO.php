<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

use App\HomeLibrary\Domain\AI\AiRecommendationEvent;

final class AcceptRecommendationResponseDTO
{
    /**
     * @param string[] $acceptedBookIds
     */
    private function __construct(
        private readonly int $eventId,
        private readonly array $acceptedBookIds,
    ) {}

    public static function fromEvent(AiRecommendationEvent $event): self
    {
        $eventId = $event->id();

        if (null === $eventId) {
            throw new \LogicException('Recommendation event must be persisted before building the response DTO.');
        }

        $accepted = array_map(
            static fn ($uuid): string => $uuid->toString(),
            $event->acceptedBookIds(),
        );

        return new self($eventId, $accepted);
    }

    public function eventId(): int
    {
        return $this->eventId;
    }

    /**
     * @return string[]
     */
    public function acceptedBookIds(): array
    {
        return $this->acceptedBookIds;
    }

    public function toArray(): array
    {
        return [
            'event' => [
                'id' => $this->eventId,
                'acceptedBookIds' => $this->acceptedBookIds,
            ],
        ];
    }
}
