<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260120_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add background processing lock columns for link preview jobs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_processing_locked_until timestamp with time zone
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN preview_processing_lock_token uuid
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_gift_list_item_link_processing_locked_until
ON app.tbl_gift_list_item_link (preview_processing_locked_until)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DROP INDEX idx_tbl_gift_list_item_link_processing_locked_until
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_processing_lock_token
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN preview_processing_locked_until
SQL);
    }
}
