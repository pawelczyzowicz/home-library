<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Shelf;

use App\HomeLibrary\Domain\Shelf\ShelfName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShelfNameTest extends TestCase
{
    private ShelfName $shelfName;

    protected function setUp(): void
    {
        $this->shelfName = new ShelfName('  Favorite Classics  ');
    }

    #[Test]
    public function itNormalizesNameValue(): void
    {
        self::assertSame('Favorite Classics', $this->shelfName->value());
        self::assertSame('Favorite Classics', (string) $this->shelfName);
    }

    #[Test]
    public function itAcceptsNameWithMinimumLength(): void
    {
        $name = new ShelfName('Z');

        self::assertSame('Z', $name->value());
    }

    public static function providerForInvalidValues(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['      '],
            'exceeds maximum length' => [str_repeat('N', 51)],
        ];
    }

    #[DataProvider('providerForInvalidValues')]
    #[Test]
    public function itThrowsInvalidArgumentExceptionWhenValueHasInvalidLength(string $invalidValue): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shelf name must have between 1 and 50 characters.');

        new ShelfName($invalidValue);
    }
}
