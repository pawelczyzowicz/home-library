<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add library_id FK to shelves and books tables for multi-library isolation.';
    }

    public function up(Schema $schema): void
    {
        // Ensure at least one library exists for assigning orphan shelves/books
        $this->addSql(<<<'SQL'
            INSERT INTO libraries (id, name, password_hash, created_at, updated_at)
            SELECT gen_random_uuid(), '__migration_default__', '$2y$13$defaulthashplaceholder000000000000000000000000000000', NOW(), NOW()
            WHERE NOT EXISTS (SELECT 1 FROM libraries LIMIT 1)
              AND (EXISTS (SELECT 1 FROM shelves LIMIT 1) OR EXISTS (SELECT 1 FROM books LIMIT 1))
        SQL);

        // --- SHELVES ---
        // Add nullable library_id first
        $this->addSql('ALTER TABLE shelves ADD COLUMN library_id UUID DEFAULT NULL');

        // Assign existing shelves to the default library (or first available)
        $this->addSql(<<<'SQL'
            UPDATE shelves SET library_id = (SELECT id FROM libraries LIMIT 1)
            WHERE library_id IS NULL
        SQL);

        // Make NOT NULL + FK + INDEX
        $this->addSql('ALTER TABLE shelves ALTER COLUMN library_id SET NOT NULL');
        $this->addSql('ALTER TABLE shelves ADD CONSTRAINT fk_shelves_library_id FOREIGN KEY (library_id) REFERENCES libraries (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX shelves_library_id_idx ON shelves (library_id)');

        // Change UNIQUE on name to composite UNIQUE (library_id, name)
        $this->addSql('DROP INDEX IF EXISTS shelves_name_ci_unique');
        $this->addSql('DROP INDEX IF EXISTS shelves_name_unique');
        $this->addSql('DROP INDEX IF EXISTS uniq_shelves_name');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_2AF005855E237E06');
        $this->addSql('CREATE UNIQUE INDEX shelves_library_name_unique ON shelves (library_id, LOWER(name))');

        // --- BOOKS ---
        // Add nullable library_id first
        $this->addSql('ALTER TABLE books ADD COLUMN library_id UUID DEFAULT NULL');

        // Assign existing books to the default library (or first available)
        $this->addSql(<<<'SQL'
            UPDATE books SET library_id = (SELECT id FROM libraries LIMIT 1)
            WHERE library_id IS NULL
        SQL);

        // Make NOT NULL + FK + INDEX
        $this->addSql('ALTER TABLE books ALTER COLUMN library_id SET NOT NULL');
        $this->addSql('ALTER TABLE books ADD CONSTRAINT fk_books_library_id FOREIGN KEY (library_id) REFERENCES libraries (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX books_library_id_idx ON books (library_id)');
    }

    public function down(Schema $schema): void
    {
        // Books
        $this->addSql('ALTER TABLE books DROP CONSTRAINT IF EXISTS fk_books_library_id');
        $this->addSql('DROP INDEX IF EXISTS books_library_id_idx');
        $this->addSql('ALTER TABLE books DROP COLUMN IF EXISTS library_id');

        // Shelves
        $this->addSql('DROP INDEX IF EXISTS shelves_library_name_unique');
        $this->addSql('ALTER TABLE shelves DROP CONSTRAINT IF EXISTS fk_shelves_library_id');
        $this->addSql('DROP INDEX IF EXISTS shelves_library_id_idx');
        $this->addSql('ALTER TABLE shelves DROP COLUMN IF EXISTS library_id');
        $this->addSql('CREATE UNIQUE INDEX shelves_name_ci_unique ON shelves (LOWER(name))');
    }
}
