<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\AI;

use App\HomeLibrary\Application\AI\AiRecommendationService;
use App\HomeLibrary\Application\AI\Command\AcceptRecommendationCommand;
use App\HomeLibrary\Application\AI\Command\GenerateRecommendationsCommand;
use App\HomeLibrary\Application\AI\Exception\RecommendationConflictException;
use App\HomeLibrary\Application\AI\Exception\RecommendationEventNotFoundException;
use App\HomeLibrary\Application\AI\Exception\RecommendationProviderException;
use App\HomeLibrary\Application\AI\IRecommendationProvider;
use App\HomeLibrary\Application\AI\Idempotency\IdempotencyRepository;
use App\HomeLibrary\Application\AI\ReadModel\BookReadModel;
use App\HomeLibrary\Application\AI\ReadModel\BookReadRepository;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\AI\AiRecommendationEvent;
use App\HomeLibrary\Domain\AI\RecommendationEventRepository;
use App\HomeLibrary\Domain\AI\RecommendationProposal;
use App\HomeLibrary\Domain\Book\BookSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AiRecommendationServiceTest extends TestCase
{
    private RecommendationEventRepository&MockObject $eventRepository;

    private IRecommendationProvider&MockObject $provider;

    private BookReadRepository&MockObject $bookReadRepository;

    private IdempotencyRepository&MockObject $idempotencyRepository;

    private AiRecommendationService $service;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(RecommendationEventRepository::class);
        $this->provider = $this->createMock(IRecommendationProvider::class);
        $this->bookReadRepository = $this->createMock(BookReadRepository::class);
        $this->idempotencyRepository = $this->createMock(IdempotencyRepository::class);

        $this->service = new AiRecommendationService(
            $this->eventRepository,
            $this->provider,
            $this->bookReadRepository,
            $this->idempotencyRepository,
        );
    }

    #[Test]
    public function itGeneratesRecommendations(): void
    {
        $userId = Uuid::uuid4();
        $command = new GenerateRecommendationsCommand($userId, ['Title'], 'model');

        $proposals = [
            new RecommendationProposal('t1', 'Title1', 'Author1', [1], 'Reason1'),
            new RecommendationProposal('t2', 'Title2', 'Author2', [2], 'Reason2'),
            new RecommendationProposal('t3', 'Title3', 'Author3', [3], 'Reason3'),
        ];

        $this->provider
            ->expects(self::once())
            ->method('generate')
            ->with(['Title'])
            ->willReturn($proposals);

        $this->eventRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (AiRecommendationEvent $event): bool => $event->userId()?->equals($userId) ?? false));

        $event = $this->service->generate($command);

        self::assertSame(['Title'], $event->inputTitles());
        self::assertCount(3, $event->recommended());
    }

    #[Test]
    public function itWrapsProviderFailures(): void
    {
        $command = new GenerateRecommendationsCommand(null, ['Title'], null);

        $this->provider
            ->method('generate')
            ->willThrowException(new \RuntimeException('Provider error'));

        $this->expectException(RecommendationProviderException::class);
        $this->service->generate($command);
    }

    #[Test]
    public function itValidatesNormalizedInputs(): void
    {
        $command = new GenerateRecommendationsCommand(null, [], null);

        $this->expectException(ValidationException::class);
        $this->service->generate($command);
    }

    #[Test]
    public function itAcceptsRecommendation(): void
    {
        $userId = Uuid::uuid4();
        $bookId = Uuid::uuid7();
        $event = $this->persistedEvent($userId);

        $this->eventRepository
            ->method('findOwnedBy')
            ->with(10, $userId)
            ->willReturn($event);

        $this->bookReadRepository
            ->method('find')
            ->with($bookId)
            ->willReturn(new BookReadModel($bookId, BookSource::AI_RECOMMENDATION, 10));

        $this->idempotencyRepository
            ->method('hasKey')
            ->with(10, 'KEY')
            ->willReturn(false);

        $this->idempotencyRepository
            ->method('hasBook')
            ->with(10, $bookId)
            ->willReturn(false);

        $this->eventRepository
            ->expects(self::once())
            ->method('save')
            ->with($event);

        $this->idempotencyRepository
            ->expects(self::once())
            ->method('record')
            ->with(10, 'KEY', $bookId);

        $command = new AcceptRecommendationCommand(10, $bookId, 'KEY', $userId);

        $result = $this->service->accept($command);

        self::assertCount(1, $result->acceptedBookIds());
        self::assertTrue($result->acceptedBookIds()[0]->equals($bookId));
    }

    #[Test]
    public function itThrowsWhenIdempotencyKeyAlreadyUsed(): void
    {
        $userId = Uuid::uuid4();
        $event = $this->persistedEvent($userId);

        $this->eventRepository
            ->method('findOwnedBy')
            ->willReturn($event);

        $this->idempotencyRepository
            ->method('hasKey')
            ->willReturn(true);

        $this->expectException(RecommendationConflictException::class);

        $this->service->accept(new AcceptRecommendationCommand(10, Uuid::uuid7(), 'KEY', $userId));
    }

    #[Test]
    public function itThrowsWhenBookDoesNotMatchEvent(): void
    {
        $userId = Uuid::uuid4();
        $bookId = Uuid::uuid7();
        $event = $this->persistedEvent($userId);

        $this->eventRepository->method('findOwnedBy')->willReturn($event);

        $this->bookReadRepository
            ->method('find')
            ->with($bookId)
            ->willReturn(new BookReadModel($bookId, BookSource::MANUAL, null));

        $this->expectException(RecommendationEventNotFoundException::class);

        $this->service->accept(new AcceptRecommendationCommand(10, $bookId, null, $userId));
    }

    #[Test]
    public function itThrowsWhenEventNotOwned(): void
    {
        $this->eventRepository
            ->method('findOwnedBy')
            ->willReturn(null);

        $this->expectException(RecommendationEventNotFoundException::class);

        $this->service->accept(new AcceptRecommendationCommand(10, Uuid::uuid7(), null, null));
    }

    private function persistedEvent(?UuidInterface $userId): AiRecommendationEvent
    {
        $event = AiRecommendationEvent::create(
            $userId,
            ['Input'],
            [
                new RecommendationProposal('t1', 'Title1', 'Author1', [1], 'Reason1'),
                new RecommendationProposal('t2', 'Title2', 'Author2', [2], 'Reason2'),
                new RecommendationProposal('t3', 'Title3', 'Author3', [3], 'Reason3'),
            ],
            null,
        );

        $reflection = new \ReflectionProperty($event, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($event, 10);

        return $event;
    }
}
