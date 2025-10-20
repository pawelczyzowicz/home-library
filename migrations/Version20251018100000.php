<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251018100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shelves table with case-insensitive unique index on lower(name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shelves (id UUID NOT NULL, name VARCHAR(50) NOT NULL, is_system BOOLEAN DEFAULT FALSE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX shelves_name_ci_unique ON shelves (LOWER(name))');
        $this->addSql("INSERT INTO shelves (id, name, is_system, created_at, updated_at) VALUES ('00000000-0000-0000-0000-000000000001', 'Do zakupu', TRUE, NOW(), NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM shelves WHERE id = '00000000-0000-0000-0000-000000000001'");
        $this->addSql('DROP TABLE shelves');
        $this->addSql('DROP INDEX shelves_name_ci_unique');
    }
}
