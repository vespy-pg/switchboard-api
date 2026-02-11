-- =========================
-- Phase 1: database + role
-- Run as postgres (or superuser) in an admin database (e.g. postgres)
-- =========================

CREATE ROLE switchboard_user WITH LOGIN PASSWORD 'switchboard_pass';

CREATE DATABASE switchboard
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.utf8'
    LC_CTYPE = 'en_US.utf8'
    TABLESPACE = pg_default
    CONNECTION LIMIT = -1;

GRANT CONNECT, TEMPORARY, CREATE ON DATABASE switchboard TO switchboard_user;

-- Allow switchboard_user to see and use objects in app schema
GRANT USAGE ON SCHEMA app TO switchboard_user;

-- Allow switchboard_user to create objects in app schema (optional)
GRANT CREATE ON SCHEMA app TO switchboard_user;

-- Grant privileges on existing tables/sequences

GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA app TO switchboard_user;

-- Ensure future tables/sequences created by postgres are automatically accessible
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA app
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO switchboard_user;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA app
    GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO switchboard_user;