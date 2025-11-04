<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\BrowserKit\HttpBrowser;

final class BookCreateApiTest extends E2ETestCase
{
    public function testCreateBookViaApiReturnsCreatedResource(): void
    {
        $email = 'e2e-books-' . bin2hex(random_bytes(4)) . '@example.com';
        $password = 'SecurePass123!';

        $httpClient = $this->registerUser(null, $email, $password);

        $shelfId = $this->createShelf($httpClient);

        $title = 'E2E Book ' . bin2hex(random_bytes(4));

        $httpClient->request(
            'POST',
            '/api/books',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'title' => $title,
                'author' => 'Test Author',
                'shelfId' => $shelfId,
                'genreIds' => [1, 2],
                'isbn' => '978-83-1234567-0',
                'pageCount' => 384,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClient->getResponse()->getStatusCode());

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame($title, $payload['title']);
        self::assertSame('Test Author', $payload['author']);
        self::assertSame('9788312345670', $payload['isbn']);
        self::assertSame(384, $payload['pageCount']);
        self::assertSame('manual', $payload['source']);
        self::assertNull($payload['recommendationId']);

        self::assertArrayHasKey('shelf', $payload);
        self::assertSame($shelfId, $payload['shelf']['id']);
        self::assertSame(false, $payload['shelf']['isSystem']);

        self::assertArrayHasKey('genres', $payload);
        $genreIds = array_map(static fn (array $genre): int => (int) $genre['id'], $payload['genres']);
        sort($genreIds);
        self::assertSame([1, 2], $genreIds);

        self::assertArrayHasKey('createdAt', $payload);
        self::assertArrayHasKey('updatedAt', $payload);
    }

    private function createShelf(HttpBrowser $httpClient): string
    {
        $name = 'E2E API Shelf ' . bin2hex(random_bytes(4));

        $httpClient->request(
            'POST',
            '/api/shelves',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => $name], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(201, $httpClient->getResponse()->getStatusCode());

        $payload = json_decode((string) $httpClient->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame($name, $payload['name']);

        return (string) $payload['id'];
    }
}
