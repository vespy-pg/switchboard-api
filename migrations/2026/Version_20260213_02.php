<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260213_02 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default switchboard content_json to one empty row and empty connections';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_switchboard
    ALTER COLUMN content_json SET DEFAULT '{"rows":[{"items":[],"label":"Row 1","rowNumber":1}],"connections":[]}'::jsonb
SQL);
    }
}
