<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260213_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create device_type_category table (code PK), add roles and link device_type to category code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.device_type_category
(
    code                    varchar(100)                                     NOT NULL
        PRIMARY KEY,
    label                   text                                             NOT NULL
)
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.device_type_category TO switchboard_user
SQL);

        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_CATEGORY_SHOW', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");
        $this->addSql("SELECT app.add_role('ROLE_DEVICE_TYPE_CATEGORY_LIST', ARRAY['VERIFIED'], ARRAY['UI', 'API']);");

        $this->addSql(<<<'SQL'
INSERT INTO app.device_type_category (code, label)
VALUES
    ('PROTECTION_OVERCURRENT', 'PROTECTION_OVERCURRENT'),
    ('PROTECTION_RCD', 'PROTECTION_RCD'),
    ('PROTECTION_SPD', 'PROTECTION_SPD'),
    ('SWITCHING', 'SWITCHING'),
    ('MEASUREMENT', 'MEASUREMENT'),
    ('DISTRIBUTION', 'DISTRIBUTION'),
    ('POWER_SUPPLY', 'POWER_SUPPLY'),
    ('AUTOMATION', 'AUTOMATION'),
    ('TERMINAL', 'TERMINAL')
ON CONFLICT DO NOTHING
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.device_type
    ADD COLUMN IF NOT EXISTS device_type_category_code varchar(100)
SQL);

        $this->addSql(<<<'SQL'
UPDATE app.device_type
SET device_type_category_code = c.code
FROM app.device_type_category c
WHERE c.code = 'PROTECTION_OVERCURRENT'
  AND app.device_type.device_type_category_code IS NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.device_type
    ADD CONSTRAINT fk_device_type_category
        FOREIGN KEY (device_type_category_code)
            REFERENCES app.device_type_category (code)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX IF NOT EXISTS idx_device_type_device_type_category_code
    ON app.device_type (device_type_category_code)
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.device_type
    ALTER COLUMN device_type_category_code SET NOT NULL
SQL);
    }
}
