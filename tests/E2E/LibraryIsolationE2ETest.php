<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class LibraryIsolationE2ETest extends E2ETestCase
{
    public function testUserCannotSeeOtherLibraryBooks(): void
    {
        $passwordA = 'SecurePass123!';
        $emailA = 'e2e-iso-a-' . bin2hex(random_bytes(4)) . '@example.com';
        $libraryNameA = 'Isolation Lib A ' . bin2hex(random_bytes(4));

        $passwordB = 'SecurePass123!';
        $emailB = 'e2e-iso-b-' . bin2hex(random_bytes(4)) . '@example.com';
        $libraryNameB = 'Isolation Lib B ' . bin2hex(random_bytes(4));

        // User A creates library, shelf, and book
        $httpClientA = $this->registerUser(null, $emailA, $passwordA, $libraryNameA, 'LibPass123!', 'create');
        $shelfA = $this->createShelf($httpClientA, 'Shelf A ' . bin2hex(random_bytes(3)));
        $bookA = $this->createBook($httpClientA, $shelfA['id'], 'Book by A');

        // User B creates a different library
        $httpClientB = $this->registerUser(null, $emailB, $passwordB, $libraryNameB, 'LibPass123!', 'create');
        $this->createShelf($httpClientB, 'Shelf B ' . bin2hex(random_bytes(3)));

        // User B lists books — should see 0 books (not User A's book)
        $httpClientB->request('GET', '/api/books');
        self::assertSame(200, $httpClientB->getResponse()->getStatusCode());

        /** @var array{data: array<int, array{id: string}>, meta: array{total: int}} $booksPayload */
        $booksPayload = json_decode((string) $httpClientB->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $booksPayload['meta']['total'], 'User B should not see books from Library A.');

        $bookIds = array_map(
            static fn (array $book): string => (string) $book['id'],
            $booksPayload['data'],
        );
        self::assertNotContains($bookA['id'], $bookIds, 'User B book list must not contain User A book ID.');
    }

    public function testUserCannotSeeOtherLibraryShelves(): void
    {
        $passwordA = 'SecurePass123!';
        $emailA = 'e2e-iso-shelves-a-' . bin2hex(random_bytes(4)) . '@example.com';
        $libraryNameA = 'Iso Shelf A ' . bin2hex(random_bytes(4));

        $passwordB = 'SecurePass123!';
        $emailB = 'e2e-iso-shelves-b-' . bin2hex(random_bytes(4)) . '@example.com';
        $libraryNameB = 'Iso Shelf B ' . bin2hex(random_bytes(4));

        // User A creates library and a custom shelf
        $httpClientA = $this->registerUser(null, $emailA, $passwordA, $libraryNameA, 'LibPass123!', 'create');
        $shelfA = $this->createShelf($httpClientA, 'Custom Shelf A ' . bin2hex(random_bytes(3)));

        // User B creates a different library
        $httpClientB = $this->registerUser(null, $emailB, $passwordB, $libraryNameB, 'LibPass123!', 'create');

        // User B lists shelves — should NOT contain User A's custom shelf
        $httpClientB->request('GET', '/api/shelves');
        self::assertSame(200, $httpClientB->getResponse()->getStatusCode());

        /** @var array{data: array<int, array{id: string}>} $shelvesPayload */
        $shelvesPayload = json_decode((string) $httpClientB->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $shelfIds = array_map(
            static fn (array $shelf): string => (string) $shelf['id'],
            $shelvesPayload['data'],
        );

        self::assertNotContains(
            $shelfA['id'],
            $shelfIds,
            'User B should not see shelves from Library A.',
        );
    }

    public function testUsersInSameLibraryShareData(): void
    {
        $libraryName = 'Shared Lib ' . bin2hex(random_bytes(4));
        $libraryPassword = 'SharedPass123!';

        $emailA = 'e2e-shared-a-' . bin2hex(random_bytes(4)) . '@example.com';
        $emailB = 'e2e-shared-b-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        // User A creates the library and adds a book
        $httpClientA = $this->registerUser(null, $emailA, $password, $libraryName, $libraryPassword, 'create');
        $shelfA = $this->createShelf($httpClientA, 'Shared Shelf ' . bin2hex(random_bytes(3)));
        $bookA = $this->createBook($httpClientA, $shelfA['id'], 'Shared Book');

        // User B joins the same library
        $httpClientB = $this->registerUser(null, $emailB, $password, $libraryName, $libraryPassword, 'join');

        // User B should see User A's book
        $httpClientB->request('GET', '/api/books');
        self::assertSame(200, $httpClientB->getResponse()->getStatusCode());

        /** @var array{data: array<int, array{id: string}>, meta: array{total: int}} $booksPayload */
        $booksPayload = json_decode((string) $httpClientB->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(1, $booksPayload['meta']['total'], 'User B should see books from shared library.');

        $bookIds = array_map(
            static fn (array $book): string => (string) $book['id'],
            $booksPayload['data'],
        );
        self::assertContains($bookA['id'], $bookIds, 'User B should see the book created by User A in the same library.');
    }

    /**
     * @return array{id: string, title: string}
     */
    private function createBook(
        \Symfony\Component\BrowserKit\HttpBrowser $httpClient,
        string $shelfId,
        string $title,
    ): array {
        $genres = $this->listGenres($httpClient);
        $genreIds = \count($genres) > 0 ? [(int) $genres[0]['id']] : [1];

        $httpClient->request(
            'POST',
            '/api/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'title' => $title,
                'author' => 'Test Author',
                'shelfId' => $shelfId,
                'genreIds' => $genreIds,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClient->getResponse()->getStatusCode(), 'Book creation must return 201.');

        /** @var array{id: string, title: string} $payload */
        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [
            'id' => $payload['id'],
            'title' => $payload['title'],
        ];
    }
}
