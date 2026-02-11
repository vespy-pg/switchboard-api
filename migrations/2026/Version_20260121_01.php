<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260121_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link host table (favicon + preview flag) and FK from gift list item link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app.tbl_link_host
(
    link_host_id        uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    host                varchar(255)                                        NOT NULL,
    is_preview_enabled  boolean                  DEFAULT TRUE              NOT NULL,
    favicon_url         varchar(800),
    created_at          timestamp with time zone DEFAULT CURRENT_TIMESTAMP  NOT NULL,
    last_updated_at     timestamp with time zone,
    removed_at          timestamp with time zone
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX uq_tbl_link_host_host
    ON app.tbl_link_host (host)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_link_host_removed_at
    ON app.tbl_link_host (removed_at)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN link_host_id uuid
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD CONSTRAINT fk_tbl_gift_list_item_link_link_host_id
FOREIGN KEY (link_host_id)
REFERENCES app.tbl_link_host (link_host_id)
ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_gift_list_item_link_link_host_id
    ON app.tbl_gift_list_item_link (link_host_id)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DROP INDEX idx_tbl_gift_list_item_link_link_host_id
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP CONSTRAINT fk_tbl_gift_list_item_link_link_host_id
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN link_host_id
SQL);

        $this->addSql(<<<'SQL'
DROP INDEX idx_tbl_link_host_removed_at
SQL);

        $this->addSql(<<<'SQL'
DROP INDEX uq_tbl_link_host_host
SQL);

        $this->addSql(<<<'SQL'
DROP TABLE app.tbl_link_host
SQL);
    }
}
