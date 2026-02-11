INSERT INTO app.tbl_group (group_code, group_name, is_active)
VALUES ('BASIC_USER', 'Basic User', true);

INSERT INTO app.tbl_group (group_code, group_name, is_active)
VALUES ('VERIFIED_USER', 'Verified User', true);

INSERT INTO app.tbl_role (role_code, scope_api, scope_ui)
VALUES ('ROLE_BASIC_USER', true, true);

INSERT INTO app.tbl_role (role_code, scope_api, scope_ui)
VALUES ('ROLE_VERIFIED_USER', true, true);

INSERT INTO app.tbl_group_role (group_role_id, group_code, role_code)
VALUES (DEFAULT, 'BASIC_USER', 'ROLE_BASIC_USER');

INSERT INTO app.tbl_group_role (group_role_id, group_code, role_code)
VALUES (DEFAULT, 'VERIFIED_USER', 'ROLE_VERIFIED_USER');

INSERT INTO app.tbl_user (user_id, email, password_hash, first_name, last_name, avatar_url, is_active, is_verified,
                          email_verified_at, country_code, language_code, currency_code, last_login_at, metadata,
                          created_at, last_updated_at, removed_at)
VALUES ('703d21d8-ef98-40a3-b363-8c829fbaa423', 'basic@example.com',
        null, null, null, null, true, true,
        null, null, null, null, '2025-12-23 16:31:27.000000', null,
        '2025-12-23 15:06:19.000000', null, null);

INSERT INTO app.tbl_user (user_id, email, password_hash, first_name, last_name, avatar_url, is_active, is_verified,
                          email_verified_at, country_code, language_code, currency_code, last_login_at, metadata,
                          created_at, last_updated_at, removed_at)
VALUES ('703d21d8-ef98-40a3-b363-8c829fbaa424', 'verified@example.com',
        '$2y$13$VgLVx8KKsB86bEDf9rB8ROAII2tdvCIhv1Ue.t3SFM6M2apN65fAu', null, null, null, true, true,
        '2025-12-23 15:06:19.000000', null, null, null, '2025-12-23 16:31:27.000000', null,
        '2025-12-23 15:06:19.000000', null, null);

INSERT INTO app.tbl_user_group (user_group_id, user_id, group_code, is_active)
VALUES (DEFAULT, '703d21d8-ef98-40a3-b363-8c829fbaa423', 'BASIC_USER', true);

INSERT INTO app.tbl_user_group (user_group_id, user_id, group_code, is_active)
VALUES (DEFAULT, '703d21d8-ef98-40a3-b363-8c829fbaa424', 'VERIFIED_USER', true);


INSERT INTO app.tbl_language (language_code, language_name)
VALUES ('en-us', 'United States');

INSERT INTO app.tbl_country (country_code, country_name)
VALUES ('US', 'United States');

INSERT INTO app.tbl_currency (currency_code, currency_name, currency_symbol, minor_units)
VALUES ('USD', 'US Dollar', null, DEFAULT);
