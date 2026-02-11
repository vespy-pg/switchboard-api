<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260126_02 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fx_rate_date and enforce positive share_amount (and optionally share_percent range 1..100)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD COLUMN fx_rate_date date
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD CONSTRAINT chk_reservation_share_amount_positive
    CHECK (share_amount IS NULL OR share_amount > 0)
SQL);

        // Optional: tighten percent from 0..100 to 1..100 (keeps NULL allowed)
        // If you already have chk_reservation_share_percent_range, we must DROP it first
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    DROP CONSTRAINT chk_reservation_share_percent_range
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_reservation
    ADD CONSTRAINT chk_reservation_share_percent_range
    CHECK (share_percent IS NULL OR (share_percent >= 1 AND share_percent <= 100))
SQL);
    }
}
