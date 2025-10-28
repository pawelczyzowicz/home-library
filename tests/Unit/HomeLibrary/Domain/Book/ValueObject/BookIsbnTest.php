<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Book\ValueObject;

use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookIsbnTest extends TestCase
{
    #[Test]
    public function itAcceptsNull(): void
    {
        $isbn = new BookIsbn(null);

        self::assertNull($isbn->value());
    }

    #[Test]
    public function itNormalizesInput(): void
    {
        $isbn = new BookIsbn('978-83-1234567-0');

        self::assertSame('9788312345670', $isbn->value());
    }

    #[Test]
    #[DataProvider('invalidIsbnProvider')]
    public function itRejectsInvalidIsbn(?string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BookIsbn($value);
    }

    /** @return iterable<string, array{0: ?string}> */
    public static function invalidIsbnProvider(): iterable
    {
        yield 'too short' => ['123'];
        yield 'with letters' => ['ABC1234567'];
        yield 'wrong length after clean' => ['123-456-789'];
    }
}
