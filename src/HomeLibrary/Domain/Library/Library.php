<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Library;

use App\HomeLibrary\Domain\Common\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'libraries')]
#[ORM\HasLifecycleCallbacks]
class Library
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Embedded(class: LibraryName::class, columnPrefix: false)]
    private LibraryName $name;

    #[ORM\Embedded(class: LibraryPasswordHash::class, columnPrefix: false)]
    private LibraryPasswordHash $passwordHash;

    public function __construct(
        UuidInterface $id,
        LibraryName $name,
        LibraryPasswordHash $passwordHash,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->passwordHash = $passwordHash;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): LibraryName
    {
        return $this->name;
    }

    public function passwordHash(): LibraryPasswordHash
    {
        return $this->passwordHash;
    }
}
