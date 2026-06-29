<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class LibrarySecurityE2ETest extends E2ETestCase
{
    public function testUserCannotDeleteBookFromAnotherLibrary(): void
    {
        [$httpClientA, $httpClientB, $bookAId] = $this->setupTwoLibrariesWithData();

        // User B attempts to DELETE User A's book → 404
        $httpClientB->request('DELETE', '/api/books/' . $bookAId);
        self::assertSame(404, $httpClientB->getResponse()->getStatusCode(), 'DELETE book from another library must return 404.');
    }

    public function testUserCannotDeleteShelfFromAnotherLibrary(): void
    {
        [$httpClientA, $httpClientB, , $shelfAId] = $this->setupTwoLibrariesWithData();

        // User B attempts to DELETE User A's shelf → 404
        $httpClientB->request('DELETE', '/api/shelves/' . $shelfAId);
        self::assertSame(404, $httpClientB->getResponse()->getStatusCode(), 'DELETE shelf from another library must return 404.');
    }

    public function testUserCannotCreateBookWithShelfFromAnotherLibrary(): void
    {
        [$httpClientA, $httpClientB, , $shelfAId] = $this->setupTwoLibrariesWithData();

        $genres = $this->listGenres($httpClientB);
        $genreIds = \count($genres) > 0 ? [(int) $genres[0]['id']] : [1];

        // User B attempts to create a book using User A's shelf → 404 (shelf not found in B's library)
        $httpClientB->request(
            'POST',
            '/api/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'title' => 'Malicious Book',
                'author' => 'Evil Author',
                'shelfId' => $shelfAId,
                'genreIds' => $genreIds,
            ], \JSON_THROW_ON_ERROR),
        );

        $status = $httpClientB->getResponse()->getStatusCode();
        self::assertContains($status, [404, 422], \sprintf(
            'Creating book with shelf from another library should return 404 or 422, got %d.',
            $status,
        ));
    }

    public function testManipulatedUuidReturnsNotFound(): void
    {
        $email = 'e2e-sec-uuid-' . bin2hex(random_bytes(4)) . '@example.com';
        $httpClient = $this->registerUser(null, $email, 'SecurePass123!');

        // Random UUID that doesn't belong to any library
        $fakeBookId = '00000000-0000-7000-a000-000000000001';
        $fakeShelfId = '00000000-0000-7000-a000-000000000002';

        $httpClient->request('DELETE', '/api/books/' . $fakeBookId);
        self::assertSame(404, $httpClient->getResponse()->getStatusCode(), 'DELETE non-existent book must return 404.');

        $httpClient->request('DELETE', '/api/shelves/' . $fakeShelfId);
        self::assertSame(404, $httpClient->getResponse()->getStatusCode(), 'DELETE non-existent shelf must return 404.');
    }

    /**
     * Sets up two users in separate libraries.
     * User A creates a shelf and a book.
     *
     * @return array{
     *     0: \Symfony\Component\BrowserKit\HttpBrowser,
     *     1: \Symfony\Component\BrowserKit\HttpBrowser,
     *     2: string,
     *     3: string,
     *     4: string,
     * }
     */
    private function setupTwoLibrariesWithData(): array
    {
        $emailA = 'e2e-sec-a-' . bin2hex(random_bytes(4)) . '@example.com';
        $emailB = 'e2e-sec-b-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';
        $libraryNameA = 'Security Lib A ' . bin2hex(random_bytes(4));
        $libraryNameB = 'Security Lib B ' . bin2hex(random_bytes(4));

        // User A: own library with shelf + book
        $httpClientA = $this->registerUser(null, $emailA, $password, $libraryNameA, 'LibPass123!', 'create');
        $shelfA = $this->createShelf($httpClientA, 'Sec Shelf A ' . bin2hex(random_bytes(3)));

        $genres = $this->listGenres($httpClientA);
        $genreIds = \count($genres) > 0 ? [(int) $genres[0]['id']] : [1];

        $httpClientA->request(
            'POST',
            '/api/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'title' => 'Secret Book A',
                'author' => 'Author A',
                'shelfId' => $shelfA['id'],
                'genreIds' => $genreIds,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClientA->getResponse()->getStatusCode(), 'User A book creation must return 201.');

        /** @var array{id: string} $bookPayload */
        $bookPayload = json_decode((string) $httpClientA->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $bookAId = $bookPayload['id'];

        // User B: separate library
        $httpClientB = $this->registerUser(null, $emailB, $password, $libraryNameB, 'LibPass123!', 'create');

        return [$httpClientA, $httpClientB, $bookAId, $shelfA['id'], $libraryNameA];
    }
}
