<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260109_03 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
ALTER TABLE app.tbl_gift_list_item
ALTER COLUMN price_from TYPE NUMERIC(22,2)
USING price_from::NUMERIC(22,2)
SQL);

        $this->addSql(<<<SQL
ALTER TABLE app.tbl_gift_list_item
ALTER COLUMN price_to TYPE NUMERIC(22,2)
USING price_to::NUMERIC(22,2)
SQL);
    }
}
