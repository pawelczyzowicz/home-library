<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Application\Auth;

use App\HomeLibrary\Application\Auth\Command\RegisterUserCommand;
use App\HomeLibrary\Application\Auth\RegisterUserHandler;
use App\HomeLibrary\Application\Exception\ValidationException;
use App\HomeLibrary\Domain\User\Exception\UserAlreadyExistsException;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterUserHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;

    private UserPasswordHasherInterface&MockObject $passwordHasher;

    private ValidatorInterface&MockObject $validator;

    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->handler = new RegisterUserHandler(
            $this->userRepository,
            $this->passwordHasher,
            $this->validator,
        );
    }

    #[Test]
    public function itRegistersUserSuccessfully(): void
    {
        $command = new RegisterUserCommand(
            Uuid::uuid7(),
            'user@example.com',
            'securePass1',
            'securePass1',
        );

        $this->validator
            ->expects(self::exactly(2))
            ->method('validate')
            ->willReturnCallback(static fn () => new ConstraintViolationList());

        $this->userRepository
            ->expects(self::once())
            ->method('existsByEmail')
            ->with('user@example.com')
            ->willReturn(false);

        $this->passwordHasher
            ->expects(self::once())
            ->method('hashPassword')
            ->willReturn('$hashed$');

        $this->userRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (User $user) use ($command): bool {
                self::assertTrue($command->id()->equals($user->id()));
                self::assertSame('user@example.com', $user->email()->value());
                self::assertSame('$hashed$', $user->passwordHash()->value());
                self::assertSame(['ROLE_USER'], $user->roles()->values());

                return true;
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function itFailsWhenPasswordsDoNotMatch(): void
    {
        $command = new RegisterUserCommand(
            Uuid::uuid7(),
            'user@example.com',
            'firstPass',
            'secondPass',
        );

        $this->validator->expects(self::never())->method('validate');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed.');

        ($this->handler)($command);
    }

    #[Test]
    public function itFailsWhenEmailValidationFails(): void
    {
        $command = new RegisterUserCommand(
            Uuid::uuid7(),
            'invalid-email',
            'securePass1',
            'securePass1',
        );

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid email.', '', [], '', 'email', $command->email()),
        ]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with('invalid-email', self::isType('array'))
            ->willReturn($violations);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed.');

        ($this->handler)($command);
    }

    #[Test]
    public function itFailsWhenUserAlreadyExists(): void
    {
        $command = new RegisterUserCommand(
            Uuid::uuid7(),
            'user@example.com',
            'securePass1',
            'securePass1',
        );

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with('user@example.com', self::isType('array'))
            ->willReturn(new ConstraintViolationList());

        $this->userRepository
            ->expects(self::once())
            ->method('existsByEmail')
            ->with('user@example.com')
            ->willReturn(true);

        $this->expectException(UserAlreadyExistsException::class);

        ($this->handler)($command);
    }
}
