<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User\Exception;

final class UserAlreadyExistsException extends \DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(\sprintf('User with email "%s" already exists.', $email));
    }
}
