<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Genre\Query;

use App\HomeLibrary\Domain\Genre\Genre;

final class ListGenresResult
{
    /**
     * @param Genre[] $genres
     */
    public function __construct(private readonly array $genres) {}

    /**
     * @return Genre[]
     */
    public function genres(): array
    {
        return $this->genres;
    }

    public function total(): int
    {
        return \count($this->genres);
    }
}
