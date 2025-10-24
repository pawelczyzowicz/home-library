<?php

declare(strict_types=1);

namespace App\Tests\Integration\HomeLibrary\Infrastructure\Persistence;

use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRepository;
use App\HomeLibrary\Domain\User\UserRoles;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(UserRepository::class);

        $container = self::getContainer();

        \assert($container instanceof ContainerInterface);

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
    }

    public function testSaveAndFindByEmail(): void
    {
        $user = new User(
            Uuid::uuid7(),
            UserEmail::fromString('user@example.com'),
            UserPasswordHash::fromString('$hash$'),
            UserRoles::fromArray(['ROLE_USER']),
        );

        $this->repository->save($user);

        $found = $this->repository->findByEmail('user@example.com');

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($user->id()));
    }

    public function testExistsByEmail(): void
    {
        $user = new User(
            Uuid::uuid7(),
            UserEmail::fromString('exists@example.com'),
            UserPasswordHash::fromString('$hash$'),
            UserRoles::fromArray(['ROLE_USER']),
        );

        $this->repository->save($user);

        self::assertTrue($this->repository->existsByEmail('exists@example.com'));
        self::assertFalse($this->repository->existsByEmail('missing@example.com'));
    }
}
