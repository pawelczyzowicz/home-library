<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

use App\HomeLibrary\Domain\Common\TimestampableTrait;
use App\HomeLibrary\Domain\Library\Library;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** @SuppressWarnings("PHPMD.TooManyPublicMethods") */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'users_created_at_idx', columns: ['created_at'])]
#[ORM\Index(name: 'users_library_id_idx', columns: ['library_id'])]
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

    #[ORM\ManyToOne(targetEntity: Library::class)]
    #[ORM\JoinColumn(name: 'library_id', referencedColumnName: 'id', nullable: true)]
    private ?Library $library;

    public function __construct(
        UuidInterface $id,
        UserEmail $email,
        UserPasswordHash $passwordHash,
        UserRoles $roles,
        ?Library $library = null,
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
        $this->library = $library;
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

    public function library(): ?Library
    {
        return $this->library;
    }

    public function assignToLibrary(Library $library): void
    {
        $this->library = $library;
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }
}
