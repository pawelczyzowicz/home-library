<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Book\ValueObject;

use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookPageCountTest extends TestCase
{
    #[Test]
    public function itAcceptsNull(): void
    {
        $pageCount = new BookPageCount(null);

        self::assertNull($pageCount->value());
    }

    #[Test]
    public function itStoresValidValue(): void
    {
        $pageCount = new BookPageCount(350);

        self::assertSame(350, $pageCount->value());
    }

    #[Test]
    #[DataProvider('invalidPageCountsProvider')]
    public function itRejectsInvalidPageCounts(?int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BookPageCount($value);
    }

    /** @return iterable<string, array{0: ?int}> */
    public static function invalidPageCountsProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
        yield 'too big' => [100000];
    }
}
