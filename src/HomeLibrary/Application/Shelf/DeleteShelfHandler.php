<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf;

use App\HomeLibrary\Application\Shelf\Command\DeleteShelfCommand;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfIsSystemException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotEmptyException;
use App\HomeLibrary\Domain\Shelf\Exception\ShelfNotFoundException;
use App\HomeLibrary\Domain\Shelf\ShelfRepository;

final class DeleteShelfHandler
{
    public function __construct(
        private readonly ShelfRepository $repository,
        private readonly ShelfBooksCounter $booksCounter,
    ) {
    }

    public function __invoke(DeleteShelfCommand $command): void
    {
        $shelf = $this->repository->findById($command->id());

        if ($shelf === null) {
            throw ShelfNotFoundException::withId($command->id());
        }

        if ($shelf->systemFlag()->isSystem()) {
            throw ShelfIsSystemException::withId($shelf->id());
        }

        if ($this->booksCounter->countForShelf($shelf->id()) > 0) {
            throw ShelfNotEmptyException::withId($shelf->id());
        }

        $this->repository->remove($shelf);
    }
}


