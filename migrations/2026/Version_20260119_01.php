<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260119_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preview cache columns (jsonb + status + fetched/expires timestamps) to app.tbl_gift_list_item_link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_json jsonb
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_status varchar(30)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_fetched_at timestamp with time zone
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_expires_at timestamp with time zone
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_gift_list_item_link_preview_expires_at
ON app.tbl_gift_list_item_link (preview_expires_at)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DROP INDEX idx_tbl_gift_list_item_link_preview_expires_at
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_expires_at
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_fetched_at
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_status
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_json
SQL);
    }
}
