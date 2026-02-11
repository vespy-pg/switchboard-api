<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles for list and show operations on locale and gift list related resources
 * for BASIC and VERIFIED user groups
 */
final class Version_20260131_01 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto
SQL);

        // Config table (singleton row) - if you already created it, merge manually or skip this part.
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app.tbl_db_config
(
    db_config_id       smallint                 NOT NULL,
    share_slug_length  smallint                 NOT NULL DEFAULT 4,
    share_slug_count   bigint                   NOT NULL DEFAULT 0,
    updated_at         timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_tbl_db_config PRIMARY KEY (db_config_id),
    CONSTRAINT chk_tbl_db_config_singleton CHECK (db_config_id = 1),
    CONSTRAINT chk_tbl_db_config_share_slug_length CHECK (share_slug_length >= 4),
    CONSTRAINT chk_tbl_db_config_share_slug_count CHECK (share_slug_count >= 0)
)
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO app.tbl_db_config (db_config_id, share_slug_length, share_slug_count)
VALUES (1, 4, 0)
ON CONFLICT (db_config_id) DO NOTHING
SQL);

        // Registry table that actually enforces uniqueness for the allocator.
        $this->addSql(<<<'SQL'
CREATE TABLE app.tbl_share_slug_registry
(
    share_slug  text                        NOT NULL,
    created_at  timestamp with time zone    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_tbl_share_slug_registry PRIMARY KEY (share_slug)
)
SQL);

        // Reserve function: reads config, generates base57 slug, INSERTs into registry (unique),
        // updates share_slug_count, bumps length when count hits >= 10% of pool, and uses 10 retries x 5 blocks.
        $this->addSql(<<<'SQL'
CREATE OR REPLACE FUNCTION app.fx_share_slug_reserve()
RETURNS text
LANGUAGE plpgsql
AS $$
DECLARE
    -- base57 alphabet: excludes 0, O, o, I, l
    alphabet text := '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    alphabet_length integer := length(alphabet);

    current_length integer;
    current_count bigint;

    pool_size numeric;
    threshold numeric;

    attempt_in_block integer;
    block_index integer;

    candidate text;
    position integer;
BEGIN
    -- Lock config row so concurrent allocators don't race on counters/length changes.
    SELECT share_slug_length, share_slug_count
    INTO current_length, current_count
    FROM app.tbl_db_config
    WHERE db_config_id = 1
    FOR UPDATE;

    IF current_length IS NULL OR current_length < 4 THEN
        RAISE EXCEPTION 'Invalid share_slug_length in db_config';
    END IF;

    -- Your policy: 10 retries, repeated 5 times (total 50). After each 10:
    -- check if >=10% of pool, then bump length by 1 and reset count.
    FOR block_index IN 1..5 LOOP
        FOR attempt_in_block IN 1..10 LOOP
            -- Generate candidate of current_length
            candidate := '';
            WHILE length(candidate) < current_length LOOP
                position := 1 + (get_byte(gen_random_bytes(1), 0) % alphabet_length);
                candidate := candidate || substr(alphabet, position, 1);
            END LOOP;

            BEGIN
                INSERT INTO app.tbl_share_slug_registry (share_slug)
                VALUES (candidate);

                -- Success: update count for current length
                UPDATE app.tbl_db_config
                SET share_slug_count = share_slug_count + 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE db_config_id = 1;

                RETURN candidate;

            EXCEPTION
                WHEN unique_violation THEN
                    -- collision: continue attempts
                    NULL;
            END;
        END LOOP;

        -- After 10 failed attempts, decide whether to grow.
        -- Use count from config (exact, O(1)), not COUNT(*) from huge tables.
        SELECT share_slug_length, share_slug_count
        INTO current_length, current_count
        FROM app.tbl_db_config
        WHERE db_config_id = 1;

        pool_size := power(alphabet_length::numeric, current_length::numeric);
        threshold := pool_size * 0.10;

        IF current_count >= threshold THEN
            UPDATE app.tbl_db_config
            SET share_slug_length = share_slug_length + 1,
                share_slug_count = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE db_config_id = 1;

            -- refresh local vars
            SELECT share_slug_length, share_slug_count
            INTO current_length, current_count
            FROM app.tbl_db_config
            WHERE db_config_id = 1;
        END IF;
    END LOOP;

    RAISE EXCEPTION 'Failed to reserve unique share slug after 50 attempts';
END;
$$
SQL);
    }
}
