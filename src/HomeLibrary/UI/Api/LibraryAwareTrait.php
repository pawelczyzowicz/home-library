<?php

declare(strict_types=1);

namespace App\HomeLibrary\UI\Api;

use App\HomeLibrary\Domain\User\User;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait LibraryAwareTrait
{
    private function currentLibraryId(): UuidInterface
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $user->library()->id();
    }
}
