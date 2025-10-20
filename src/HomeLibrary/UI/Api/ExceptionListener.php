<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api;

use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Shelf\Exception\DuplicateShelfNameException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfIsSystemException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotEmptyException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
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
        if (!str_starts_with((string) $event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof DuplicateShelfNameException) {
            $event->setResponse(
                $this->problemFactory->create(
                    type: 'https://example.com/problems/shelf-conflict',
                    title: 'Shelf name already exists',
                    status: Response::HTTP_CONFLICT,
                    detail: $exception->getMessage(),
                ),
            );

            return;
        }

        if ($exception instanceof ShelfNotFoundException) {
            $event->setResponse(
                $this->problemFactory->create(
                    type: 'https://example.com/problems/not-found',
                    title: 'Shelf not found',
                    status: Response::HTTP_NOT_FOUND,
                    detail: $exception->getMessage(),
                ),
            );

            return;
        }

        if ($exception instanceof ShelfIsSystemException) {
            $event->setResponse(
                $this->problemFactory->create(
                    type: 'https://example.com/errors/cannot-delete-system-shelf',
                    title: 'Cannot delete system shelf',
                    status: Response::HTTP_CONFLICT,
                    detail: $exception->getMessage(),
                ),
            );

            return;
        }

        if ($exception instanceof ShelfNotEmptyException) {
            $event->setResponse(
                $this->problemFactory->create(
                    type: 'https://example.com/errors/shelf-not-empty',
                    title: 'Shelf not empty',
                    status: Response::HTTP_CONFLICT,
                    detail: $exception->getMessage(),
                ),
            );

            return;
        }

        if ($exception instanceof ValidationException) {
            $event->setResponse(
                $this->problemFactory->create(
                    type: 'https://example.com/problems/validation-error',
                    title: 'Validation failed',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    extensions: ['errors' => $exception->errors()],
                ),
            );

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return;
        }

        $this->logger->error('Unhandled exception while handling API request', [
            'exception' => $exception,
            'path' => (string) $event->getRequest()->getPathInfo(),
            'method' => (string) $event->getRequest()->getMethod(),
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
