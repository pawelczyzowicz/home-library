<?php

declare(strict_types=1);

namespace App\HomeLibrary\Domain\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class UserRoles
{
    /**
     * @var string[]
     */
    #[ORM\Column(name: 'roles', type: Types::JSON)]
    private array $values;

    public function __construct(array $roles)
    {
        $normalized = [];

        foreach ($roles as $role) {
            $normalized[] = $this->normalizeRole($role);
        }

        if (!\in_array('ROLE_USER', $normalized, true)) {
            $normalized[] = 'ROLE_USER';
        }

        $this->values = array_values(array_unique($normalized));
    }

    private function normalizeRole(mixed $role): string
    {
        if (!\is_string($role)) {
            throw new \InvalidArgumentException('Role must be a string.');
        }

        $trimmed = trim($role);

        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Role must be a non-empty string.');
        }

        return strtoupper($trimmed);
    }

    public static function fromArray(array $roles): self
    {
        return new self($roles);
    }

    /**
     * @return string[]
     */
    public function values(): array
    {
        return $this->values;
    }
}
