<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Shelf;

use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ShelfTest extends TestCase
{
    private UuidInterface $id;
    private ShelfName $name;
    private ShelfFlag $systemFlag;
    private Shelf $shelf;

    protected function setUp(): void
    {
        $this->id = Uuid::uuid7();
        $this->name = new ShelfName('Art Books');
        $this->systemFlag = ShelfFlag::userDefined();

        $this->shelf = new Shelf($this->id, $this->name, $this->systemFlag);
    }

    #[Test]
    public function itReturnsAssignedValues(): void
    {
        self::assertTrue($this->shelf->id()->equals($this->id));
        self::assertSame($this->name, $this->shelf->name());
        self::assertSame($this->systemFlag, $this->shelf->systemFlag());
    }

    #[Test]
    public function itRenamesShelf(): void
    {
        $newName = new ShelfName('Rare Maps');

        $this->shelf->rename($newName);

        self::assertSame($newName, $this->shelf->name());
        self::assertSame('Rare Maps', $this->shelf->name()->value());
    }

    #[Test]
    public function itPromotesShelfToSystem(): void
    {
        $this->shelf->promoteToSystem();

        self::assertTrue($this->shelf->systemFlag()->isSystem());
        self::assertTrue($this->shelf->systemFlag()->value());
    }
}
