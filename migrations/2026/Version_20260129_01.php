<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version_20260129_01 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tbl_person (owned contacts/people) with optional link to tbl_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app.tbl_person
(
    person_id           uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    created_by_user_id  uuid                                               NOT NULL
        REFERENCES app.tbl_user
            ON DELETE CASCADE,
    display_name        varchar(255)                                       NOT NULL,
    linked_user_id      uuid
        REFERENCES app.tbl_user
            ON DELETE SET NULL,
    metadata            jsonb,
    created_at          timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at     timestamp with time zone,
    removed_at          timestamp with time zone
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_person_created_by_user_id
    ON app.tbl_person (created_by_user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_person_linked_user_id
    ON app.tbl_person (linked_user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_person_removed_at
    ON app.tbl_person (removed_at)
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.tbl_person TO gift_user
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE app.tbl_event
(
    event_id            uuid                     DEFAULT gen_random_uuid() NOT NULL
        PRIMARY KEY,
    created_by_user_id  uuid                                               NOT NULL
        REFERENCES app.tbl_user
            ON DELETE CASCADE,
    display_name        varchar(255)                                       NOT NULL,
    event_date          date,
    metadata            jsonb,
    created_at          timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at     timestamp with time zone,
    removed_at          timestamp with time zone
)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_event_created_by_user_id
    ON app.tbl_event (created_by_user_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_event_event_date
    ON app.tbl_event (event_date)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_event_removed_at
    ON app.tbl_event (removed_at)
SQL);

        $this->addSql(<<<'SQL'
GRANT DELETE, INSERT, REFERENCES, SELECT, TRUNCATE, UPDATE ON app.tbl_event TO gift_user
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list
    ADD COLUMN owner_person_id uuid
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list
    ADD COLUMN event_id uuid
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list
    ADD CONSTRAINT fk_tbl_gift_list_owner_person_id
        FOREIGN KEY (owner_person_id)
        REFERENCES app.tbl_person (person_id)
        ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list
    ADD CONSTRAINT fk_tbl_gift_list_event_id
        FOREIGN KEY (event_id)
        REFERENCES app.tbl_event (event_id)
        ON DELETE SET NULL
SQL);

        // Exactly one subject must be set:
        // - owner_user_id (list is about a real user)
        // - owner_person_id (list is about a non-user person)
        // - event_id (list is about an event)
        $this->addSql(<<<'SQL'
ALTER TABLE app.tbl_gift_list
    ADD CONSTRAINT chk_tbl_gift_list_exactly_one_subject
        CHECK (num_nonnulls(owner_user_id, owner_person_id, event_id) = 1)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_gift_list_owner_person_id
    ON app.tbl_gift_list (owner_person_id)
SQL);

        $this->addSql(<<<'SQL'
CREATE INDEX idx_tbl_gift_list_event_id
    ON app.tbl_gift_list (event_id)
SQL);
    }
}
