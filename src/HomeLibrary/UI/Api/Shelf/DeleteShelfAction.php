<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Shelf;

use App\HomeLibrary\Application\Shelf\Command\DeleteShelfCommand;
use App\HomeLibrary\Application\Shelf\DeleteShelfHandler;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/shelves/{id}', name: 'api_shelves_delete', methods: ['DELETE'])]
final class DeleteShelfAction extends AbstractController
{
    public function __construct(
        private readonly DeleteShelfHandler $handler,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->problemFactory->create(
                type: 'https://example.com/problems/invalid-uuid',
                title: 'Invalid shelf identifier',
                status: Response::HTTP_BAD_REQUEST,
                detail: 'Parameter "id" must be a valid UUID.',
            );
        }

        $uuid = Uuid::fromString($id);

        ($this->handler)(new DeleteShelfCommand($uuid));

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}


