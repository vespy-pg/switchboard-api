DO $$
    BEGIN

        IF NOT EXISTS (
                SELECT FROM pg_catalog.pg_roles
                WHERE  rolname = 'aptvision') THEN

            CREATE ROLE aptvision LOGIN;
        END IF;
        IF NOT EXISTS (
                SELECT FROM pg_catalog.pg_roles
                WHERE  rolname = 'gift_user') THEN

            CREATE ROLE gift_user LOGIN;
        END IF;
END $$;
--
-- PostgreSQL database dump
--

-- Dumped from database version 11.16 (Debian 11.16-1.pgdg90+1)
-- Dumped by pg_dump version 12.16 (Ubuntu 12.16-1.pgdg22.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: app; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA app;


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

--
-- Name: tbl_auth_challenge; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_auth_challenge (
    auth_challenge_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    challenge_type character varying(50) NOT NULL,
    secret_hash character varying(128) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    used_at timestamp without time zone,
    attempt_count integer DEFAULT 0 NOT NULL,
    last_attempt_at timestamp without time zone,
    ip_address inet,
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    CONSTRAINT chk_auth_challenge_valid_expiration CHECK ((expires_at > created_at))
);


--
-- Name: tbl_cluster; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_cluster (
    cluster_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    created_by_user_id uuid,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone
);


--
-- Name: tbl_cluster_list; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_cluster_list (
    cluster_list_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    cluster_id uuid NOT NULL,
    list_id uuid NOT NULL,
    added_by_user_id uuid,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone
);


--
-- Name: tbl_country; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_country (
    country_code character(2) NOT NULL,
    country_name character varying(100) NOT NULL
);


--
-- Name: tbl_currency; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_currency (
    currency_code character(3) NOT NULL,
    currency_name character varying(50) NOT NULL,
    currency_symbol character varying(10),
    minor_units smallint DEFAULT 2 NOT NULL
);


--
-- Name: tbl_group; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_group (
    group_code character varying(100) NOT NULL,
    group_name character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: tbl_group_role; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_group_role (
    group_role_id bigint NOT NULL,
    group_code character varying(100) NOT NULL,
    role_code character varying(100) NOT NULL
);


--
-- Name: tbl_group_role_group_role_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.tbl_group_role_group_role_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tbl_group_role_group_role_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.tbl_group_role_group_role_id_seq OWNED BY app.tbl_group_role.group_role_id;


--
-- Name: tbl_language; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_language (
    language_code character varying(10) NOT NULL,
    language_name character varying(100) NOT NULL
);


--
-- Name: tbl_list; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list (
    list_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    list_type character varying(30) NOT NULL,
    display_name character varying(255) NOT NULL,
    share_slug character varying(80) NOT NULL,
    created_by_user_id uuid NOT NULL,
    owner_user_id uuid,
    country_code character(2),
    language_code character varying(10),
    currency_code character(3),
    is_unlisted boolean DEFAULT true NOT NULL,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone
);


--
-- Name: tbl_list_item; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item (
    list_item_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    list_id uuid NOT NULL,
    share_slug character varying(80) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    price_from numeric(12,2),
    price_to numeric(12,2),
    expires_at timestamp without time zone,
    desire integer DEFAULT 50 NOT NULL,
    status_code character varying(50) NOT NULL,
    created_by_user_id uuid NOT NULL,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    CONSTRAINT chk_list_item_desire_range CHECK (((desire >= 0) AND (desire <= 100))),
    CONSTRAINT chk_list_item_price_range CHECK (((price_from IS NULL) OR (price_to IS NULL) OR (price_from <= price_to)))
);


--
-- Name: tbl_list_item_comment; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item_comment (
    list_item_comment_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    list_item_id uuid NOT NULL,
    comment_text text NOT NULL,
    created_by_user_id uuid NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone
);


--
-- Name: tbl_list_item_link; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item_link (
    list_item_link_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    list_item_id uuid NOT NULL,
    link_type character varying(30) NOT NULL,
    provider_code character varying(50),
    url character varying(800) NOT NULL,
    created_by_user_id uuid,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone
);


--
-- Name: tbl_list_item_reservation; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item_reservation (
    list_item_reservation_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    list_item_id uuid NOT NULL,
    reserved_by_user_id uuid NOT NULL,
    reservation_status_code character varying(50) NOT NULL,
    share_percent integer,
    share_amount numeric(12,2),
    currency_code character(3),
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    CONSTRAINT chk_reservation_amount_currency CHECK (((share_amount IS NULL) OR (currency_code IS NOT NULL))),
    CONSTRAINT chk_reservation_share_exclusive CHECK ((((share_percent IS NOT NULL) AND (share_amount IS NULL)) OR ((share_percent IS NULL) AND (share_amount IS NOT NULL)))),
    CONSTRAINT chk_reservation_share_percent_range CHECK (((share_percent IS NULL) OR ((share_percent >= 0) AND (share_percent <= 100))))
);


--
-- Name: tbl_list_item_reservation_status; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item_reservation_status (
    reservation_status_code character varying(50) NOT NULL,
    reservation_status_name character varying(100) NOT NULL,
    is_final boolean DEFAULT false NOT NULL,
    sort_order integer
);


--
-- Name: tbl_list_item_status; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_list_item_status (
    status_code character varying(50) NOT NULL,
    status_name character varying(100) NOT NULL,
    is_final boolean DEFAULT false NOT NULL,
    sort_order integer
);


--
-- Name: tbl_role; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_role (
    role_code character varying(100) NOT NULL,
    scope_api boolean DEFAULT false NOT NULL,
    scope_ui boolean DEFAULT false NOT NULL
);


--
-- Name: tbl_user; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_user (
    user_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    email character varying(180),
    password_hash character varying(255),
    first_name character varying(100),
    last_name character varying(100),
    avatar_url character varying(500),
    is_active boolean DEFAULT true NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    email_verified_at timestamp without time zone,
    country_code character(2),
    language_code character varying(10),
    currency_code character(3),
    last_login_at timestamp without time zone,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    CONSTRAINT chk_user_email_lowercase CHECK (((email IS NULL) OR ((email)::text = lower((email)::text))))
);


--
-- Name: tbl_user_group; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_user_group (
    user_group_id bigint NOT NULL,
    user_id uuid NOT NULL,
    group_code character varying(100) NOT NULL,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: tbl_user_group_user_group_id_seq; Type: SEQUENCE; Schema: app; Owner: -
--

CREATE SEQUENCE app.tbl_user_group_user_group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tbl_user_group_user_group_id_seq; Type: SEQUENCE OWNED BY; Schema: app; Owner: -
--

ALTER SEQUENCE app.tbl_user_group_user_group_id_seq OWNED BY app.tbl_user_group.user_group_id;


--
-- Name: tbl_user_identity; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_user_identity (
    user_identity_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    provider character varying(50) NOT NULL,
    provider_subject character varying(255) NOT NULL,
    provider_email character varying(180),
    provider_email_verified boolean DEFAULT false NOT NULL,
    last_used_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    CONSTRAINT chk_user_identity_email_lowercase CHECK (((provider_email IS NULL) OR ((provider_email)::text = lower((provider_email)::text))))
);


--
-- Name: tbl_user_session; Type: TABLE; Schema: app; Owner: -
--

CREATE TABLE app.tbl_user_session (
    user_session_id uuid DEFAULT public.gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    session_token_hash character varying(128) NOT NULL,
    device_name character varying(100),
    device_type character varying(50),
    user_agent text,
    ip_address inet,
    is_trusted boolean DEFAULT false NOT NULL,
    last_used_at timestamp without time zone,
    last_ip_address inet,
    expires_at timestamp without time zone NOT NULL,
    revoked_at timestamp without time zone,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    last_updated_at timestamp without time zone,
    removed_at timestamp without time zone,
    session_type character varying(30) DEFAULT 'basic'::character varying NOT NULL,
    CONSTRAINT chk_user_session_valid_expiration CHECK ((expires_at > created_at))
);


--
-- Name: doctrine_migration_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.doctrine_migration_versions (
    version character varying(191) NOT NULL,
    executed_at timestamp(0) without time zone DEFAULT NULL::timestamp without time zone,
    execution_time integer
);


--
-- Name: tbl_group_role group_role_id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group_role ALTER COLUMN group_role_id SET DEFAULT nextval('app.tbl_group_role_group_role_id_seq'::regclass);


--
-- Name: tbl_user_group user_group_id; Type: DEFAULT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_group ALTER COLUMN user_group_id SET DEFAULT nextval('app.tbl_user_group_user_group_id_seq'::regclass);


--
-- Name: tbl_auth_challenge tbl_auth_challenge_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_auth_challenge
    ADD CONSTRAINT tbl_auth_challenge_pkey PRIMARY KEY (auth_challenge_id);


--
-- Name: tbl_cluster_list tbl_cluster_list_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster_list
    ADD CONSTRAINT tbl_cluster_list_pkey PRIMARY KEY (cluster_list_id);


--
-- Name: tbl_cluster tbl_cluster_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster
    ADD CONSTRAINT tbl_cluster_pkey PRIMARY KEY (cluster_id);


--
-- Name: tbl_country tbl_country_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_country
    ADD CONSTRAINT tbl_country_pkey PRIMARY KEY (country_code);


--
-- Name: tbl_currency tbl_currency_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_currency
    ADD CONSTRAINT tbl_currency_pkey PRIMARY KEY (currency_code);


--
-- Name: tbl_group tbl_group_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group
    ADD CONSTRAINT tbl_group_pkey PRIMARY KEY (group_code);


--
-- Name: tbl_group_role tbl_group_role_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group_role
    ADD CONSTRAINT tbl_group_role_pkey PRIMARY KEY (group_role_id);


--
-- Name: tbl_language tbl_language_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_language
    ADD CONSTRAINT tbl_language_pkey PRIMARY KEY (language_code);


--
-- Name: tbl_list_item_comment tbl_list_item_comment_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_comment
    ADD CONSTRAINT tbl_list_item_comment_pkey PRIMARY KEY (list_item_comment_id);


--
-- Name: tbl_list_item_link tbl_list_item_link_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_link
    ADD CONSTRAINT tbl_list_item_link_pkey PRIMARY KEY (list_item_link_id);


--
-- Name: tbl_list_item tbl_list_item_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item
    ADD CONSTRAINT tbl_list_item_pkey PRIMARY KEY (list_item_id);


--
-- Name: tbl_list_item_reservation tbl_list_item_reservation_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation
    ADD CONSTRAINT tbl_list_item_reservation_pkey PRIMARY KEY (list_item_reservation_id);


--
-- Name: tbl_list_item_reservation_status tbl_list_item_reservation_status_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation_status
    ADD CONSTRAINT tbl_list_item_reservation_status_pkey PRIMARY KEY (reservation_status_code);


--
-- Name: tbl_list_item_status tbl_list_item_status_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_status
    ADD CONSTRAINT tbl_list_item_status_pkey PRIMARY KEY (status_code);


--
-- Name: tbl_list tbl_list_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_pkey PRIMARY KEY (list_id);


--
-- Name: tbl_role tbl_role_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_role
    ADD CONSTRAINT tbl_role_pkey PRIMARY KEY (role_code);


--
-- Name: tbl_user_group tbl_user_group_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_group
    ADD CONSTRAINT tbl_user_group_pkey PRIMARY KEY (user_group_id);


--
-- Name: tbl_user_identity tbl_user_identity_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_identity
    ADD CONSTRAINT tbl_user_identity_pkey PRIMARY KEY (user_identity_id);


--
-- Name: tbl_user tbl_user_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user
    ADD CONSTRAINT tbl_user_pkey PRIMARY KEY (user_id);


--
-- Name: tbl_user_session tbl_user_session_pkey; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_session
    ADD CONSTRAINT tbl_user_session_pkey PRIMARY KEY (user_session_id);


--
-- Name: tbl_group_role ux_tbl_group_role_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group_role
    ADD CONSTRAINT ux_tbl_group_role_unique UNIQUE (group_code, role_code);


--
-- Name: tbl_user_group ux_tbl_user_group_unique; Type: CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_group
    ADD CONSTRAINT ux_tbl_user_group_unique UNIQUE (user_id, group_code);


--
-- Name: doctrine_migration_versions doctrine_migration_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.doctrine_migration_versions
    ADD CONSTRAINT doctrine_migration_versions_pkey PRIMARY KEY (version);


--
-- Name: idx_tbl_auth_challenge_expires_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_auth_challenge_expires_at ON app.tbl_auth_challenge USING btree (expires_at);


--
-- Name: idx_tbl_auth_challenge_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_auth_challenge_removed_at ON app.tbl_auth_challenge USING btree (removed_at);


--
-- Name: idx_tbl_auth_challenge_type; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_auth_challenge_type ON app.tbl_auth_challenge USING btree (challenge_type);


--
-- Name: idx_tbl_auth_challenge_used_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_auth_challenge_used_at ON app.tbl_auth_challenge USING btree (used_at);


--
-- Name: idx_tbl_auth_challenge_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_auth_challenge_user_id ON app.tbl_auth_challenge USING btree (user_id);


--
-- Name: idx_tbl_cluster_list_cluster_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_cluster_list_cluster_id ON app.tbl_cluster_list USING btree (cluster_id);


--
-- Name: idx_tbl_cluster_list_list_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_cluster_list_list_id ON app.tbl_cluster_list USING btree (list_id);


--
-- Name: idx_tbl_cluster_list_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_cluster_list_removed_at ON app.tbl_cluster_list USING btree (removed_at);


--
-- Name: idx_tbl_cluster_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_cluster_removed_at ON app.tbl_cluster USING btree (removed_at);


--
-- Name: idx_tbl_group_role_group_code; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_group_role_group_code ON app.tbl_group_role USING btree (group_code);


--
-- Name: idx_tbl_group_role_role_code; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_group_role_role_code ON app.tbl_group_role USING btree (role_code);


--
-- Name: idx_tbl_list_created_by_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_created_by_user_id ON app.tbl_list USING btree (created_by_user_id);


--
-- Name: idx_tbl_list_item_comment_item_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_comment_item_id ON app.tbl_list_item_comment USING btree (list_item_id);


--
-- Name: idx_tbl_list_item_comment_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_comment_removed_at ON app.tbl_list_item_comment USING btree (removed_at);


--
-- Name: idx_tbl_list_item_created_by_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_created_by_user_id ON app.tbl_list_item USING btree (created_by_user_id);


--
-- Name: idx_tbl_list_item_link_item_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_link_item_id ON app.tbl_list_item_link USING btree (list_item_id);


--
-- Name: idx_tbl_list_item_link_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_link_removed_at ON app.tbl_list_item_link USING btree (removed_at);


--
-- Name: idx_tbl_list_item_list_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_list_id ON app.tbl_list_item USING btree (list_id);


--
-- Name: idx_tbl_list_item_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_removed_at ON app.tbl_list_item USING btree (removed_at);


--
-- Name: idx_tbl_list_item_reservation_item_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_reservation_item_id ON app.tbl_list_item_reservation USING btree (list_item_id);


--
-- Name: idx_tbl_list_item_reservation_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_reservation_removed_at ON app.tbl_list_item_reservation USING btree (removed_at);


--
-- Name: idx_tbl_list_item_reservation_reserved_by; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_item_reservation_reserved_by ON app.tbl_list_item_reservation USING btree (reserved_by_user_id);


--
-- Name: idx_tbl_list_owner_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_owner_user_id ON app.tbl_list USING btree (owner_user_id);


--
-- Name: idx_tbl_list_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_list_removed_at ON app.tbl_list USING btree (removed_at);


--
-- Name: idx_tbl_user_group_group_code; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_group_group_code ON app.tbl_user_group USING btree (group_code);


--
-- Name: idx_tbl_user_group_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_group_user_id ON app.tbl_user_group USING btree (user_id);


--
-- Name: idx_tbl_user_identity_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_identity_removed_at ON app.tbl_user_identity USING btree (removed_at);


--
-- Name: idx_tbl_user_identity_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_identity_user_id ON app.tbl_user_identity USING btree (user_id);


--
-- Name: idx_tbl_user_is_active; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_is_active ON app.tbl_user USING btree (is_active);


--
-- Name: idx_tbl_user_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_removed_at ON app.tbl_user USING btree (removed_at);


--
-- Name: idx_tbl_user_session_expires_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_session_expires_at ON app.tbl_user_session USING btree (expires_at);


--
-- Name: idx_tbl_user_session_removed_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_session_removed_at ON app.tbl_user_session USING btree (removed_at);


--
-- Name: idx_tbl_user_session_revoked_at; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_session_revoked_at ON app.tbl_user_session USING btree (revoked_at);


--
-- Name: idx_tbl_user_session_user_id; Type: INDEX; Schema: app; Owner: -
--

CREATE INDEX idx_tbl_user_session_user_id ON app.tbl_user_session USING btree (user_id);


--
-- Name: ux_tbl_cluster_list_cluster_list_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_cluster_list_cluster_list_active ON app.tbl_cluster_list USING btree (cluster_id, list_id) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_cluster_list_list_one_cluster_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_cluster_list_list_one_cluster_active ON app.tbl_cluster_list USING btree (list_id) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_list_item_share_slug_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_list_item_share_slug_active ON app.tbl_list_item USING btree (share_slug) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_list_share_slug_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_list_share_slug_active ON app.tbl_list USING btree (share_slug) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_user_email_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_user_email_active ON app.tbl_user USING btree (email) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_user_identity_provider_subject_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_user_identity_provider_subject_active ON app.tbl_user_identity USING btree (provider, provider_subject) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_user_identity_user_provider_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_user_identity_user_provider_active ON app.tbl_user_identity USING btree (user_id, provider) WHERE (removed_at IS NULL);


--
-- Name: ux_tbl_user_session_token_hash_active; Type: INDEX; Schema: app; Owner: -
--

CREATE UNIQUE INDEX ux_tbl_user_session_token_hash_active ON app.tbl_user_session USING btree (session_token_hash) WHERE (removed_at IS NULL);


--
-- Name: tbl_auth_challenge tbl_auth_challenge_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_auth_challenge
    ADD CONSTRAINT tbl_auth_challenge_user_id_fkey FOREIGN KEY (user_id) REFERENCES app.tbl_user(user_id) ON DELETE CASCADE;


--
-- Name: tbl_cluster tbl_cluster_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster
    ADD CONSTRAINT tbl_cluster_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE SET NULL;


--
-- Name: tbl_cluster_list tbl_cluster_list_added_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster_list
    ADD CONSTRAINT tbl_cluster_list_added_by_user_id_fkey FOREIGN KEY (added_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE SET NULL;


--
-- Name: tbl_cluster_list tbl_cluster_list_cluster_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster_list
    ADD CONSTRAINT tbl_cluster_list_cluster_id_fkey FOREIGN KEY (cluster_id) REFERENCES app.tbl_cluster(cluster_id) ON DELETE CASCADE;


--
-- Name: tbl_cluster_list tbl_cluster_list_list_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_cluster_list
    ADD CONSTRAINT tbl_cluster_list_list_id_fkey FOREIGN KEY (list_id) REFERENCES app.tbl_list(list_id) ON DELETE CASCADE;


--
-- Name: tbl_group_role tbl_group_role_group_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group_role
    ADD CONSTRAINT tbl_group_role_group_code_fkey FOREIGN KEY (group_code) REFERENCES app.tbl_group(group_code) ON DELETE CASCADE;


--
-- Name: tbl_group_role tbl_group_role_role_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_group_role
    ADD CONSTRAINT tbl_group_role_role_code_fkey FOREIGN KEY (role_code) REFERENCES app.tbl_role(role_code) ON DELETE CASCADE;


--
-- Name: tbl_list tbl_list_country_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_country_code_fkey FOREIGN KEY (country_code) REFERENCES app.tbl_country(country_code) ON DELETE SET NULL;


--
-- Name: tbl_list tbl_list_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE RESTRICT;


--
-- Name: tbl_list tbl_list_currency_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_currency_code_fkey FOREIGN KEY (currency_code) REFERENCES app.tbl_currency(currency_code) ON DELETE SET NULL;


--
-- Name: tbl_list_item_comment tbl_list_item_comment_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_comment
    ADD CONSTRAINT tbl_list_item_comment_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE RESTRICT;


--
-- Name: tbl_list_item_comment tbl_list_item_comment_list_item_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_comment
    ADD CONSTRAINT tbl_list_item_comment_list_item_id_fkey FOREIGN KEY (list_item_id) REFERENCES app.tbl_list_item(list_item_id) ON DELETE CASCADE;


--
-- Name: tbl_list_item tbl_list_item_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item
    ADD CONSTRAINT tbl_list_item_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE RESTRICT;


--
-- Name: tbl_list_item_link tbl_list_item_link_created_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_link
    ADD CONSTRAINT tbl_list_item_link_created_by_user_id_fkey FOREIGN KEY (created_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE SET NULL;


--
-- Name: tbl_list_item_link tbl_list_item_link_list_item_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_link
    ADD CONSTRAINT tbl_list_item_link_list_item_id_fkey FOREIGN KEY (list_item_id) REFERENCES app.tbl_list_item(list_item_id) ON DELETE CASCADE;


--
-- Name: tbl_list_item tbl_list_item_list_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item
    ADD CONSTRAINT tbl_list_item_list_id_fkey FOREIGN KEY (list_id) REFERENCES app.tbl_list(list_id) ON DELETE CASCADE;


--
-- Name: tbl_list_item_reservation tbl_list_item_reservation_currency_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation
    ADD CONSTRAINT tbl_list_item_reservation_currency_code_fkey FOREIGN KEY (currency_code) REFERENCES app.tbl_currency(currency_code) ON DELETE RESTRICT;


--
-- Name: tbl_list_item_reservation tbl_list_item_reservation_list_item_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation
    ADD CONSTRAINT tbl_list_item_reservation_list_item_id_fkey FOREIGN KEY (list_item_id) REFERENCES app.tbl_list_item(list_item_id) ON DELETE CASCADE;


--
-- Name: tbl_list_item_reservation tbl_list_item_reservation_reservation_status_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation
    ADD CONSTRAINT tbl_list_item_reservation_reservation_status_code_fkey FOREIGN KEY (reservation_status_code) REFERENCES app.tbl_list_item_reservation_status(reservation_status_code) ON DELETE RESTRICT;


--
-- Name: tbl_list_item_reservation tbl_list_item_reservation_reserved_by_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item_reservation
    ADD CONSTRAINT tbl_list_item_reservation_reserved_by_user_id_fkey FOREIGN KEY (reserved_by_user_id) REFERENCES app.tbl_user(user_id) ON DELETE RESTRICT;


--
-- Name: tbl_list_item tbl_list_item_status_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list_item
    ADD CONSTRAINT tbl_list_item_status_code_fkey FOREIGN KEY (status_code) REFERENCES app.tbl_list_item_status(status_code) ON DELETE RESTRICT;


--
-- Name: tbl_list tbl_list_language_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_language_code_fkey FOREIGN KEY (language_code) REFERENCES app.tbl_language(language_code) ON DELETE SET NULL;


--
-- Name: tbl_list tbl_list_owner_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_list
    ADD CONSTRAINT tbl_list_owner_user_id_fkey FOREIGN KEY (owner_user_id) REFERENCES app.tbl_user(user_id) ON DELETE SET NULL;


--
-- Name: tbl_user tbl_user_country_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user
    ADD CONSTRAINT tbl_user_country_code_fkey FOREIGN KEY (country_code) REFERENCES app.tbl_country(country_code) ON DELETE SET NULL;


--
-- Name: tbl_user tbl_user_currency_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user
    ADD CONSTRAINT tbl_user_currency_code_fkey FOREIGN KEY (currency_code) REFERENCES app.tbl_currency(currency_code) ON DELETE SET NULL;


--
-- Name: tbl_user_group tbl_user_group_group_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_group
    ADD CONSTRAINT tbl_user_group_group_code_fkey FOREIGN KEY (group_code) REFERENCES app.tbl_group(group_code) ON DELETE CASCADE;


--
-- Name: tbl_user_group tbl_user_group_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_group
    ADD CONSTRAINT tbl_user_group_user_id_fkey FOREIGN KEY (user_id) REFERENCES app.tbl_user(user_id) ON DELETE CASCADE;


--
-- Name: tbl_user_identity tbl_user_identity_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_identity
    ADD CONSTRAINT tbl_user_identity_user_id_fkey FOREIGN KEY (user_id) REFERENCES app.tbl_user(user_id) ON DELETE CASCADE;


--
-- Name: tbl_user tbl_user_language_code_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user
    ADD CONSTRAINT tbl_user_language_code_fkey FOREIGN KEY (language_code) REFERENCES app.tbl_language(language_code) ON DELETE SET NULL;


--
-- Name: tbl_user_session tbl_user_session_user_id_fkey; Type: FK CONSTRAINT; Schema: app; Owner: -
--

ALTER TABLE ONLY app.tbl_user_session
    ADD CONSTRAINT tbl_user_session_user_id_fkey FOREIGN KEY (user_id) REFERENCES app.tbl_user(user_id) ON DELETE CASCADE;


--
-- Name: SCHEMA app; Type: ACL; Schema: -; Owner: -
--

GRANT ALL ON SCHEMA app TO gift_test_functional_user;


--
-- Name: TABLE tbl_auth_challenge; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_auth_challenge TO gift_test_functional_user;


--
-- Name: TABLE tbl_cluster; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_cluster TO gift_test_functional_user;


--
-- Name: TABLE tbl_cluster_list; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_cluster_list TO gift_test_functional_user;


--
-- Name: TABLE tbl_country; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_country TO gift_test_functional_user;


--
-- Name: TABLE tbl_currency; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_currency TO gift_test_functional_user;


--
-- Name: TABLE tbl_group; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_group TO gift_test_functional_user;


--
-- Name: TABLE tbl_group_role; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_group_role TO gift_test_functional_user;


--
-- Name: TABLE tbl_language; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_language TO gift_test_functional_user;


--
-- Name: TABLE tbl_list; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item_comment; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item_comment TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item_link; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item_link TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item_reservation; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item_reservation TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item_reservation_status; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item_reservation_status TO gift_test_functional_user;


--
-- Name: TABLE tbl_list_item_status; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_list_item_status TO gift_test_functional_user;


--
-- Name: TABLE tbl_role; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_role TO gift_test_functional_user;


--
-- Name: TABLE tbl_user; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_user TO gift_test_functional_user;


--
-- Name: TABLE tbl_user_group; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_user_group TO gift_test_functional_user;


--
-- Name: TABLE tbl_user_identity; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_user_identity TO gift_test_functional_user;


--
-- Name: TABLE tbl_user_session; Type: ACL; Schema: app; Owner: -
--

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE app.tbl_user_session TO gift_test_functional_user;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: app; Owner: -
--

ALTER DEFAULT PRIVILEGES FOR ROLE gift_test_functional_user IN SCHEMA app GRANT ALL ON SEQUENCES  TO gift_test_functional_user;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: app; Owner: -
--

ALTER DEFAULT PRIVILEGES FOR ROLE gift_test_functional_user IN SCHEMA app GRANT ALL ON TABLES  TO gift_test_functional_user;


--
-- Name: DEFAULT PRIVILEGES FOR SEQUENCES; Type: DEFAULT ACL; Schema: public; Owner: -
--

ALTER DEFAULT PRIVILEGES FOR ROLE gift_test_functional_user IN SCHEMA public GRANT ALL ON SEQUENCES  TO gift_test_functional_user;


--
-- Name: DEFAULT PRIVILEGES FOR TABLES; Type: DEFAULT ACL; Schema: public; Owner: -
--

ALTER DEFAULT PRIVILEGES FOR ROLE gift_test_functional_user IN SCHEMA public GRANT ALL ON TABLES  TO gift_test_functional_user;


--
-- PostgreSQL database dump complete
--

