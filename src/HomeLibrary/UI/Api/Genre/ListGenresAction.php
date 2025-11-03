<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Genre;

use App\HomeLibrary\Application\Genre\Query\ListGenresHandler;
use App\HomeLibrary\UI\Api\Book\Resource\GenreResource;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/genres', name: 'api_genres_list', methods: ['GET'])]
final class ListGenresAction extends AbstractController
{
    public function __construct(
        private readonly ListGenresHandler $handler,
        private readonly GenreResource $resource,
    ) {}

    public function __invoke(): JsonResponse
    {
        $result = ($this->handler)();

        return new JsonResponse([
            'data' => array_map(
                fn ($genre): array => $this->resource->toArray($genre),
                $result->genres(),
            ),
            'meta' => [
                'total' => $result->total(),
            ],
        ]);
    }
}
