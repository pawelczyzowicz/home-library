<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Genre;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'genres')]
class Genre
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Embedded(class: GenreName::class, columnPrefix: false)]
    private GenreName $name;

    public function __construct(int $id, GenreName $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): GenreName
    {
        return $this->name;
    }
}
