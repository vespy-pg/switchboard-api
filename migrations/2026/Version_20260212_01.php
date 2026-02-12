<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260212_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add VERIFIED group roles for project, switchboard, device, and device type actions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SELECT app.add_role('ROLE_PROJECT_SHOW', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_PROJECT_LIST', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_PROJECT_CREATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_PROJECT_UPDATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_PROJECT_DELETE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");

        $this->addSql("SELECT app.add_role('ROLE_SWITCHBOARD_SHOW', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_SWITCHBOARD_LIST', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_SWITCHBOARD_CREATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_SWITCHBOARD_UPDATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_SWITCHBOARD_DELETE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");

        $this->addSql("SELECT app.add_role('ROLE_DEVICE_SHOW', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_LIST', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_CREATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_UPDATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_DELETE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");

        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_SHOW', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_LIST', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_CREATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_UPDATE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_DELETE', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
    }
}
