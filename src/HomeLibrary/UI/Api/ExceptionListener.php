<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api;

use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Genre\Exception\GenreNotFoundException;
use App\HomeLibrary\Domain\Shelf\Exception\DuplicateShelfNameException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfIsSystemException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotEmptyException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotFoundException;
use App\HomeLibrary\Domain\User\Exception\UserAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener(event: ExceptionEvent::class)]
final class ExceptionListener
{
    public function __construct(
        private readonly Problem\ProblemJsonResponseFactory $problemFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$this->isApiRequest($event)) {
            return;
        }

        $exception = $event->getThrowable();

        $response = $this->createProblemResponseFor($exception);

        if (null !== $response) {
            $event->setResponse($response);

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return;
        }

        $this->handleUnexpectedException($event, $exception);
    }

    private function isApiRequest(ExceptionEvent $event): bool
    {
        return str_starts_with((string) $event->getRequest()->getPathInfo(), '/api/');
    }

    private function createProblemResponseFor(\Throwable $exception): ?JsonResponse
    {
        return match (true) {
            $exception instanceof DuplicateShelfNameException => $this->problemFactory->create(
                type: 'https://example.com/problems/shelf-conflict',
                title: 'Shelf name already exists',
                status: Response::HTTP_CONFLICT,
                detail: $exception->getMessage(),
            ),
            $exception instanceof ShelfNotFoundException => $this->problemFactory->create(
                type: 'https://example.com/problems/not-found',
                title: 'Shelf not found',
                status: Response::HTTP_NOT_FOUND,
                detail: $exception->getMessage(),
            ),
            $exception instanceof GenreNotFoundException => $this->problemFactory->create(
                type: 'https://example.com/problems/not-found',
                title: 'Genre not found',
                status: Response::HTTP_NOT_FOUND,
                detail: $exception->getMessage(),
            ),
            $exception instanceof ShelfIsSystemException => $this->problemFactory->create(
                type: 'https://example.com/errors/cannot-delete-system-shelf',
                title: 'Cannot delete system shelf',
                status: Response::HTTP_CONFLICT,
                detail: $exception->getMessage(),
            ),
            $exception instanceof ShelfNotEmptyException => $this->problemFactory->create(
                type: 'https://example.com/errors/shelf-not-empty',
                title: 'Shelf not empty',
                status: Response::HTTP_CONFLICT,
                detail: $exception->getMessage(),
            ),
            $exception instanceof ValidationException => $this->problemFactory->create(
                type: 'https://example.com/problems/validation-error',
                title: 'Validation failed',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                extensions: ['errors' => $exception->errors()],
            ),
            $exception instanceof UserAlreadyExistsException => $this->problemFactory->create(
                type: 'https://example.com/problems/user-conflict',
                title: 'User already exists',
                status: Response::HTTP_CONFLICT,
                detail: $exception->getMessage(),
            ),
            default => null,
        };
    }

    private function handleUnexpectedException(ExceptionEvent $event, \Throwable $exception): void
    {
        $request = $event->getRequest();

        $this->logger->error('Unhandled exception while handling API request', [
            'exception' => $exception,
            'path' => (string) $request->getPathInfo(),
            'method' => (string) $request->getMethod(),
        ]);

        $event->setResponse(
            $this->problemFactory->create(
                type: 'https://example.com/problems/internal-error',
                title: 'Internal Server Error',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            ),
        );
    }
}
