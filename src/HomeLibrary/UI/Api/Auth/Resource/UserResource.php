<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api\Auth\Resource;

use App\HomeLibrary\Domain\User\User;

final class UserResource
{
    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     library: array{id: string, name: string},
     *     createdAt: string,
     * }
     */
    public function toArray(User $user): array
    {
        $library = $user->library();

        return [
            'id' => $user->id()->toString(),
            'email' => $user->email()->value(),
            'library' => [
                'id' => $library->id()->toString(),
                'name' => $library->name()->value(),
            ],
            'createdAt' => $user->createdAt()->format(\DATE_ATOM),
        ];
    }
}
