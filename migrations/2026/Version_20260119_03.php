<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260119_03 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preview cache columns (jsonb + status + fetched/expires timestamps) to app.tbl_gift_list_item_link';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
ADD COLUMN gift_list_item_link_description varchar(255)
SQL);


    }

    public function down(Schema $schema): void
    {

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list_item_link
DROP COLUMN gift_list_item_link_description
SQL);
    }
}
