<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Book\ValueObject;

use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookTitleTest extends TestCase
{
    #[Test]
    public function itReturnsValue(): void
    {
        $title = new BookTitle('  Hobbit  ');

        self::assertSame('Hobbit', $title->value());
        self::assertSame('Hobbit', (string) $title);
    }

    #[Test]
    #[DataProvider('invalidTitlesProvider')]
    public function itRejectsInvalidTitles(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BookTitle($value);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidTitlesProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('a', 300)];
    }
}
