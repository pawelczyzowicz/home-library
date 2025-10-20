<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Shelf;

use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShelfFlagTest extends TestCase
{
    private ShelfFlag $systemFlag;
    private ShelfFlag $userFlag;

    protected function setUp(): void
    {
        $this->systemFlag = ShelfFlag::system();
        $this->userFlag = ShelfFlag::userDefined();
    }

    #[Test]
    public function itCreatesSystemFlag(): void
    {
        self::assertTrue($this->systemFlag->isSystem());
        self::assertTrue($this->systemFlag->value());
    }

    #[Test]
    public function itCreatesUserDefinedFlag(): void
    {
        self::assertFalse($this->userFlag->isSystem());
        self::assertFalse($this->userFlag->value());
    }
}
