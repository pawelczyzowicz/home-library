<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\AI;

use App\HomeLibrary\Application\AI\AiRecommendationService;
use App\HomeLibrary\Application\AI\Command\AcceptRecommendationCommand;
use App\HomeLibrary\Application\AI\Command\GenerateRecommendationsCommand;
use App\HomeLibrary\Application\AI\Service\AcceptRecommendationPayloadValidator;
use App\HomeLibrary\Application\AI\Service\GenerateRecommendationsPayloadValidator;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\UI\Api\AI\Dto\AcceptRecommendationPayloadDto;
use App\HomeLibrary\UI\Api\AI\Dto\GenerateRecommendationsPayloadDto;
use App\HomeLibrary\UI\Api\AI\Resource\RecommendationEventResource;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/ai/recommendations', name: 'api_ai_recommendations_')]
#[IsGranted('ROLE_USER')]
final class ApiAiRecommendationsController extends AbstractController
{
    public function __construct(
        private readonly AiRecommendationService $service,
        private readonly GenerateRecommendationsPayloadValidator $generateValidator,
        private readonly AcceptRecommendationPayloadValidator $acceptValidator,
        private readonly RecommendationEventResource $resource,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    #[Route(path: '/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $response = $this->validateContentType($request);

        if (null !== $response) {
            return $response;
        }

        $payload = $this->decodeJsonPayload($request);

        $dto = new GenerateRecommendationsPayloadDto(
            inputs: $payload['inputs'] ?? null,
            excludeTitles: $payload['excludeTitles'] ?? null,
            model: $payload['model'] ?? null,
        );

        $normalized = $this->generateValidator->validate($dto);

        $command = new GenerateRecommendationsCommand(
            userId: $this->currentUserId(),
            inputs: $normalized['inputs'],
            excludeTitles: $normalized['excludeTitles'],
            model: $normalized['model'],
        );

        $event = $this->service->generate($command);

        return new JsonResponse(
            data: $this->resource->toArray($event),
            status: Response::HTTP_CREATED,
        );
    }

    #[Route(path: '/{eventId<\d+>}/accept', name: 'accept', methods: ['POST'])]
    public function accept(int $eventId, Request $request): JsonResponse
    {
        $response = $this->validateContentType($request);

        if (null !== $response) {
            return $response;
        }

        $payload = $this->decodeJsonPayload($request);

        $dto = new AcceptRecommendationPayloadDto($payload['bookId'] ?? null);

        $normalized = $this->acceptValidator->validate($dto);

        $command = new AcceptRecommendationCommand(
            eventId: $eventId,
            bookId: $normalized['bookId'],
            idempotencyKey: $request->headers->get('Idempotency-Key'),
            userId: $this->currentUserId(),
        );

        $event = $this->service->accept($command);

        return new JsonResponse(
            data: $this->resource->toAcceptArray($event),
            status: Response::HTTP_OK,
        );
    }

    private function currentUserId(): ?UuidInterface
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->id() : null;
    }

    private function validateContentType(Request $request): ?JsonResponse
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            return $this->problemFactory->create(
                type: 'https://example.com/problems/invalid-content-type',
                title: 'Invalid Content-Type header',
                status: Response::HTTP_BAD_REQUEST,
                detail: 'Expected Content-Type: application/json',
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Invalid JSON payload. Expected JSON object.');
        }

        return $payload;
    }
}
