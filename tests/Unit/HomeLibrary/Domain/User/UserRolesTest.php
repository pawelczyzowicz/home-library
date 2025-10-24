<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\User;

use App\HomeLibrary\Domain\User\UserRoles;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserRolesTest extends TestCase
{
    #[Test]
    public function itEnforcesRoleUserAndUppercasesRoles(): void
    {
        $roles = UserRoles::fromArray(['role_admin', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $roles->values());
    }

    #[Test]
    public function itRejectsEmptyRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Role must be a non-empty string.');

        UserRoles::fromArray(['']);
    }
}
