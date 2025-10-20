<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Shelf;

use App\HomeLibrary\Application\Shelf\Query\ListShelvesHandler;
use App\HomeLibrary\Application\Shelf\Query\ListShelvesQuery;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/shelves', name: 'api_shelves_list', methods: ['GET'])]
final class ListShelvesAction extends AbstractController
{
    public function __construct(
        private readonly ListShelvesHandler $handler,
        private readonly ShelfResource $resource,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $searchTerm = $request->query->get('q');

        if (null !== $searchTerm) {
            $searchTerm = trim((string) $searchTerm);

            if (mb_strlen($searchTerm) > 50) {
                return $this->problemFactory->create(
                    type: 'https://example.com/problems/invalid-query-parameter',
                    title: 'Invalid query parameter',
                    status: Response::HTTP_BAD_REQUEST,
                    detail: 'Parameter "q" must not exceed 50 characters.',
                );
            }

            if ('' === $searchTerm) {
                $searchTerm = null;
            }
        }

        $result = ($this->handler)(new ListShelvesQuery($searchTerm));

        return new JsonResponse(
            [
                'data' => array_map(
                    fn ($shelf) => $this->resource->toArray($shelf),
                    $result->shelves(),
                ),
                'meta' => ['total' => $result->total()],
            ],
        );
    }
}
