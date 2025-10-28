<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Genre\Exception;

final class GenreNotFoundException extends \RuntimeException
{
    /**
     * @param int[] $ids
     */
    public static function withIds(array $ids): self
    {
        $formatted = implode(', ', array_map(static fn (int $id): string => (string) $id, $ids));

        return new self(\sprintf('Genres with ids [%s] were not found.', $formatted));
    }
}
