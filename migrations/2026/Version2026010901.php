<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update app.add_role() function to be truly additive:
 * - Create role if not exists
 * - Add role-group mappings for all specified groups (whether role is new or existing)
 * - Skip role-group mappings that already exist (fail-safe)
 */
final class Version2026010901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update app.add_role() to be truly additive: preserve existing scopes and add new group mappings (manual execution required)';
    }

    public function up(Schema $schema): void
    {
        // Drop the existing function first (to bypass ownership issues)
        $this->addSql('DROP FUNCTION IF EXISTS app.add_role(character varying, character varying[], character varying[])');
        
        // Recreate the function with additive behavior
        $this->addSql(<<<'SQL'
CREATE FUNCTION app.add_role(
    p_role_code character varying,
    p_group_codes character varying[],
    p_scopes character varying[]
)
RETURNS void
LANGUAGE plpgsql
AS $function$
DECLARE
    normalizedScopes text[];
    scopeUi boolean;
    scopeApi boolean;
    existingScopeUi boolean;
    existingScopeApi boolean;
    effectiveGroupCodes text[];
    missingGroups text[];
BEGIN
    IF p_role_code IS NULL OR btrim(p_role_code) = '' THEN
        RAISE EXCEPTION 'p_role_code cannot be null/empty';
    END IF;

    -- Merge ADMIN into group list (always)
    effectiveGroupCodes := (
        SELECT array_agg(DISTINCT groupCode)
        FROM (
                 SELECT btrim(groupCode) AS groupCode
                 FROM unnest(COALESCE(p_group_codes, ARRAY[]::varchar[])) AS groupCode
                 WHERE btrim(groupCode) <> ''

                 UNION ALL
                 SELECT 'ADMIN'
             ) g
    );

    IF effectiveGroupCodes IS NULL OR array_length(effectiveGroupCodes, 1) IS NULL THEN
        RAISE EXCEPTION 'No valid group codes provided';
    END IF;

    -- Normalize + validate scopes
    normalizedScopes := (
        SELECT COALESCE(array_agg(DISTINCT upper(btrim(scopeValue))), ARRAY[]::text[])
        FROM unnest(COALESCE(p_scopes, ARRAY[]::varchar[])) AS scopeValue
        WHERE btrim(scopeValue) <> ''
    );

    IF EXISTS (
        SELECT 1
        FROM unnest(normalizedScopes) AS scopeValue
        WHERE scopeValue NOT IN ('UI', 'API')
    ) THEN
        RAISE EXCEPTION 'Invalid scope(s): %, allowed: UI, API', normalizedScopes;
    END IF;

    scopeUi := ('UI' = ANY(normalizedScopes));
    scopeApi := ('API' = ANY(normalizedScopes));

    -- Ensure all groups exist (ADMIN included)
    missingGroups := (
        SELECT array_agg(groupCode)
        FROM unnest(effectiveGroupCodes) AS groupCode
        WHERE NOT EXISTS (
            SELECT 1
            FROM app.tbl_group g
            WHERE g.group_code = groupCode
        )
    );

    IF missingGroups IS NOT NULL AND array_length(missingGroups, 1) IS NOT NULL THEN
        RAISE EXCEPTION 'Unknown group_code(s): %', missingGroups;
    END IF;

    -- Get existing scopes if role exists
    SELECT scope_ui, scope_api
    INTO existingScopeUi, existingScopeApi
    FROM app.tbl_role
    WHERE role_code = p_role_code;

    -- Merge scopes: preserve existing scopes and add new ones (additive behavior)
    IF FOUND THEN
        scopeUi := COALESCE(existingScopeUi, false) OR COALESCE(scopeUi, false);
        scopeApi := COALESCE(existingScopeApi, false) OR COALESCE(scopeApi, false);
    END IF;

    -- Upsert role with merged scopes
    INSERT INTO app.tbl_role (role_code, scope_ui, scope_api)
    VALUES (p_role_code, scopeUi, scopeApi)
    ON CONFLICT (role_code)
        DO UPDATE SET
                      scope_ui = EXCLUDED.scope_ui,
                      scope_api = EXCLUDED.scope_api;

    -- Assign role to groups (ADMIN guaranteed) - additive, no removal
    INSERT INTO app.tbl_role_group (group_code, role_code)
    SELECT groupCode, p_role_code
    FROM unnest(effectiveGroupCodes) AS groupCode
    ON CONFLICT ON CONSTRAINT ux_tbl_role_group_unique
        DO NOTHING;
END;
$function$;
SQL);
    }
}
