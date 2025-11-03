<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Book;

use App\HomeLibrary\Application\Book\Command\DeleteBookCommand;
use App\HomeLibrary\Domain\Book\Exception\BookNotFoundException;
use App\HomeLibrary\Domain\Book\BookRepository;

final class DeleteBookHandler
{
    public function __construct(private readonly BookRepository $repository) {}

    public function __invoke(DeleteBookCommand $command): void
    {
        $book = $this->repository->findById($command->id());

        if (null === $book) {
            throw BookNotFoundException::withId($command->id());
        }

        $this->repository->remove($book);
    }
}
