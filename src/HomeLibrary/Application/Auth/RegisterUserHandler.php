<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Auth;

use App\HomeLibrary\Application\Auth\Command\RegisterUserCommand;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\User\Exception\UserAlreadyExistsException;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRepository;
use App\HomeLibrary\Domain\User\UserRoles;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterUserHandler
{
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(RegisterUserCommand $command): User
    {
        $this->ensurePasswordsMatch($command);

        $violations = $this->validator->validate($command->email(), [
            new Assert\NotBlank(),
            new Assert\Length(min: 3, max: 255),
            new Assert\Email(),
        ]);

        if (0 !== $violations->count()) {
            throw ValidationException::fromViolations($violations);
        }

        $email = UserEmail::fromString($command->email());

        if ($this->userRepository->existsByEmail($email->value())) {
            throw UserAlreadyExistsException::forEmail($email->value());
        }

        $passwordViolations = $this->validator->validate($command->password(), [
            new Assert\NotBlank(),
            new Assert\Length(min: self::PASSWORD_MIN_LENGTH),
        ]);

        if (0 !== $passwordViolations->count()) {
            throw ValidationException::fromViolations($passwordViolations);
        }

        $user = new User(
            $command->id(),
            $email,
            UserPasswordHash::fromString(
                $this->passwordHasher->hashPassword(
                    new class () implements PasswordAuthenticatedUserInterface {
                        public function getPassword(): ?string
                        {
                            return null;
                        }
                    },
                    $command->password(),
                )
            ),
            UserRoles::fromArray(['ROLE_USER']),
        );

        $this->userRepository->save($user);

        return $user;
    }

    private function ensurePasswordsMatch(RegisterUserCommand $command): void
    {
        if ($command->password() !== $command->passwordConfirm()) {
            throw ValidationException::withMessage('passwordConfirm', 'Passwords do not match.');
        }
    }
}
