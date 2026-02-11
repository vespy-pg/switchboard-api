<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260126_03 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gift currency snapshot and converted share amount to gift list item reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD COLUMN gift_currency_snapshot_code char(3)
        REFERENCES app.tbl_currency
        ON DELETE RESTRICT
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD COLUMN share_amount_in_gift_currency numeric(22, 2)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD CONSTRAINT chk_reservation_amount_snapshot_required
    CHECK (
        share_amount IS NULL
        OR gift_currency_snapshot_code IS NULL
        OR share_amount_in_gift_currency IS NOT NULL
    )
SQL);
    }
}
