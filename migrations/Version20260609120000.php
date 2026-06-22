<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create libraries table and add library_id FK to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE libraries (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                name VARCHAR(255) NOT NULL,
                password_hash TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX libraries_name_uindex ON libraries (name)');

        // Add nullable library_id to users first
        $this->addSql('ALTER TABLE users ADD COLUMN library_id UUID DEFAULT NULL');

        // Create a default library for existing users
        $this->addSql(<<<'SQL'
            INSERT INTO libraries (id, name, password_hash, created_at, updated_at)
            SELECT gen_random_uuid(), '__default__', '$2y$13$defaulthashplaceholder000000000000000000000000000000', NOW(), NOW()
            WHERE EXISTS (SELECT 1 FROM users LIMIT 1)
        SQL);

        // Assign all existing users to the default library
        $this->addSql(<<<'SQL'
            UPDATE users SET library_id = (SELECT id FROM libraries WHERE name = '__default__' LIMIT 1)
            WHERE library_id IS NULL
        SQL);

        // Make library_id NOT NULL and add FK
        $this->addSql('ALTER TABLE users ALTER COLUMN library_id SET NOT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT fk_users_library_id FOREIGN KEY (library_id) REFERENCES libraries (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX users_library_id_idx ON users (library_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT IF EXISTS fk_users_library_id');
        $this->addSql('DROP INDEX IF EXISTS users_library_id_idx');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS library_id');
        $this->addSql('DROP INDEX IF EXISTS libraries_name_uindex');
        $this->addSql('DROP TABLE IF EXISTS libraries');
    }
}
