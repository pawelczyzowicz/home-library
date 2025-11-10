<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book;

use App\HomeLibrary\Application\Book\Command\CreateBookCommand;
use App\HomeLibrary\Application\Book\CreateBookHandler;
use App\HomeLibrary\Application\Book\Service\CreateBookPayloadValidator;
use App\HomeLibrary\UI\Api\Book\Dto\CreateBookPayloadDto;
use App\HomeLibrary\UI\Api\Book\Resource\BookResource;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/books', name: 'api_books_create', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class CreateBookAction extends AbstractController
{
    public function __construct(
        private readonly CreateBookHandler $handler,
        private readonly CreateBookPayloadValidator $validator,
        private readonly BookResource $resource,
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
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON payload.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Invalid JSON payload. Expected JSON object.');
        }

        $dto = new CreateBookPayloadDto(
            title: $payload['title'] ?? null,
            author: $payload['author'] ?? null,
            shelfId: $payload['shelfId'] ?? null,
            genreIds: $payload['genreIds'] ?? null,
            isbn: $payload['isbn'] ?? null,
            pageCount: $payload['pageCount'] ?? null,
            source: $payload['source'] ?? null,
            recommendationId: $payload['recommendationId'] ?? null,
        );

        $normalized = $this->validator->validate($dto);

        $command = new CreateBookCommand(
            id: Uuid::uuid7(),
            title: $normalized['title'],
            author: $normalized['author'],
            isbn: $normalized['isbn'],
            pageCount: $normalized['pageCount'],
            shelfId: $normalized['shelfId'],
            genreIds: $normalized['genreIds'],
            source: $normalized['source'],
            recommendationId: $normalized['recommendationId'],
        );

        $book = ($this->handler)($command);

        return new JsonResponse(
            data: $this->resource->toArray($book),
            status: Response::HTTP_CREATED,
        );
    }
}
