<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\AI;

use App\HomeLibrary\Domain\AI\Exception\InvalidRecommendationEventException;
use App\HomeLibrary\Domain\AI\Exception\RecommendationAlreadyAcceptedException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'ai_recommendation_events')]
#[ORM\HasLifecycleCallbacks]
final class AiRecommendationEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore-next-line Doctrine assigns the identifier after persistence. */
    private ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'user_id', type: 'uuid', nullable: true)]
    private ?UuidInterface $userId;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'input_titles', type: Types::JSON)]
    private array $inputTitles;

    #[ORM\Column(name: 'recommended_book_ids', type: Types::JSON)]
    private array $recommendedData = [];

    /**
     * @var RecommendationProposal[]
     */
    private array $recommended = [];

    #[ORM\Column(name: 'accepted_book_ids', type: Types::JSON)]
    private array $acceptedBookIdsData = [];

    /**
     * @var UuidInterface[]
     */
    private array $acceptedBookIds = [];

    #[ORM\Column(name: 'model', type: Types::STRING, length: 191, nullable: true)]
    private ?string $model;

    private function __construct(
        ?UuidInterface $userId,
        array $inputTitles,
        array $recommended,
        ?string $model,
        \DateTimeImmutable $createdAt,
        array $acceptedBookIds,
    ) {
        $this->userId = $userId;
        $this->createdAt = $createdAt;
        $this->model = $this->normalizeModel($model);

        $this->setInputTitles($inputTitles);
        $this->setRecommended($recommended);
        $this->setAcceptedBookIds($acceptedBookIds);
    }

    /**
     * @param array<int, mixed> $inputTitles
     * @param array<int, mixed> $recommended
     */
    public static function create(
        ?UuidInterface $userId,
        array $inputTitles,
        array $recommended,
        ?string $model,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $createdAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            $userId,
            $inputTitles,
            $recommended,
            $model,
            $createdAt,
            [],
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function userId(): ?UuidInterface
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
     * @return RecommendationProposal[]
     */
    public function recommended(): array
    {
        return $this->recommended;
    }

    /**
     * @return UuidInterface[]
     */
    public function acceptedBookIds(): array
    {
        return $this->acceptedBookIds;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function acceptBook(UuidInterface $bookId): void
    {
        if ($this->hasAcceptedBook($bookId)) {
            throw RecommendationAlreadyAcceptedException::forBook($bookId);
        }

        $this->acceptedBookIds[] = $bookId;
        $this->acceptedBookIdsData = array_map(
            static fn (UuidInterface $uuid): string => $uuid->toString(),
            $this->acceptedBookIds,
        );
    }

    public function isOwnedBy(?UuidInterface $userId): bool
    {
        if (null === $this->userId) {
            return null === $userId;
        }

        if (null === $userId) {
            return false;
        }

        return $this->userId->equals($userId);
    }

    #[ORM\PostLoad]
    public function restoreComputedState(): void
    {
        $this->recommended = array_map(
            static fn (array $data): RecommendationProposal => RecommendationProposal::fromArray($data),
            $this->recommendedData,
        );

        $this->acceptedBookIds = array_map(
            static fn (string $id): UuidInterface => Uuid::fromString($id),
            $this->acceptedBookIdsData,
        );
    }

    /**
     * @param array<int, mixed> $inputTitles
     */
    private function setInputTitles(array $inputTitles): void
    {
        if ([] === $inputTitles) {
            throw InvalidRecommendationEventException::because('Recommendation event must contain at least one input title.');
        }

        $normalized = [];

        foreach ($inputTitles as $title) {
            if (!\is_string($title)) {
                throw InvalidRecommendationEventException::because('Each input title must be a string.');
            }

            $trimmed = trim($title);

            if ('' === $trimmed) {
                throw InvalidRecommendationEventException::because('Each input title must be a non-empty string.');
            }

            $normalized[$trimmed] = $trimmed;
        }

        $this->inputTitles = array_values($normalized);
    }

    /**
     * @param array<int, mixed> $recommended
     */
    private function setRecommended(array $recommended): void
    {
        if (3 !== \count($recommended)) {
            throw InvalidRecommendationEventException::because('Recommendation event must contain exactly 3 proposals.');
        }

        foreach ($recommended as $proposal) {
            if (!$proposal instanceof RecommendationProposal) {
                throw InvalidRecommendationEventException::because('Invalid recommendation proposal instance.');
            }
        }

        $this->recommended = array_values($recommended);
        $this->recommendedData = array_map(
            static fn (RecommendationProposal $proposal): array => $proposal->toArray(),
            $this->recommended,
        );
    }

    /**
     * @param array<int, UuidInterface|string> $bookIds
     */
    private function setAcceptedBookIds(array $bookIds): void
    {
        $normalized = [];

        foreach ($bookIds as $bookId) {
            if ($bookId instanceof UuidInterface) {
                $normalized[$bookId->toString()] = $bookId;

                continue;
            }

            $trimmed = trim($bookId);

            if ('' === $trimmed) {
                throw InvalidRecommendationEventException::because('Each accepted book id must be a valid UUID string.');
            }

            $normalized[$trimmed] = Uuid::fromString($trimmed);
        }

        $this->acceptedBookIds = array_values($normalized);
        $this->acceptedBookIdsData = array_map(
            static fn (UuidInterface $uuid): string => $uuid->toString(),
            $this->acceptedBookIds,
        );
    }

    private function hasAcceptedBook(UuidInterface $bookId): bool
    {
        foreach ($this->acceptedBookIds as $acceptedBookId) {
            if ($acceptedBookId->equals($bookId)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeModel(?string $model): ?string
    {
        if (null === $model) {
            return null;
        }
        $trimmed = trim($model);

        if ('' === $trimmed) {
            return null;
        }

        if (\strlen($trimmed) > 191) {
            throw InvalidRecommendationEventException::because('Model name must not exceed 191 characters.');
        }

        return $trimmed;
    }
}
