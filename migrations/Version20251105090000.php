<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251105090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create AI recommendation events and accept request idempotency tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ai_recommendation_events (
                id SERIAL NOT NULL,
                user_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
                input_titles JSONB NOT NULL,
                recommended_book_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
                accepted_book_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
                model VARCHAR(191) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_ai_rec_events_user_id_created_at ON ai_recommendation_events (user_id, created_at)');
        $this->addSql('ALTER TABLE ai_recommendation_events ADD CONSTRAINT fk_ai_rec_events_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE ai_recommendation_accept_requests (
                id SERIAL NOT NULL,
                event_id INT NOT NULL,
                book_id UUID NOT NULL,
                idempotency_key VARCHAR(128) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_ai_rec_accept_requests_event_id ON ai_recommendation_accept_requests (event_id)');
        $this->addSql('ALTER TABLE ai_recommendation_accept_requests ADD CONSTRAINT fk_ai_rec_accept_requests_event_id FOREIGN KEY (event_id) REFERENCES ai_recommendation_events (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_rec_accept_requests_event_key ON ai_recommendation_accept_requests (event_id, idempotency_key)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_rec_accept_requests_event_book ON ai_recommendation_accept_requests (event_id, book_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_recommendation_accept_requests');
        $this->addSql('DROP TABLE ai_recommendation_events');
    }
}
