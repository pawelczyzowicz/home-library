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
     *     createdAt: string,
     * }
     */
    public function toArray(User $user): array
    {
        return [
            'id' => $user->id()->toString(),
            'email' => $user->email()->value(),
            'createdAt' => $user->createdAt()->format(\DATE_ATOM),
        ];
    }
}
