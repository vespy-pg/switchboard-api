<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260211_02 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create device_type and device tables with soft-delete-aware uniqueness';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.device_type
(
    device_type_id      uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    owner_user_id         uuid
        REFERENCES app.tbl_user (user_id)
            ON DELETE SET NULL,
    code                  text,
    label                 text                     NOT NULL,
    config_schema_json    jsonb                    DEFAULT '{}'::jsonb NOT NULL,
    config_defaults_json  jsonb                    DEFAULT '{}'::jsonb NOT NULL,
    created_at            timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at            timestamp with time zone,
    removed_at            timestamp with time zone
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_type_owner_user_id
    ON app.device_type (owner_user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_type_code
    ON app.device_type (code)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS ux_device_type_code_predefined_active
    ON app.device_type (code)
    WHERE owner_user_id IS NULL
      AND removed_at IS NULL
      AND code IS NOT NULL
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS ux_device_type_owner_user_id_label_active
    ON app.device_type (owner_user_id, label)
    WHERE owner_user_id IS NOT NULL
      AND removed_at IS NULL
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.device_type TO switchboard_user
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.device
(
    device_id      uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    owner_user_id            uuid
        REFERENCES app.tbl_user (user_id)
            ON DELETE SET NULL,
    visibility               text                     DEFAULT 'private'::text NOT NULL,
    device_type_id           uuid                     NOT NULL
        REFERENCES app.device_type (device_type_id),
    manufacturer             text,
    model                    text,
    name_short               text                     NOT NULL,
    name_full                text                     NOT NULL,
    size_mm                  numeric(10, 2)           NOT NULL,
    default_terminals_json   jsonb                    DEFAULT '[]'::jsonb NOT NULL,
    config_json              jsonb                    DEFAULT '{}'::jsonb NOT NULL,
    created_at               timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at               timestamp with time zone,
    removed_at               timestamp with time zone,

    CONSTRAINT chk_device_visibility
        CHECK (visibility IN ('private', 'public')),
    CONSTRAINT chk_device_default_terminals_json_array
        CHECK (jsonb_typeof(default_terminals_json) = 'array')
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_owner_user_id
    ON app.device (owner_user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_device_type_id
    ON app.device (device_type_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_visibility
    ON app.device (visibility)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_owner_user_id_visibility
    ON app.device (owner_user_id, visibility)
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.device TO switchboard_user
SQL);
    }
}
