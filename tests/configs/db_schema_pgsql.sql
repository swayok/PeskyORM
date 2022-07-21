DROP TABLE IF EXISTS "public"."admins";
CREATE TABLE "public"."admins"
(
    "id"                    serial4                                                         NOT NULL,
    "login"                 varchar(100) COLLATE "default"                                  NOT NULL,
    "password"              varchar(100) COLLATE "default"                                  NOT NULL,
    "parent_id"             int4,
    "created_at"            timestamptz(6)                 DEFAULT NOW()                    NOT NULL,
    "updated_at"            timestamptz(6)                 DEFAULT NOW()                    NOT NULL,
    "remember_token"        varchar(100) COLLATE "default" DEFAULT ''::character varying,
    "is_superadmin"         bool                           DEFAULT FALSE                    NOT NULL,
    "language"              char(2) COLLATE "default"      DEFAULT 'en'::bpchar,
    "ip"                    inet                           DEFAULT '192.168.1.1'::inet,
    "role"                  varchar(100) COLLATE "default" DEFAULT ''::character varying    NOT NULL,
    "is_active"             bool                           DEFAULT TRUE                     NOT NULL,
    "name"                  varchar(200) COLLATE "default" DEFAULT ''::character varying    NOT NULL,
    "email"                 varchar(100) COLLATE "default",
    "timezone"              varchar(50) COLLATE "default"  DEFAULT 'UTC'::character varying NOT NULL,
    "not_changeable_column" varchar(50) COLLATE "default"  DEFAULT 'not changable',
    "big_data"              text COLLATE "default"                                          NOT NULL DEFAULT 'biiiiiiig data'
)
    WITH (OIDS= FALSE)
;

-- --------------------------------------------------------

DROP TABLE IF EXISTS "public"."info_pages";
CREATE TABLE "public"."info_pages"
(
    "id"           serial4                                                      NOT NULL,
    "code"         varchar(255) COLLATE "default"                               NOT NULL,
    "lang"         char(2) COLLATE "default"      DEFAULT 'en'::bpchar          NOT NULL,
    "title"        varchar(500) COLLATE "default"                               NOT NULL,
    "link_title"   varchar(255) COLLATE "default" DEFAULT ''::character varying NOT NULL,
    "content"      text COLLATE "default",
    "is_important" bool                                                         NOT NULL,
    "created_at"   timestamptz(6)                 DEFAULT NOW()                 NOT NULL,
    "updated_at"   timestamptz(6)                 DEFAULT NOW()                 NOT NULL
)
    WITH (OIDS= FALSE)
;

-- --------------------------------------------------------

DROP TABLE IF EXISTS "public"."settings";
CREATE TABLE "public"."settings"
(
    "id"    serial4                        NOT NULL,
    "key"   varchar(100) COLLATE "default" NOT NULL,
    "value" json DEFAULT '{}'::json        NOT NULL
)
    WITH (OIDS= FALSE)
;

-- --------------------------------------------------------

CREATE UNIQUE INDEX "admins_email_idx" ON "public"."admins" USING btree ("email");
CREATE INDEX "admins_idx_id01" ON "public"."admins" USING btree ("id");
CREATE INDEX "admins_idx_parent_id01" ON "public"."admins" USING btree ("parent_id");
CREATE INDEX "admins_idx_token01" ON "public"."admins" USING btree ("remember_token");
CREATE UNIQUE INDEX "admins_login_idx" ON "public"."admins" USING btree ("login");
ALTER TABLE "public"."admins"
    ADD UNIQUE ("email");
ALTER TABLE "public"."admins"
    ADD UNIQUE ("login");
ALTER TABLE "public"."admins"
    ADD PRIMARY KEY ("id");

-- --------------------------------------------------------

CREATE INDEX "info_pages_code_idx" ON "public"."info_pages" USING btree ("code");
CREATE INDEX "info_pages_lang_idx" ON "public"."info_pages" USING btree ("lang");
CREATE INDEX "info_pages_title_idx" ON "public"."info_pages" USING btree ("title");
ALTER TABLE "public"."info_pages"
    ADD PRIMARY KEY ("id");

-- --------------------------------------------------------

CREATE UNIQUE INDEX "settings_key_idx" ON "public"."settings" USING btree ("key");
ALTER TABLE "public"."settings"
    ADD UNIQUE ("key");
ALTER TABLE "public"."settings"
    ADD PRIMARY KEY ("id");

-- --------------------------------------------------------

ALTER TABLE "public"."admins"
    ADD FOREIGN KEY ("parent_id") REFERENCES "public"."admins" ("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------

SELECT SETVAL('"public"."admins_id_seq"', 10000, TRUE);
SELECT SETVAL('"public"."info_pages_id_seq"', 10000, TRUE);
SELECT SETVAL('"public"."settings_id_seq"', 10000, TRUE);

ALTER TABLE "public"."admins"
    OWNER TO "pesky_orm_test";
ALTER TABLE "public"."info_pages"
    OWNER TO "pesky_orm_test";
ALTER TABLE "public"."settings"
    OWNER TO "pesky_orm_test";
