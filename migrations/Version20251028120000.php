<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create books, genres, and book_genre tables with book_source_enum type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE book_source_enum AS ENUM ('manual', 'ai_recommendation')");

        $this->addSql(<<<'SQL'
            CREATE TABLE genres (
                id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX genres_name_ci_unique ON genres (LOWER(name))');

        $this->addSql(<<<'SQL'
            CREATE TABLE books (
                id UUID NOT NULL,
                title VARCHAR(255) NOT NULL,
                author VARCHAR(255) NOT NULL,
                isbn VARCHAR(13) DEFAULT NULL,
                page_count INT DEFAULT NULL,
                source book_source_enum NOT NULL DEFAULT 'manual',
                recommendation_id BIGINT DEFAULT NULL,
                shelf_id UUID NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX books_shelf_id_idx ON books (shelf_id)');
        $this->addSql("COMMENT ON COLUMN books.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN books.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN books.updated_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql(<<<'SQL'
            CREATE TABLE book_genre (
                book_id UUID NOT NULL,
                genre_id INT NOT NULL,
                PRIMARY KEY(book_id, genre_id)
            )
        SQL);

        $this->addSql("COMMENT ON COLUMN book_genre.book_id IS '(DC2Type:uuid)'");
        $this->addSql('CREATE INDEX book_genre_book_id_idx ON book_genre (book_id)');
        $this->addSql('CREATE INDEX book_genre_genre_id_idx ON book_genre (genre_id)');

        $this->addSql('ALTER TABLE books ADD CONSTRAINT fk_books_shelf_id FOREIGN KEY (shelf_id) REFERENCES shelves (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE book_genre ADD CONSTRAINT fk_book_genre_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE book_genre ADD CONSTRAINT fk_book_genre_genre FOREIGN KEY (genre_id) REFERENCES genres (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_genre DROP CONSTRAINT fk_book_genre_book');
        $this->addSql('ALTER TABLE book_genre DROP CONSTRAINT fk_book_genre_genre');
        $this->addSql('ALTER TABLE books DROP CONSTRAINT fk_books_shelf_id');

        $this->addSql('DROP TABLE book_genre');
        $this->addSql('DROP TABLE books');
        $this->addSql('DROP TABLE genres');

        $this->addSql('DROP TYPE book_source_enum');
    }
}
