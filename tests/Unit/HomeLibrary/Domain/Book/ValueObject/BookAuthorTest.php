<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Book\ValueObject;

use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookAuthorTest extends TestCase
{
    #[Test]
    public function itReturnsValue(): void
    {
        $author = new BookAuthor('  Andrzej Sapkowski  ');

        self::assertSame('Andrzej Sapkowski', $author->value());
        self::assertSame('Andrzej Sapkowski', (string) $author);
    }

    #[Test]
    #[DataProvider('invalidAuthorsProvider')]
    public function itRejectsInvalidAuthors(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BookAuthor($value);
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidAuthorsProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['    '];
        yield 'too long' => [str_repeat('b', 300)];
    }
}
