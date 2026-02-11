INSERT INTO app.tbl_group (group_code, group_name, is_active)
VALUES ('BASIC', 'Basic', true);

INSERT INTO app.tbl_group (group_code, group_name, is_active)
VALUES ('VERIFIED', 'Verified', true);

INSERT INTO app.tbl_group (group_code, group_name, is_active)
VALUES ('ADMIN', 'Admin', true);

-- SELECT app.add_role(
--                'ROLE_GIFT_LIST_SHOW',
--                ARRAY['BASIC', 'VERIFIED'],
--                ARRAY['UI', 'API']
--        );


INSERT INTO app.tbl_user (user_id, email, password_hash, first_name, avatar_url, is_verified,
                          email_verified_at, last_login_at, metadata,
                          created_at, last_updated_at, removed_at)
VALUES (DEFAULT, 'admin@example.com', '$2y$13$h.JLTpnZPjMGDcfV1.FCfefx5YCfXwH8JRKfPbGIDl7dkvtBM.YZ2', 'Admin', null,
        true, '2025-12-31 13:53:22.000000 +00:00', null, null,
        '2025-12-31 13:53:22.000000 +00:00', null, null);

INSERT INTO app.tbl_user_group (user_id, group_code)
VALUES ((SELECT user_id from app.tbl_user WHERE email = 'admin@example.com'), 'ADMIN');