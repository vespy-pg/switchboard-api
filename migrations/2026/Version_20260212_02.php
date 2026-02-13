<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260212_02 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add poles column to device table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.device
    ADD COLUMN IF NOT EXISTS poles integer
SQL);
    }
}
