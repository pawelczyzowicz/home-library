<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Web\Books;

use App\HomeLibrary\UI\Api\Book\ListBooksAction as ApiListBooksAction;
use App\HomeLibrary\UI\Api\Genre\ListGenresAction as ApiListGenresAction;
use App\HomeLibrary\UI\Api\Shelf\ListShelvesAction as ApiListShelvesAction;
use App\HomeLibrary\UI\Web\Books\ViewModel\ListBooksFilters;
use App\HomeLibrary\UI\Web\Books\ViewModel\ListBooksMetaViewModel;
use App\HomeLibrary\UI\Web\Books\ViewModel\ListBooksViewModel;
use App\HomeLibrary\UI\Web\Books\ViewModel\ProblemDetailsViewModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/books', name: 'books_index', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class BooksController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $filters = ListBooksFilters::fromRequest($request);

        $shelves = $this->fetchCollection(ApiListShelvesAction::class);
        $genres = $this->fetchCollection(ApiListGenresAction::class);

        $booksResult = $this->fetchBooks($filters);

        $notice = $this->resolveNotice(
            $request->query->get('notice'),
            $request->query->get('noticeDetail'),
        );

        $viewModel = new ListBooksViewModel(
            filters: $filters,
            meta: $booksResult['meta'],
            books: $booksResult['books'],
            shelves: $shelves,
            genres: $genres,
            problem: $booksResult['problem'],
        );

        return $this->render('books/index.html.twig', [
            'viewModel' => $viewModel,
            'notice' => $notice,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCollection(string $controllerClass): array
    {
        $response = $this->forward($controllerClass, query: []);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return [];
        }

        $payload = $this->decodeJsonResponse($response);
        $data = $payload['data'] ?? [];

        if (!\is_array($data)) {
            return [];
        }

        return array_values(array_filter(
            $data,
            static fn ($item): bool => \is_array($item),
        ));
    }

    /**
     * @return array{books: array<int, array<string, mixed>>, meta: ListBooksMetaViewModel, problem: ?ProblemDetailsViewModel}
     */
    private function fetchBooks(ListBooksFilters $filters): array
    {
        $response = $this->forward(ApiListBooksAction::class, query: $filters->toApiQuery());
        $payload = $this->decodeJsonResponse($response);

        if (Response::HTTP_OK === $response->getStatusCode()) {
            $books = $payload['data'] ?? [];

            if (!\is_array($books)) {
                $books = [];
            }

            $metaPayload = \is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

            $meta = new ListBooksMetaViewModel(
                total: isset($metaPayload['total']) ? (int) $metaPayload['total'] : 0,
                limit: isset($metaPayload['limit']) ? (int) $metaPayload['limit'] : $filters->limit(),
                offset: isset($metaPayload['offset']) ? (int) $metaPayload['offset'] : $filters->offset(),
            );

            return [
                'books' => array_values(array_filter($books, static fn ($item): bool => \is_array($item))),
                'meta' => $meta,
                'problem' => null,
            ];
        }

        if (Response::HTTP_BAD_REQUEST === $response->getStatusCode()) {
            $meta = new ListBooksMetaViewModel(total: 0, limit: $filters->limit(), offset: $filters->offset());

            return [
                'books' => [],
                'meta' => $meta,
                'problem' => ProblemDetailsViewModel::fromArray($payload),
            ];
        }

        throw new \RuntimeException(\sprintf('Unexpected response (%d) from /api/books.', $response->getStatusCode()));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(Response $response): array
    {
        $content = $response->getContent();

        if (null === $content || '' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid JSON response received.', 0, $exception);
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{status: string, message: string}|null
     */
    private function resolveNotice(?string $notice, ?string $detail): ?array
    {
        return match ($notice) {
            'book-deleted' => [
                'status' => 'success',
                'message' => 'Książka została usunięta.',
            ],
            'book-delete-failed' => [
                'status' => 'error',
                'message' => $detail ?: 'Nie udało się usunąć książki. Spróbuj ponownie później.',
            ],
            default => null,
        };
    }
}
