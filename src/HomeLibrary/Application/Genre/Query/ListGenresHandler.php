<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Genre\Query;

use App\HomeLibrary\Domain\Genre\GenreRepository;

final class ListGenresHandler
{
    public function __construct(private readonly GenreRepository $repository) {}

    public function __invoke(): ListGenresResult
    {
        return new ListGenresResult($this->repository->findAllOrderedByName());
    }
}
