<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\User;

use App\HomeLibrary\Domain\User\UserEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    #[Test]
    public function itNormalizesEmailToLowercase(): void
    {
        $email = new UserEmail('Alice.Example@Example.COM');

        self::assertSame('alice.example@example.com', $email->value());
        self::assertSame('alice.example@example.com', (string) $email);
    }

    #[Test]
    #[DataProvider('invalidEmails')]
    public function itRejectsInvalidEmails(string $input, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new UserEmail($input);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function invalidEmails(): iterable
    {
        yield ['ab', 'Email must have between 3 and 255 characters.'];
        yield [str_repeat('a', 256) . '@example.com', 'Email must have between 3 and 255 characters.'];
        yield ['invalid-email', 'Email format is invalid.'];
    }
}
