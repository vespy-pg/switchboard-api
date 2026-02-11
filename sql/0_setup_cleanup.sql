-- Terminate existing connections so DROP DATABASE works reliably
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = 'switchboard'
  AND pid <> pg_backend_pid();

DROP DATABASE IF EXISTS switchboard;

DO $$
    BEGIN
        IF EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'switchboard_user') THEN
            -- If you want to drop user, you must remove privileges/ownership dependencies.
            -- Reassign owned objects just in case (safe even if nothing exists)
            REASSIGN OWNED BY switchboard_user TO postgres;
            DROP OWNED BY switchboard_user;

            DROP ROLE switchboard_user;
        END IF;
    END
$$;