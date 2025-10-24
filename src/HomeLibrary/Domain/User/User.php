<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

use App\HomeLibrary\Domain\Common\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'users_created_at_idx', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Embedded(class: UserEmail::class, columnPrefix: false)]
    private UserEmail $email;

    #[ORM\Embedded(class: UserPasswordHash::class, columnPrefix: false)]
    private UserPasswordHash $passwordHash;

    #[ORM\Embedded(class: UserRoles::class, columnPrefix: false)]
    private UserRoles $roles;

    public function __construct(
        UuidInterface $id,
        UserEmail $email,
        UserPasswordHash $passwordHash,
        UserRoles $roles,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function email(): UserEmail
    {
        return $this->email;
    }

    public function changeEmail(UserEmail $email): void
    {
        $this->email = $email;
    }

    public function passwordHash(): UserPasswordHash
    {
        return $this->passwordHash;
    }

    public function updatePasswordHash(UserPasswordHash $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function roles(): UserRoles
    {
        return $this->roles;
    }

    public function updateRoles(UserRoles $roles): void
    {
        $this->roles = $roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->email->value();
    }

    public function getRoles(): array
    {
        return $this->roles->values();
    }

    public function getPassword(): string
    {
        return $this->passwordHash->value();
    }

    public function eraseCredentials(): void
    {
        // no-op
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }
}
