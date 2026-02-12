<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260211_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create projects and switchboards tables with soft-delete-aware uniqueness';
    }

    public function up(Schema $schema): void
    {
        // =============================
        // Projects table
        // =============================
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.tbl_project
(
    project_id      uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    user_id         uuid                                               NOT NULL
        REFERENCES app.tbl_user (user_id)
            ON DELETE CASCADE,
    name            varchar(255)                                       NOT NULL,
    created_at      timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at      timestamp with time zone,
    removed_at      timestamp with time zone
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_tbl_project_user_id
    ON app.tbl_project (user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_tbl_project_removed_at
    ON app.tbl_project (removed_at)
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.tbl_project TO switchboard_user
SQL);

        // =============================
        // Switchboards table
        // =============================
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.tbl_switchboard
(
    switchboard_id  uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    project_id      uuid                                               NOT NULL
        REFERENCES app.tbl_project (project_id)
            ON DELETE CASCADE,
    name            varchar(255)                                       NOT NULL,
    content_json    jsonb                    DEFAULT '{}'::jsonb       NOT NULL,
    version         integer                  DEFAULT 1                 NOT NULL,
    created_at      timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at      timestamp with time zone,
    removed_at      timestamp with time zone,
    
    CONSTRAINT chk_tbl_switchboard_version_positive
        CHECK (version > 0)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_tbl_switchboard_project_id
    ON app.tbl_switchboard (project_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_tbl_switchboard_removed_at
    ON app.tbl_switchboard (removed_at)
SQL);

        // Soft-delete-aware unique constraint: allow name reuse after soft delete
        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_switchboard_project_id_name_active
    ON app.tbl_switchboard (project_id, name)
    WHERE removed_at IS NULL
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.tbl_switchboard TO switchboard_user
SQL);
    }
}
