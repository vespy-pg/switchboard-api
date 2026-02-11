<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260118_01 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql("SELECT app.add_role('ROLE_GIFT_LIST_ITEM_COMMENT_CREATE', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
    }
}
