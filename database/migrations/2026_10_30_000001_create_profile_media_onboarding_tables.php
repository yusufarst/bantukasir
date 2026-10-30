<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-010: the tables of the installation's first state, the shop identity
 * and the media pipeline (SCHEMA sections 5.2, 5.5 and 5.7), with their
 * checks, keys, presence key, indexes and grants (SCHEMA sections 10 and 11,
 * database/privileges.php):
 *
 * - stored_files (class F): the reference record of every stored logo,
 *   product photo and thumbnail; a random storage key, never derived from
 *   input (THR-12); the SHA-256 of the stored bytes; derived_from_file_id set
 *   exactly for a thumbnail. Insert-only: a stored file is never changed,
 *   overwritten or deleted (D-042, BR-CFG-03).
 * - business_profile_revisions (class F): the immutable revisions of the
 *   BusinessProfile (BR-CFG-02); the accent of DESIGN_SYSTEM section 3.3, null
 *   rendering as green; a logo by reference to stored_files, whose purpose
 *   guard G-20 checks; the Owner who made it, with the display name frozen
 *   (SCHEMA section 3.5), and the command — null for the first revision,
 *   which the bootstrap procedure writes without an envelope (TR-CMD-07).
 * - business_profile_current (class S): the one current pointer, inserted
 *   with the first revision (guard G-23) and moved under its update lock
 *   (TR §7.1 step 2.4; RACE-25).
 * - onboarding_releases (class F): the durable release of onboarding, at most
 *   one (D-071); a RELEASE carries its command (RC-13), a
 *   RESTORE_RECONCILIATION none (SEC-OP-05).
 * - onboarding_state (class S): the installation's onboarding state, inserted
 *   NOT_STARTED by the bootstrap; its presence key release_presence, a stored
 *   generated column equal to 1 exactly while the state is RELEASED, refers
 *   to onboarding_releases with a deferred key, so that the state is RELEASED
 *   only with its release record (SCHEMA section 3.7; G-26).
 *
 * Every foreign-key column has its index (SCHEMA section 11). No key of an
 * earlier table targets these tables (EXECUTION_CONTEXT EXC-44); the keys of
 * products.photo_file_id, sales and cash_session_reports to stored_files and
 * business_profile_revisions come with those tables.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under
 * a second. Lock impact: none on an existing table; the keys to users and
 * command_identities take a SHARE ROW EXCLUSIVE lock on them only while the
 * new, empty tables are created. Verification (REL-20): db:check-privileges
 * passes; tests/Feature/Configuration/ConfigurationTablesTest.php proves the
 * constraint, deferred, privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE stored_files (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                storage_key text NOT NULL CONSTRAINT stored_files_storage_key_format CHECK (storage_key ~ '^[A-Za-z0-9_-]{32,128}$'),
                purpose text NOT NULL CONSTRAINT stored_files_purpose_allowed CHECK (purpose IN ('LOGO', 'PRODUCT_PHOTO', 'PRODUCT_THUMBNAIL')),
                media_type text NOT NULL CONSTRAINT stored_files_media_type_allowed CHECK (media_type IN ('image/png', 'image/jpeg', 'image/webp')),
                byte_size bigint NOT NULL CONSTRAINT stored_files_byte_size_positive CHECK (byte_size > 0),
                width integer NOT NULL CONSTRAINT stored_files_width_positive CHECK (width > 0),
                height integer NOT NULL CONSTRAINT stored_files_height_positive CHECK (height > 0),
                sha256 bytea NOT NULL CONSTRAINT stored_files_sha256_length CHECK (octet_length(sha256) = 32),
                derived_from_file_id bigint NULL CONSTRAINT stored_files_derived_from_fk REFERENCES stored_files (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                created_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT stored_files_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                command_id bigint NOT NULL CONSTRAINT stored_files_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT stored_files_storage_key_unique UNIQUE (storage_key),
                CONSTRAINT stored_files_thumbnail_derived CHECK ((purpose = 'PRODUCT_THUMBNAIL') = (derived_from_file_id IS NOT NULL))
            )
            SQL);
        $database->statement('CREATE INDEX stored_files_derived_from_file_id_index ON stored_files (derived_from_file_id)');
        $database->statement('CREATE INDEX stored_files_actor_user_id_index ON stored_files (actor_user_id)');
        $database->statement('CREATE INDEX stored_files_command_id_index ON stored_files (command_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE business_profile_revisions (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                shop_name text NOT NULL CONSTRAINT business_profile_revisions_shop_name_length CHECK (char_length(shop_name) BETWEEN 1 AND 100),
                address text NULL CONSTRAINT business_profile_revisions_address_length CHECK (char_length(address) BETWEEN 1 AND 500),
                contact text NULL CONSTRAINT business_profile_revisions_contact_length CHECK (char_length(contact) BETWEEN 1 AND 200),
                email text NULL CONSTRAINT business_profile_revisions_email_length CHECK (char_length(email) BETWEEN 1 AND 254),
                document_header text NULL CONSTRAINT business_profile_revisions_document_header_length CHECK (char_length(document_header) BETWEEN 1 AND 500),
                document_footer text NULL CONSTRAINT business_profile_revisions_document_footer_length CHECK (char_length(document_footer) BETWEEN 1 AND 500),
                document_identity text NULL CONSTRAINT business_profile_revisions_document_identity_length CHECK (char_length(document_identity) BETWEEN 1 AND 100),
                accent_token text NULL CONSTRAINT business_profile_revisions_accent_token_allowed CHECK (accent_token IN ('green', 'teal', 'blue', 'purple', 'graphite')),
                logo_file_id bigint NULL CONSTRAINT business_profile_revisions_logo_file_fk REFERENCES stored_files (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                created_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT business_profile_revisions_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT business_profile_revisions_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NULL CONSTRAINT business_profile_revisions_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);
        $database->statement('CREATE INDEX business_profile_revisions_logo_file_id_index ON business_profile_revisions (logo_file_id)');
        $database->statement('CREATE INDEX business_profile_revisions_actor_user_id_index ON business_profile_revisions (actor_user_id)');
        $database->statement('CREATE INDEX business_profile_revisions_command_id_index ON business_profile_revisions (command_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE business_profile_current (
                id smallint PRIMARY KEY CONSTRAINT business_profile_current_single CHECK (id = 1),
                revision_id bigint NOT NULL CONSTRAINT business_profile_current_revision_fk REFERENCES business_profile_revisions (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                changed_at timestamptz NOT NULL
            )
            SQL);
        $database->statement('CREATE INDEX business_profile_current_revision_id_index ON business_profile_current (revision_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_releases (
                id smallint PRIMARY KEY CONSTRAINT onboarding_releases_single CHECK (id = 1),
                released_at timestamptz NOT NULL,
                kind text NOT NULL CONSTRAINT onboarding_releases_kind_allowed CHECK (kind IN ('RELEASE', 'RESTORE_RECONCILIATION')),
                actor_user_id bigint NOT NULL CONSTRAINT onboarding_releases_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                command_id bigint NULL CONSTRAINT onboarding_releases_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT onboarding_releases_command_kind CHECK ((kind = 'RELEASE') = (command_id IS NOT NULL))
            )
            SQL);
        $database->statement('CREATE INDEX onboarding_releases_actor_user_id_index ON onboarding_releases (actor_user_id)');
        $database->statement('CREATE INDEX onboarding_releases_command_id_index ON onboarding_releases (command_id)');

        $database->statement(<<<'SQL'
            CREATE TABLE onboarding_state (
                id smallint PRIMARY KEY CONSTRAINT onboarding_state_single CHECK (id = 1),
                state text NOT NULL CONSTRAINT onboarding_state_state_allowed CHECK (state IN ('NOT_STARTED', 'ACTIVE', 'RELEASED')),
                version bigint NOT NULL CONSTRAINT onboarding_state_version_positive CHECK (version >= 1),
                release_presence smallint GENERATED ALWAYS AS (CASE WHEN state = 'RELEASED' THEN 1::smallint END) STORED,
                changed_at timestamptz NOT NULL,
                changed_by_user_id bigint NULL CONSTRAINT onboarding_state_changed_by_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT onboarding_state_release_presence_fk FOREIGN KEY (release_presence)
                    REFERENCES onboarding_releases (id) DEFERRABLE INITIALLY DEFERRED
            )
            SQL);
        $database->statement('CREATE INDEX onboarding_state_changed_by_user_id_index ON onboarding_state (changed_by_user_id)');
        $database->statement('CREATE INDEX onboarding_state_release_presence_index ON onboarding_state (release_presence)');

        $database->statement('GRANT SELECT, INSERT ON TABLE stored_files, business_profile_revisions, onboarding_releases TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (revision_id, changed_at) ON TABLE business_profile_current TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (state, version, changed_at, changed_by_user_id) ON TABLE onboarding_state TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE stored_files, business_profile_revisions, business_profile_current, onboarding_releases, onboarding_state TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE stored_files_id_seq, business_profile_revisions_id_seq TO '.Identifier::quote($roles->backup));
    }
};
