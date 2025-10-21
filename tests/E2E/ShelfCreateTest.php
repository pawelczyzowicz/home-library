<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Bridge\Doctrine\ManagerRegistry as SymfonyManagerRegistry;
use Symfony\Component\Panther\PantherTestCase;

final class ShelfCreateTest extends PantherTestCase
{
    private function getDoctrine(): SymfonyManagerRegistry
    {
        /** @var SymfonyManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');

        return $doctrine;
    }

    public function testCreateShelfPersistsInDatabase(): void
    {
        $doctrine = $this->getDoctrine();
        $conn = $doctrine->getConnection();

        $before = (int) $conn->fetchOne('SELECT COUNT(*) FROM shelves');

        $http = static::createHttpBrowserClient();
        $payload = ['name' => 'E2E Shelf ' . bin2hex(random_bytes(3))];
        $http->request('POST', '/api/shelves', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->assertSame(201, $http->getResponse()->getStatusCode());

        $after = (int) $conn->fetchOne('SELECT COUNT(*) FROM shelves');
        $this->assertSame($before + 1, $after, 'Row count in shelves should increase by 1');
    }
}
