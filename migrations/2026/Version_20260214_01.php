<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260214_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archived_at column for projects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_project
    ADD COLUMN archived_at timestamp with time zone
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_tbl_project_archived_at
    ON app.tbl_project (archived_at)
SQL);
    }
}
