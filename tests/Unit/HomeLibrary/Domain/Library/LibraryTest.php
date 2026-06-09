<?php

declare(strict_types=1);

namespace App\Tests\Unit\HomeLibrary\Domain\Library;

use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class LibraryTest extends TestCase
{
    #[Test]
    public function itCreatesLibraryWithValidData(): void
    {
        $id = Uuid::uuid7();
        $name = LibraryName::fromString('Rodzinna Biblioteka');
        $passwordHash = LibraryPasswordHash::fromString('$2y$13$hashedvalue');

        $library = new Library($id, $name, $passwordHash);

        self::assertTrue($id->equals($library->id()));
        self::assertSame('Rodzinna Biblioteka', $library->name()->value());
        self::assertSame('$2y$13$hashedvalue', $library->passwordHash()->value());
    }

    #[Test]
    public function libraryNameTrimsWhitespace(): void
    {
        $name = LibraryName::fromString('  My Library  ');

        self::assertSame('My Library', $name->value());
    }

    #[Test]
    public function libraryNameRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Library name cannot be empty.');

        LibraryName::fromString('');
    }

    #[Test]
    public function libraryNameRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Library name cannot be empty.');

        LibraryName::fromString('   ');
    }

    #[Test]
    public function libraryNameRejectsOver255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Library name cannot exceed 255 characters.');

        LibraryName::fromString(str_repeat('a', 256));
    }

    #[Test]
    public function libraryPasswordHashRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Library password hash cannot be empty.');

        LibraryPasswordHash::fromString('');
    }

    #[Test]
    public function libraryPasswordHashRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Library password hash cannot be empty.');

        LibraryPasswordHash::fromString('   ');
    }
}
