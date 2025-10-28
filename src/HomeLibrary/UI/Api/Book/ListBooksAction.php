<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Book;

use App\HomeLibrary\Application\Book\Query\ListBooksHandler;
use App\HomeLibrary\Application\Book\Query\ListBooksQuery;
use App\HomeLibrary\Application\Book\Service\ListBooksParameterValidator;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\UI\Api\Book\Resource\BookResource;
use App\HomeLibrary\UI\Api\Problem\ProblemJsonResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/books', name: 'api_books_list', methods: ['GET'])]
final class ListBooksAction extends AbstractController
{
    public function __construct(
        private readonly ListBooksHandler $handler,
        private readonly ListBooksParameterValidator $validator,
        private readonly BookResource $bookResource,
        private readonly ProblemJsonResponseFactory $problemFactory,
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $normalized = $this->validator->validate([
                'q' => $request->query->get('q'),
                'shelfId' => $request->query->get('shelfId'),
                'genreIds' => $request->query->get('genreIds'),
                'limit' => $request->query->get('limit'),
                'offset' => $request->query->get('offset'),
                'sort' => $request->query->get('sort'),
                'order' => $request->query->get('order'),
            ]);
        } catch (ValidationException $exception) {
            return $this->problemFactory->create(
                type: 'https://example.com/problems/invalid-query-parameter',
                title: 'Invalid query parameter',
                status: Response::HTTP_BAD_REQUEST,
                detail: 'Provided query parameters are invalid.',
                extensions: ['errors' => $exception->errors()],
            );
        }

        $result = ($this->handler)(
            new ListBooksQuery(
                searchTerm: $normalized['q'],
                shelfId: $normalized['shelfId'],
                genreIds: $normalized['genreIds'],
                limit: $normalized['limit'],
                offset: $normalized['offset'],
                sort: $normalized['sort'],
                order: $normalized['order'],
            )
        );

        return new JsonResponse([
            'data' => array_map(
                fn (\App\HomeLibrary\Domain\Book\Book $book): array => $this->bookResource->toArray($book),
                $result->books(),
            ),
            'meta' => [
                'total' => $result->total(),
                'limit' => $result->limit(),
                'offset' => $result->offset(),
            ],
        ]);
    }
}
