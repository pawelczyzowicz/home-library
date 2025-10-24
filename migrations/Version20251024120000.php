<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table with UUID PK, email uniqueness (case-insensitive), roles JSON column, and timestamps.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                email VARCHAR(255) NOT NULL CHECK (char_length(email) BETWEEN 3 AND 255),
                password_hash TEXT NOT NULL,
                roles JSONB NOT NULL DEFAULT '[]'::jsonb,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX users_email_lower_uindex ON users (LOWER(email))');
        $this->addSql('CREATE INDEX users_created_at_idx ON users (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS users_email_lower_uindex');
        $this->addSql('DROP INDEX IF EXISTS users_created_at_idx');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
