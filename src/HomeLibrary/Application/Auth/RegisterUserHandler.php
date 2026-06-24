<?php

declare(strict_types=1);

namespace App\HomeLibrary\Application\Auth;

use App\HomeLibrary\Application\Auth\Command\RegisterUserCommand;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\Library\Exception\InvalidLibraryPasswordException;
use App\HomeLibrary\Domain\Library\Exception\LibraryAlreadyExistsException;
use App\HomeLibrary\Domain\Library\Exception\LibraryNotFoundException;
use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\Library\LibraryRepository;
use App\HomeLibrary\Domain\User\Exception\UserAlreadyExistsException;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRepository;
use App\HomeLibrary\Domain\User\UserRoles;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterUserHandler
{
    private const PASSWORD_MIN_LENGTH = 8;
    private const LIBRARY_NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LibraryRepository $libraryRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordHasherInterface $libraryPasswordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    public function __invoke(RegisterUserCommand $command): User
    {
        $this->ensurePasswordsMatch($command);
        $this->validateLibraryMode($command);

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

        $library = 'create' === $command->libraryMode()
            ? $this->createLibrary($command)
            : $this->joinLibrary($command);

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
            $library,
        );

        $this->userRepository->save($user);

        return $user;
    }

    private function createLibrary(RegisterUserCommand $command): Library
    {
        $this->validateLibraryFields($command);

        if ($this->libraryRepository->existsByName($command->libraryName())) {
            throw LibraryAlreadyExistsException::forName($command->libraryName());
        }

        $library = new Library(
            Uuid::uuid7(),
            new LibraryName($command->libraryName()),
            LibraryPasswordHash::fromString(
                $this->libraryPasswordHasher->hash($command->libraryPassword())
            ),
        );

        $this->libraryRepository->save($library);

        return $library;
    }

    private function joinLibrary(RegisterUserCommand $command): Library
    {
        $this->validateLibraryFields($command);

        $library = $this->libraryRepository->findByName($command->libraryName());

        if (null === $library) {
            throw LibraryNotFoundException::forName($command->libraryName());
        }

        if (!$this->libraryPasswordHasher->verify($library->passwordHash()->value(), $command->libraryPassword())) {
            throw InvalidLibraryPasswordException::forName($command->libraryName());
        }

        return $library;
    }

    private function validateLibraryMode(RegisterUserCommand $command): void
    {
        if (!\in_array($command->libraryMode(), ['create', 'join'], true)) {
            throw ValidationException::withMessage(
                'libraryMode',
                'Library mode must be "create" or "join".',
            );
        }
    }

    private function validateLibraryFields(RegisterUserCommand $command): void
    {
        $nameViolations = $this->validator->validate($command->libraryName(), [
            new Assert\NotBlank(),
            new Assert\Length(max: self::LIBRARY_NAME_MAX_LENGTH),
        ]);

        if (0 !== $nameViolations->count()) {
            throw ValidationException::fromViolations($nameViolations);
        }

        $passwordViolations = $this->validator->validate($command->libraryPassword(), [
            new Assert\NotBlank(),
            new Assert\Length(min: self::PASSWORD_MIN_LENGTH),
        ]);

        if (0 !== $passwordViolations->count()) {
            throw ValidationException::fromViolations($passwordViolations);
        }
    }

    private function ensurePasswordsMatch(RegisterUserCommand $command): void
    {
        if ($command->password() !== $command->passwordConfirm()) {
            throw ValidationException::withMessage('passwordConfirm', 'Passwords do not match.');
        }
    }
}
