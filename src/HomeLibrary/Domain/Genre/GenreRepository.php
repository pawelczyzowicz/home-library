<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Genre;

interface GenreRepository
{
    /**
     * @param int[] $ids
     *
     * @return Genre[]
     */
    public function findByIds(array $ids): array;
}
