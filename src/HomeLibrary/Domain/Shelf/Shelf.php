<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

use App\HomeLibrary\Domain\Common\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shelves')]
#[ORM\HasLifecycleCallbacks]
class Shelf
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Embedded(class: ShelfName::class, columnPrefix: false)]
    private ShelfName $name;

    #[ORM\Embedded(class: ShelfFlag::class, columnPrefix: false)]
    private ShelfFlag $isSystem;

    public function __construct(UuidInterface $id, ShelfName $name, ShelfFlag $isSystem)
    {
        $this->id = $id;
        $this->name = $name;
        $this->isSystem = $isSystem;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): ShelfName
    {
        return $this->name;
    }

    public function rename(ShelfName $name): void
    {
        $this->name = $name;
    }

    public function systemFlag(): ShelfFlag
    {
        return $this->isSystem;
    }

    public function promoteToSystem(): void
    {
        $this->isSystem = ShelfFlag::system();
    }
}
