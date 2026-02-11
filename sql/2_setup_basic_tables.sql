-- =========================
-- Phase 2: extensions + schema + tables
-- Run while connected to database: switchboard
-- =========================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Verify the extension is installed
SELECT extname, extversion FROM pg_extension WHERE extname = 'pgcrypto';

CREATE SCHEMA IF NOT EXISTS app AUTHORIZATION postgres;

-- Optional but recommended: allow switchboard_user to use the schema
GRANT USAGE, CREATE ON SCHEMA app TO switchboard_user;


-- =========================
-- Users
-- =========================

CREATE TABLE IF NOT EXISTS app.tbl_user
(
    user_id            uuid        DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,

    email              varchar(180),
    password_hash      varchar(255),

    first_name         varchar(100),
    last_name         varchar(100),
    user_alias         varchar(100),
    avatar_url         varchar(500),

    is_verified        boolean     DEFAULT false NOT NULL,
    email_verified_at  timestamptz,

    last_login_at      timestamptz,

    metadata           jsonb,

    created_at         timestamptz   DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at    timestamptz,
    removed_at         timestamptz,

    CONSTRAINT chk_user_email_lowercase
        CHECK (email IS NULL OR email = lower(email))
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_user_email_active
    ON app.tbl_user (email)
    WHERE removed_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_user_alias_active
    ON app.tbl_user (user_alias)
    WHERE removed_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_user_removed_at
    ON app.tbl_user (removed_at);

-- =========================
-- Sessions
-- =========================
CREATE TABLE IF NOT EXISTS app.tbl_user_session
(
    user_session_id       uuid      DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    user_id               uuid                                NOT NULL REFERENCES app.tbl_user (user_id) ON DELETE CASCADE,

    token_hash    varchar(128)                        NOT NULL,

    device_name           varchar(100),
    device_type           varchar(50),
    user_agent            text,
    ip_address            inet,

    is_trusted            boolean   DEFAULT false             NOT NULL,

    last_used_at          timestamptz,
    last_ip_address       inet,

    expires_at            timestamptz                           NOT NULL,
    revoked_at            timestamptz,

    metadata              jsonb,

    created_at            timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at       timestamptz,
    removed_at            timestamptz,

    CONSTRAINT chk_user_session_valid_expiration
        CHECK (expires_at > created_at)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_user_session_token_hash_active
    ON app.tbl_user_session (token_hash)
    WHERE removed_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_user_session_user_id
    ON app.tbl_user_session (user_id);

CREATE INDEX IF NOT EXISTS idx_tbl_user_session_expires_at
    ON app.tbl_user_session (expires_at);

CREATE INDEX IF NOT EXISTS idx_tbl_user_session_revoked_at
    ON app.tbl_user_session (revoked_at);

CREATE INDEX IF NOT EXISTS idx_tbl_user_session_removed_at
    ON app.tbl_user_session (removed_at);

-- =========================
-- Auth challenge
-- =========================
CREATE TABLE IF NOT EXISTS app.tbl_auth_challenge
(
    auth_challenge_id   uuid      DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,

    user_id             uuid                                NOT NULL REFERENCES app.tbl_user (user_id) ON DELETE CASCADE,

    challenge_type      varchar(50)                         NOT NULL,
    secret_hash         varchar(128)                        NOT NULL,

    expires_at          timestamptz                           NOT NULL,
    used_at             timestamptz,

    attempt_count       integer   DEFAULT 0                 NOT NULL,
    last_attempt_at     timestamptz,
    ip_address          inet,
    user_agent          text,

    created_at          timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at     timestamptz,
    removed_at          timestamptz,

    CONSTRAINT chk_auth_challenge_valid_expiration
        CHECK (expires_at > created_at)
);

CREATE INDEX IF NOT EXISTS idx_tbl_auth_challenge_user_id
    ON app.tbl_auth_challenge (user_id);

CREATE INDEX IF NOT EXISTS idx_tbl_auth_challenge_type
    ON app.tbl_auth_challenge (challenge_type);

CREATE INDEX IF NOT EXISTS idx_tbl_auth_challenge_expires_at
    ON app.tbl_auth_challenge (expires_at);

CREATE INDEX IF NOT EXISTS idx_tbl_auth_challenge_used_at
    ON app.tbl_auth_challenge (used_at);

CREATE INDEX IF NOT EXISTS idx_tbl_auth_challenge_removed_at
    ON app.tbl_auth_challenge (removed_at);

-- =========================
-- Social identities
-- =========================
CREATE TABLE IF NOT EXISTS app.tbl_user_identity
(
    user_identity_id          uuid      DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    user_id                   uuid                                NOT NULL REFERENCES app.tbl_user (user_id) ON DELETE CASCADE,

    provider                  varchar(50)                         NOT NULL,
    provider_subject          varchar(255)                        NOT NULL,

    provider_email            varchar(180),
    provider_email_verified   boolean   DEFAULT false             NOT NULL,

    last_used_at              timestamptz,

    created_at                timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at           timestamptz,
    removed_at                timestamptz,

    CONSTRAINT chk_user_identity_email_lowercase
        CHECK (provider_email IS NULL OR provider_email = lower(provider_email))
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_user_identity_provider_subject_active
    ON app.tbl_user_identity (provider, provider_subject)
    WHERE removed_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_tbl_user_identity_user_provider_active
    ON app.tbl_user_identity (user_id, provider)
    WHERE removed_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tbl_user_identity_user_id
    ON app.tbl_user_identity (user_id);

CREATE INDEX IF NOT EXISTS idx_tbl_user_identity_removed_at
    ON app.tbl_user_identity (removed_at);

-- =========================
-- RBAC tables
-- =========================
CREATE TABLE IF NOT EXISTS app.tbl_role
(
    role_code   varchar(100)          NOT NULL PRIMARY KEY,
    scope_api   boolean DEFAULT false NOT NULL,
    scope_ui    boolean DEFAULT false NOT NULL
);

CREATE TABLE IF NOT EXISTS app.tbl_group
(
    group_code  varchar(100)         NOT NULL PRIMARY KEY,
    group_name  varchar(255)         NOT NULL,
    is_active   boolean DEFAULT true NOT NULL
);

CREATE TABLE IF NOT EXISTS app.tbl_user_group
(
    user_group_id  uuid      DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    user_id        uuid                 NOT NULL REFERENCES app.tbl_user (user_id) ON DELETE CASCADE,
    group_code     varchar(100)         NOT NULL REFERENCES app.tbl_group (group_code) ON DELETE CASCADE,
    is_active      boolean DEFAULT true NOT NULL,

    CONSTRAINT ux_tbl_user_group_unique
        UNIQUE (user_id, group_code)
);

CREATE INDEX IF NOT EXISTS idx_tbl_user_group_user_id
    ON app.tbl_user_group (user_id);

CREATE INDEX IF NOT EXISTS idx_tbl_user_group_group_code
    ON app.tbl_user_group (group_code);

CREATE TABLE IF NOT EXISTS app.tbl_role_group
(
    role_group_id  uuid      DEFAULT gen_random_uuid() NOT NULL PRIMARY KEY,
    group_code     varchar(100)  NOT NULL REFERENCES app.tbl_group (group_code) ON DELETE CASCADE,
    role_code      varchar(100)  NOT NULL REFERENCES app.tbl_role (role_code) ON DELETE CASCADE,

    CONSTRAINT ux_tbl_role_group_unique
        UNIQUE (group_code, role_code)
);

CREATE INDEX IF NOT EXISTS idx_tbl_role_group_group_code
    ON app.tbl_role_group (group_code);

CREATE INDEX IF NOT EXISTS idx_tbl_role_role_group_code
    ON app.tbl_role_group (role_code);

-- =========================
-- switchboard lists and clusters
-- =========================

CREATE OR REPLACE FUNCTION app.add_role(
    p_role_code varchar,
    p_group_codes varchar[],
    p_scopes varchar[]
)
    RETURNS void
    LANGUAGE plpgsql
AS $$
DECLARE
    normalizedScopes text[];
    scopeUi boolean;
    scopeApi boolean;
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

    -- Upsert role
    INSERT INTO app.tbl_role (role_code, scope_ui, scope_api)
    VALUES (p_role_code, scopeUi, scopeApi)
    ON CONFLICT (role_code)
        DO UPDATE SET
                      scope_ui = EXCLUDED.scope_ui,
                      scope_api = EXCLUDED.scope_api;

    -- Assign role to groups (ADMIN guaranteed)
    INSERT INTO app.tbl_role_group (group_code, role_code)
    SELECT groupCode, p_role_code
    FROM unnest(effectiveGroupCodes) AS groupCode
    ON CONFLICT ON CONSTRAINT ux_tbl_role_group_unique
        DO NOTHING;
END;
$$;

GRANT EXECUTE ON FUNCTION app.add_role(varchar, varchar[], varchar[]) TO switchboard_user;

GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES ON ALL TABLES IN SCHEMA app TO switchboard_user;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA app TO switchboard_user;

-- Future tables/sequences created by postgres in schema app
ALTER DEFAULT PRIVILEGES FOR ROLE aptvision IN SCHEMA app
    GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES ON TABLES TO switchboard_user;

ALTER DEFAULT PRIVILEGES FOR ROLE aptvision IN SCHEMA app
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO switchboard_user;
