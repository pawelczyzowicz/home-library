<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\HomeLibrary\Domain\Book\Book;
use App\HomeLibrary\Domain\Book\BookSource;
use App\HomeLibrary\Domain\Book\ValueObject\BookAuthor;
use App\HomeLibrary\Domain\Book\ValueObject\BookIsbn;
use App\HomeLibrary\Domain\Book\ValueObject\BookPageCount;
use App\HomeLibrary\Domain\Book\ValueObject\BookTitle;
use App\HomeLibrary\Domain\Genre\Genre;
use App\HomeLibrary\Domain\Genre\GenreName;
use App\HomeLibrary\Domain\Library\Library;
use App\HomeLibrary\Domain\Library\LibraryName;
use App\HomeLibrary\Domain\Library\LibraryPasswordHash;
use App\HomeLibrary\Domain\Shelf\Shelf;
use App\HomeLibrary\Domain\Shelf\ShelfFlag;
use App\HomeLibrary\Domain\Shelf\ShelfName;
use App\HomeLibrary\Domain\User\User;
use App\HomeLibrary\Domain\User\UserEmail;
use App\HomeLibrary\Domain\User\UserPasswordHash;
use App\HomeLibrary\Domain\User\UserRoles;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/** @SuppressWarnings("PHPMD.CouplingBetweenObjects") */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly PasswordHasherInterface $libraryPasswordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $genres = $this->createGenres($manager);
        [$libraryA, $libraryB] = $this->createLibraries($manager);

        $this->createUser($manager, 'anna@example.com', 'Password123!', $libraryA);
        $this->createUser($manager, 'bartek@example.com', 'Password123!', $libraryA);
        $this->createUser($manager, 'celina@example.com', 'Password123!', $libraryB);

        $manager->flush();

        $shelvesA = $this->createShelves($manager, $libraryA, ['Do zakupu', 'Salon', 'Sypialnia']);
        $shelvesB = $this->createShelves($manager, $libraryB, ['Do zakupu', 'Biuro']);

        $manager->flush();

        $this->createBooksForLibraryA($manager, $libraryA, $shelvesA, $genres);
        $this->createBooksForLibraryB($manager, $libraryB, $shelvesB, $genres);

        $manager->flush();
    }

    /**
     * @return array<int, Genre>
     */
    private function createGenres(ObjectManager $manager): array
    {
        $genreData = [
            [1, 'kryminał'],
            [2, 'fantasy'],
            [3, 'sensacja'],
            [4, 'romans'],
            [5, 'sci-fi'],
            [6, 'horror'],
            [7, 'biografia'],
            [8, 'historia'],
            [9, 'popularnonaukowa'],
            [10, 'literatura piękna'],
            [11, 'religia'],
            [12, 'thriller'],
            [13, 'dramat'],
            [14, 'poezja'],
            [15, 'komiks'],
        ];

        $genres = [];

        foreach ($genreData as [$id, $name]) {
            $genre = new Genre($id, new GenreName($name));
            $manager->persist($genre);
            $genres[$id] = $genre;
        }

        return $genres;
    }

    /**
     * @return array{0: Library, 1: Library}
     */
    private function createLibraries(ObjectManager $manager): array
    {
        $libraryA = new Library(
            Uuid::uuid7(),
            new LibraryName('Rodzinna Biblioteka'),
            LibraryPasswordHash::fromString($this->libraryPasswordHasher->hash('rodzinna123')),
        );

        $libraryB = new Library(
            Uuid::uuid7(),
            new LibraryName('Koło Czytelnicze'),
            LibraryPasswordHash::fromString($this->libraryPasswordHasher->hash('kolko123')),
        );

        $manager->persist($libraryA);
        $manager->persist($libraryB);

        return [$libraryA, $libraryB];
    }

    private function createUser(
        ObjectManager $manager,
        string $email,
        string $plainPassword,
        Library $library,
    ): void {
        $user = new User(
            Uuid::uuid7(),
            UserEmail::fromString($email),
            UserPasswordHash::fromString('pre-hash'),
            UserRoles::fromArray(['ROLE_USER']),
            $library,
        );

        $hash = $this->userPasswordHasher->hashPassword($user, $plainPassword);
        $user->updatePasswordHash(UserPasswordHash::fromString($hash));

        $manager->persist($user);
    }

    /**
     * @param string[] $names
     *
     * @return array<string, Shelf>
     */
    private function createShelves(ObjectManager $manager, Library $library, array $names): array
    {
        $shelves = [];

        foreach ($names as $i => $name) {
            $isSystem = 0 === $i;
            $shelf = new Shelf(
                Uuid::uuid7(),
                new ShelfName($name),
                $isSystem ? ShelfFlag::system() : ShelfFlag::userDefined(),
                $library,
            );

            $manager->persist($shelf);
            $shelves[$name] = $shelf;
        }

        return $shelves;
    }

    /**
     * @param array<string, Shelf> $shelves
     * @param array<int, Genre>    $genres
     */
    private function createBooksForLibraryA(
        ObjectManager $manager,
        Library $library,
        array $shelves,
        array $genres,
    ): void {
        $books = [
            ['Wiedźmin: Ostatnie życzenie', 'Andrzej Sapkowski', '9788375780635', 332, 'Salon', [2, 10]],
            ['Solaris', 'Stanisław Lem', '9788308049723', 204, 'Salon', [5, 10]],
            ['Lalka', 'Bolesław Prus', null, 580, 'Sypialnia', [10, 13]],
            ['Zbrodnia i kara', 'Fiodor Dostojewski', '9788373272187', 576, 'Sypialnia', [1, 10]],
        ];

        foreach ($books as [$title, $author, $isbn, $pages, $shelfName, $genreIds]) {
            $bookGenres = array_map(static fn (int $id): Genre => $genres[$id], $genreIds);

            $book = new Book(
                Uuid::uuid7(),
                new BookTitle($title),
                new BookAuthor($author),
                new BookIsbn($isbn),
                new BookPageCount($pages),
                BookSource::MANUAL,
                null,
                $shelves[$shelfName],
                $library,
                $bookGenres,
            );

            $manager->persist($book);
        }
    }

    /**
     * @param array<string, Shelf> $shelves
     * @param array<int, Genre>    $genres
     */
    private function createBooksForLibraryB(
        ObjectManager $manager,
        Library $library,
        array $shelves,
        array $genres,
    ): void {
        $books = [
            ['Dune', 'Frank Herbert', '9780441172719', 412, 'Biuro', [5, 3]],
            ['Hobbit', 'J.R.R. Tolkien', '9780547928227', 310, 'Biuro', [2]],
        ];

        foreach ($books as [$title, $author, $isbn, $pages, $shelfName, $genreIds]) {
            $bookGenres = array_map(static fn (int $id): Genre => $genres[$id], $genreIds);

            $book = new Book(
                Uuid::uuid7(),
                new BookTitle($title),
                new BookAuthor($author),
                new BookIsbn($isbn),
                new BookPageCount($pages),
                BookSource::MANUAL,
                null,
                $shelves[$shelfName],
                $library,
                $bookGenres,
            );

            $manager->persist($book);
        }
    }
}
