<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Shelf;

use App\HomeLibrary\Application\Shelf\Command\CreateShelfCommand;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Library\LibraryRepository;
use App\HomeLibrary\Domain\Shelf\Exception\DuplicateShelfNameException;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use App\HomeLibrary\Domain\Shelf\ShelfRepository as DomainShelfRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class CreateShelfHandler
{
    public function __construct(
        private readonly DomainShelfRepository $repository,
        private readonly LibraryRepository $libraryRepository,
    ) {}

    public function __invoke(CreateShelfCommand $command): Shelf
    {
        $name = trim($command->name());

        if ('' === $name) {
            throw ValidationException::withMessage('name', 'This value should not be blank.');
        }

        if (mb_strlen($name) > 50) {
            throw ValidationException::withMessage('name', 'This value is too long. It should have 50 characters or less.');
        }

        $library = $this->libraryRepository->findById($command->libraryId());

        if (null === $library) {
            throw new \RuntimeException(\sprintf('Library "%s" not found.', $command->libraryId()->toString()));
        }

        $shelf = new Shelf(
            $command->id(),
            new ShelfName($name),
            $command->isSystem() ? ShelfFlag::system() : ShelfFlag::userDefined(),
            $library,
        );

        try {
            $this->repository->save($shelf);
        } catch (UniqueConstraintViolationException) {
            throw DuplicateShelfNameException::withName($command->name());
        }

        return $shelf;
    }
}
