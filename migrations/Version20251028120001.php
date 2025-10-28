<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251028120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed initial genres';
    }

    public function up(Schema $schema): void
    {
        $genres = [
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

        foreach ($genres as [$id, $name]) {
            $this->addSql('INSERT INTO genres (id, name) VALUES (?, ?) ON CONFLICT (id) DO NOTHING', [$id, $name]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM genres');
    }
}
