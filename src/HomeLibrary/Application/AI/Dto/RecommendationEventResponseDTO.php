<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\AI\Dto;

use App\HomeLibrary\Domain\AI\AiRecommendationEvent;

final class RecommendationEventResponseDTO
{
    /**
     * @param string[]                    $inputTitles
     * @param RecommendationProposalDTO[] $recommended
     * @param string[]                    $acceptedBookIds
     */
    private function __construct(
        private readonly int $id,
        private readonly string $createdAt,
        private readonly ?string $userId,
        private readonly array $inputTitles,
        private readonly array $recommended,
        private readonly array $acceptedBookIds,
    ) {}

    public static function fromEvent(AiRecommendationEvent $event): self
    {
        $id = $event->id();

        if (null === $id) {
            throw new \LogicException('Recommendation event must be persisted before building the response DTO.');
        }

        $createdAt = $event->createdAt()->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
        $userId = $event->userId();

        $recommended = array_map(
            static fn ($proposal): RecommendationProposalDTO => RecommendationProposalDTO::fromDomain($proposal),
            $event->recommended(),
        );

        $acceptedBookIds = array_map(
            static fn ($uuid): string => $uuid->toString(),
            $event->acceptedBookIds(),
        );

        return new self(
            $id,
            $createdAt,
            null === $userId ? null : $userId->toString(),
            $event->inputTitles(),
            $recommended,
            $acceptedBookIds,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    /**
     * @return string[]
     */
    public function inputTitles(): array
    {
        return $this->inputTitles;
    }

    /**
     * @return RecommendationProposalDTO[]
     */
    public function recommended(): array
    {
        return $this->recommended;
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
            'id' => $this->id,
            'createdAt' => $this->createdAt,
            'userId' => $this->userId,
            'inputTitles' => $this->inputTitles,
            'recommended' => array_map(
                static fn (RecommendationProposalDTO $proposal): array => $proposal->toArray(),
                $this->recommended,
            ),
            'acceptedBookIds' => $this->acceptedBookIds,
        ];
    }
}
