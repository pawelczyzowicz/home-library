<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Shelf;

use App\HomeLibrary\Application\Shelf\Command\CreateShelfCommand;
use App\HomeLibrary\Application\Shelf\CreateShelfHandler;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/shelves', name: 'api_shelves_', methods: ['POST'])]
final class CreateShelfAction extends AbstractController
{
    public function __construct(
        private readonly CreateShelfHandler $handler,
        private readonly ShelfResource $resource,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(Request $request): JsonResponse
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

        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        if (!\is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON payload. Expected JSON object.');
        }

        $command = new CreateShelfCommand(
            id: Uuid::uuid7(),
            name: (string) ($data['name'] ?? ''),
            isSystem: false,
        );

        /** @var Shelf $shelf */
        $shelf = ($this->handler)($command);

        return new JsonResponse(
            data: $this->resource->toArray($shelf),
            status: Response::HTTP_CREATED,
        );
    }
}
