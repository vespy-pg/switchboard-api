<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260126_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create FX rates table for daily currency conversion lookups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app.tbl_fx_rate
(
    fx_rate_id uuid DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    from_currency_code char(3) NOT NULL REFERENCES app.tbl_currency ON DELETE RESTRICT,
    to_currency_code char(3) NOT NULL REFERENCES app.tbl_currency ON DELETE RESTRICT,
    rate numeric(18, 8) NOT NULL,
    rate_date date NOT NULL,
    fetched_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_fx_rate_currency_pair CHECK (from_currency_code <> to_currency_code),
    CONSTRAINT chk_fx_rate_positive CHECK (rate > 0)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE UNIQUE INDEX ux_tbl_fx_rate_date_pair
    ON app.tbl_fx_rate (rate_date, to_currency_code, to_currency_code)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_fx_rate_pair_date
    ON app.tbl_fx_rate (to_currency_code, to_currency_code, rate_date)
SQL);
    }
}
