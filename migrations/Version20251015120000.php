<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema from .ai/db-plan.md: users, shelves, books, genres, book_genre, ai_recommendation_events, enums, extensions, triggers, indexes, seeds';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on postgresql.');

        // Extensions
        $this->addSql("CREATE EXTENSION IF NOT EXISTS pgcrypto");
        $this->addSql("CREATE EXTENSION IF NOT EXISTS pg_trgm");

        // Enum type book_source_enum
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'book_source_enum') THEN
        CREATE TYPE book_source_enum AS ENUM ('manual', 'ai_recommendation');
    END IF;
END
$$;
SQL);

        // Tables without FKs first
        $this->addSql(<<<SQL
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (char_length(email) BETWEEN 3 AND 255)
)
SQL);

        $this->addSql(<<<SQL
CREATE TABLE shelves (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) NOT NULL,
    is_system BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
)
SQL);

        $this->addSql(<<<SQL
CREATE TABLE ai_recommendation_events (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    user_id UUID NULL REFERENCES users(id),
    input_titles JSONB NOT NULL,
    recommended_book_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    accepted_book_ids JSONB NOT NULL DEFAULT '[]'::jsonb
)
SQL);

        $this->addSql(<<<SQL
CREATE TABLE genres (
    id INTEGER PRIMARY KEY,
    name VARCHAR(100) NOT NULL
)
SQL);

        // Dependent tables
        $this->addSql(<<<SQL
CREATE TABLE books (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn TEXT NULL,
    page_count INTEGER NULL,
    source book_source_enum NOT NULL DEFAULT 'manual',
    recommendation_id BIGINT NULL REFERENCES ai_recommendation_events(id) ON DELETE SET NULL,
    shelf_id UUID NOT NULL REFERENCES shelves(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (char_length(title) BETWEEN 1 AND 255),
    CHECK (char_length(author) BETWEEN 1 AND 255),
    CHECK (isbn IS NULL OR isbn ~ '^\\d{10}(\\d{3})?$'),
    CHECK (page_count IS NULL OR page_count BETWEEN 1 AND 50000)
)
SQL);

        $this->addSql(<<<SQL
CREATE TABLE book_genre (
    book_id UUID NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    genre_id INTEGER NOT NULL REFERENCES genres(id) ON DELETE RESTRICT,
    PRIMARY KEY (book_id, genre_id)
)
SQL);

        // Indexes
        $this->addSql("CREATE UNIQUE INDEX ux_users_email_ci ON users (lower(email))");
        $this->addSql("CREATE UNIQUE INDEX ux_shelves_name_ci ON shelves (lower(name))");
        $this->addSql("CREATE UNIQUE INDEX ux_genres_name_ci ON genres (lower(name))");
        $this->addSql("CREATE INDEX ix_books_shelf_id ON books (shelf_id)");
        $this->addSql("CREATE INDEX ix_book_genre_book_id ON book_genre (book_id)");
        $this->addSql("CREATE INDEX ix_book_genre_genre_id ON book_genre (genre_id)");

        $this->addSql("CREATE INDEX ix_ai_events_user_created_at ON ai_recommendation_events (user_id, created_at)");
        $this->addSql("CREATE INDEX ix_ai_events_recommended_book_ids_gin ON ai_recommendation_events USING GIN (recommended_book_ids)");
        $this->addSql("CREATE INDEX ix_ai_events_accepted_book_ids_gin ON ai_recommendation_events USING GIN (accepted_book_ids)");

        // Optional trigram indexes for faster ILIKE on title/author
        $this->addSql("CREATE INDEX IF NOT EXISTS ix_books_title_trgm ON books USING GIN (title gin_trgm_ops)");
        $this->addSql("CREATE INDEX IF NOT EXISTS ix_books_author_trgm ON books USING GIN (author gin_trgm_ops)");

        // Triggers: updated_at auto update
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS trigger AS $$
BEGIN
  NEW.updated_at := now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);

        $this->addSql("CREATE TRIGGER trg_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_updated_at()");
        $this->addSql("CREATE TRIGGER trg_shelves_updated_at BEFORE UPDATE ON shelves FOR EACH ROW EXECUTE FUNCTION set_updated_at()");
        $this->addSql("CREATE TRIGGER trg_books_updated_at BEFORE UPDATE ON books FOR EACH ROW EXECUTE FUNCTION set_updated_at()");

        // Protect system shelves from update/delete
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_system_shelf_change()
RETURNS trigger AS $$
BEGIN
  IF TG_OP IN ('UPDATE','DELETE') AND OLD.is_system THEN
    RAISE EXCEPTION 'Shelf is system-protected and cannot be %', lower(TG_OP);
  END IF;
  RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
SQL);

        $this->addSql("CREATE TRIGGER trg_shelves_protect_system_u BEFORE UPDATE ON shelves FOR EACH ROW EXECUTE FUNCTION prevent_system_shelf_change()");
        $this->addSql("CREATE TRIGGER trg_shelves_protect_system_d BEFORE DELETE ON shelves FOR EACH ROW EXECUTE FUNCTION prevent_system_shelf_change()");

        // Seed data: genres and system shelf "Do zakupu"
        $this->addSql(<<<SQL
INSERT INTO genres (id, name) VALUES
    (1, 'kryminał'),
    (2, 'fantasy'),
    (3, 'sensacja'),
    (4, 'romans'),
    (5, 'sci-fi'),
    (6, 'horror'),
    (7, 'biografia'),
    (8, 'historia'),
    (9, 'popularnonaukowa'),
    (10, 'literatura piękna'),
    (11, 'religia'),
    (12, 'thriller'),
    (13, 'dramat'),
    (14, 'poezja'),
    (15, 'komiks')
ON CONFLICT DO NOTHING
SQL);

        $this->addSql(<<<SQL
INSERT INTO shelves (id, name, is_system)
VALUES (gen_random_uuid(), 'Do zakupu', true)
ON CONFLICT DO NOTHING
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on postgresql.');

        // Drop triggers first
        $this->addSql("DROP TRIGGER IF EXISTS trg_shelves_protect_system_d ON shelves");
        $this->addSql("DROP TRIGGER IF EXISTS trg_shelves_protect_system_u ON shelves");
        $this->addSql("DROP TRIGGER IF EXISTS trg_books_updated_at ON books");
        $this->addSql("DROP TRIGGER IF EXISTS trg_shelves_updated_at ON shelves");
        $this->addSql("DROP TRIGGER IF EXISTS trg_users_updated_at ON users");

        // Drop functions
        $this->addSql("DROP FUNCTION IF EXISTS prevent_system_shelf_change()");
        $this->addSql("DROP FUNCTION IF EXISTS set_updated_at()");

        // Drop indexes (automatic on table drop for most, but explicit for safety on enums)
        $this->addSql("DROP INDEX IF EXISTS ix_books_author_trgm");
        $this->addSql("DROP INDEX IF EXISTS ix_books_title_trgm");
        $this->addSql("DROP INDEX IF EXISTS ix_ai_events_accepted_book_ids_gin");
        $this->addSql("DROP INDEX IF EXISTS ix_ai_events_recommended_book_ids_gin");
        $this->addSql("DROP INDEX IF EXISTS ix_ai_events_user_created_at");
        $this->addSql("DROP INDEX IF EXISTS ix_book_genre_genre_id");
        $this->addSql("DROP INDEX IF EXISTS ix_book_genre_book_id");
        $this->addSql("DROP INDEX IF EXISTS ix_books_shelf_id");
        $this->addSql("DROP INDEX IF EXISTS ux_genres_name_ci");
        $this->addSql("DROP INDEX IF EXISTS ux_shelves_name_ci");
        $this->addSql("DROP INDEX IF EXISTS ux_users_email_ci");

        // Drop tables in reverse dependency order
        $this->addSql("DROP TABLE IF EXISTS book_genre");
        $this->addSql("DROP TABLE IF EXISTS books");
        $this->addSql("DROP TABLE IF EXISTS genres");
        $this->addSql("DROP TABLE IF EXISTS ai_recommendation_events");
        $this->addSql("DROP TABLE IF EXISTS shelves");
        $this->addSql("DROP TABLE IF EXISTS users");

        // Drop enum type
        $this->addSql("DO $$ BEGIN IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'book_source_enum') THEN DROP TYPE book_source_enum; END IF; END $$;");

        // Optionally remove extensions
        $this->addSql("DROP EXTENSION IF EXISTS pg_trgm");
        $this->addSql("DROP EXTENSION IF EXISTS pgcrypto");
    }
}



