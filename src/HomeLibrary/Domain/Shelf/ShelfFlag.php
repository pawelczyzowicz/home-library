<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\Shelf;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class ShelfFlag
{
    #[ORM\Column(name: 'is_system', type: 'boolean', options: ['default' => false])]
    private readonly bool $isSystem;

    private function __construct(bool $isSystem)
    {
        $this->isSystem = $isSystem;
    }

    public static function system(): self
    {
        return new self(true);
    }

    public static function userDefined(): self
    {
        return new self(false);
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function value(): bool
    {
        return $this->isSystem;
    }
}
