<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Ai;

use App\HomeLibrary\Application\AI\AiRecommendationService;
use App\HomeLibrary\Application\AI\Exception\RecommendationEventNotFoundException;
use App\HomeLibrary\Application\Genre\Query\ListGenresHandler;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\UI\Api\AI\Resource\RecommendationEventResource;
use App\HomeLibrary\UI\Api\Book\Resource\GenreResource;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/ai/recommendations/{eventId<\d+>}', name: 'ai_recommendations_show', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class AiRecommendationsResultsController extends AbstractController
{
    public function __construct(
        private readonly AiRecommendationService $recommendationService,
        private readonly RecommendationEventResource $eventResource,
        private readonly ListGenresHandler $listGenresHandler,
        private readonly GenreResource $genreResource,
        private readonly ShelfRepository $shelfRepository,
    ) {}

    public function __invoke(int $eventId): Response
    {
        try {
            $event = $this->recommendationService->findOwned($eventId, $this->currentUserId());
        } catch (RecommendationEventNotFoundException) {
            throw new NotFoundHttpException();
        }

        try {
            $eventJson = json_encode($this->eventResource->toArray($event), \JSON_THROW_ON_ERROR);
            $genresJson = json_encode($this->buildGenresPayload(), \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Failed to serialise recommendation data.', 0, $exception);
        }

        return $this->render('ai/recommendations_results.html.twig', [
            'eventId' => $eventId,
            'preloadedEventJson' => $eventJson,
            'genresCatalogJson' => $genresJson,
            'purchaseShelfId' => $this->resolvePurchaseShelfId(),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function buildGenresPayload(): array
    {
        $result = ($this->listGenresHandler)();

        return array_map(
            fn ($genre): array => $this->genreResource->toArray($genre),
            $result->genres(),
        );
    }

    private function resolvePurchaseShelfId(): ?string
    {
        $shelves = $this->shelfRepository->search(null, true);

        if ([] === $shelves) {
            return null;
        }

        return $shelves[0]->id()->toString();
    }

    private function currentUserId(): ?\Ramsey\Uuid\UuidInterface
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->id() : null;
    }
}
