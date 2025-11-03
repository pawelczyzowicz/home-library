<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book;

use App\HomeLibrary\Application\Book\Command\DeleteBookCommand;
use App\HomeLibrary\Application\Book\DeleteBookHandler;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/books/{id}', name: 'api_books_delete', methods: ['DELETE'])]
#[IsGranted('ROLE_USER')]
final class DeleteBookAction extends AbstractController
{
    public function __construct(
        private readonly DeleteBookHandler $handler,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->problemFactory->create(
                type: 'https://example.com/problems/invalid-uuid',
                title: 'Invalid book identifier',
                status: Response::HTTP_BAD_REQUEST,
                detail: 'Parameter "id" must be a valid UUID.',
            );
        }

        $uuid = Uuid::fromString($id);

        ($this->handler)(new DeleteBookCommand($uuid));

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
