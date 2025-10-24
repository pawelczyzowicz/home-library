<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\User;

use App\HomeLibrary\Domain\User\UserPasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserPasswordHashTest extends TestCase
{
    #[Test]
    public function itExposesHashValue(): void
    {
        $hash = new UserPasswordHash('$argon2id$v=19$m=65536,t=4,p=1$hash');

        self::assertSame('$argon2id$v=19$m=65536,t=4,p=1$hash', $hash->value());
    }

    #[Test]
    public function itRejectsEmptyHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password hash cannot be empty.');

        new UserPasswordHash('   ');
    }
}
