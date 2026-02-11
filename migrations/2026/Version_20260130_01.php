<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260130_01 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql("SELECT app.add_role('ROLE_PERSON_SHOW', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_PERSON_LIST', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_PERSON_CREATE', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_PERSON_UPDATE', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
        $this->addSql("SELECT app.add_role('ROLE_PERSON_DELETE', ARRAY['BASIC', 'VERIFIED'], ARRAY['API', 'UI'])");
    }
}
