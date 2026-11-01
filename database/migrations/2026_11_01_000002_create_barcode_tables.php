<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-016: the barcode tables of the Catalog module (SCHEMA section 5.6; D-051,
 * D-052), with their checks, keys, indexes and grants (SCHEMA sections 10 and 11,
 * database/privileges.php):
 *
 * - barcode_tokens (class F): a token registered once, compared exactly, in the
 *   format of BR-CAT-17 and the namespace of BR-CAT-18; a declared symbology's
 *   shape. Its check digit is the guard's (G-09).
 * - barcode_mappings (class F): one row per attachment of a token to a product;
 *   barcode_mappings_one_original allows one ordinary attachment per token, ever
 *   (BR-CAT-19). The composite keys of a correction are here for TASK-020.
 * - barcode_mapping_retirements (class F): one fact per retirement, with its
 *   reason class (BR-CAT-52).
 * - barcode_active_mappings (class P): the projection, whose primary key on the
 *   token is the guarantee that a token is active on at most one product (BR-CAT-16).
 *
 * A mapping and a retirement reference each other for a correction, so the key of
 * barcode_mappings to the retirements follows them both. No key of these tables is
 * deferred: SCHEMA section 5.6 orders each one's rows within a command.
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a
 * second, on four new, empty tables. Lock impact: the keys to products, users and
 * command_identities take a SHARE ROW EXCLUSIVE lock on those three tables, held
 * to the end of the migration's transaction: writes to them, every command's first
 * write among them, wait that long. Verification (REL-20): db:check-privileges
 * passes; tests/Feature/Catalog/BarcodeTablesTest.php proves the constraint,
 * deferred, privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE barcode_tokens (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                token text NOT NULL,
                kind text NOT NULL,
                symbology text NULL,
                created_at timestamptz NOT NULL,
                CONSTRAINT barcode_tokens_token_format CHECK (token ~ '^[ -~]{1,128}$' AND token !~ '^ ' AND token !~ ' $'),
                CONSTRAINT barcode_tokens_kind_namespace CHECK (
                    (kind = 'INTERNAL' AND token ~ '^BK-P-[0-9A-HJKMNP-TV-Z]{16}$')
                    OR (kind = 'MANUFACTURER' AND token !~ '^BK-')
                ),
                CONSTRAINT barcode_tokens_symbology_shape CHECK (symbology IS NULL OR (kind = 'MANUFACTURER' AND (
                    (symbology = 'EAN_13' AND token ~ '^[0-9]{13}$')
                    OR (symbology = 'EAN_8' AND token ~ '^[0-9]{8}$')
                    OR (symbology = 'UPC_A' AND token ~ '^[0-9]{12}$')
                    OR (symbology = 'UPC_E' AND token ~ '^[0-9]{8}$')
                ))),
                CONSTRAINT barcode_tokens_token_unique UNIQUE (token),
                CONSTRAINT barcode_tokens_id_kind_unique UNIQUE (id, kind)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE barcode_mappings (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                token_id bigint NOT NULL,
                token_kind text NOT NULL,
                product_id bigint NOT NULL CONSTRAINT barcode_mappings_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                attach_kind text NOT NULL,
                corrects_mapping_id bigint NULL,
                corrected_reason_class text NULL,
                reason text NULL CONSTRAINT barcode_mappings_reason_length CHECK (char_length(reason) BETWEEN 1 AND 1000),
                attached_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT barcode_mappings_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT barcode_mappings_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT barcode_mappings_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT barcode_mappings_attach_kind_shape CHECK (
                    (attach_kind = 'ORIGINAL' AND corrects_mapping_id IS NULL AND corrected_reason_class IS NULL)
                    OR (attach_kind = 'MISASSIGNMENT_CORRECTION' AND corrects_mapping_id IS NOT NULL
                        AND corrected_reason_class IS NOT NULL AND corrected_reason_class = 'MISASSIGNMENT'
                        AND token_kind = 'MANUFACTURER' AND reason IS NOT NULL)
                ),
                CONSTRAINT barcode_mappings_token_fk FOREIGN KEY (token_id, token_kind) REFERENCES barcode_tokens (id, kind) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT barcode_mappings_corrects_unique UNIQUE (corrects_mapping_id),
                CONSTRAINT barcode_mappings_id_token_unique UNIQUE (id, token_id),
                CONSTRAINT barcode_mappings_id_token_product_unique UNIQUE (id, token_id, product_id),
                CONSTRAINT barcode_mappings_corrects_token_fk FOREIGN KEY (corrects_mapping_id, token_id) REFERENCES barcode_mappings (id, token_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE barcode_mapping_retirements (
                mapping_id bigint PRIMARY KEY CONSTRAINT barcode_mapping_retirements_mapping_fk REFERENCES barcode_mappings (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                reason_class text NOT NULL CONSTRAINT barcode_mapping_retirements_reason_class CHECK (reason_class IN ('MISASSIGNMENT', 'OTHER')),
                retired_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT barcode_mapping_retirements_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT barcode_mapping_retirements_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT barcode_mapping_retirements_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT barcode_mapping_retirements_mapping_reason_unique UNIQUE (mapping_id, reason_class)
            )
            SQL);

        $database->statement(<<<'SQL'
            ALTER TABLE barcode_mappings ADD CONSTRAINT barcode_mappings_corrected_retirement_fk
                FOREIGN KEY (corrects_mapping_id, corrected_reason_class) REFERENCES barcode_mapping_retirements (mapping_id, reason_class) MATCH FULL
                ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE barcode_active_mappings (
                token_id bigint PRIMARY KEY CONSTRAINT barcode_active_mappings_token_fk REFERENCES barcode_tokens (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                mapping_id bigint NOT NULL CONSTRAINT barcode_active_mappings_mapping_unique UNIQUE,
                product_id bigint NOT NULL CONSTRAINT barcode_active_mappings_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT barcode_active_mappings_mapping_fk FOREIGN KEY (mapping_id, token_id, product_id) REFERENCES barcode_mappings (id, token_id, product_id) ON DELETE RESTRICT ON UPDATE RESTRICT
            )
            SQL);

        // BR-CAT-19: one ordinary attachment per token, ever.
        $database->statement("CREATE UNIQUE INDEX barcode_mappings_one_original ON barcode_mappings (token_id) WHERE attach_kind = 'ORIGINAL'");

        // SCHEMA section 11: every foreign-key column that a key above does not lead.
        $database->statement('CREATE INDEX barcode_mappings_token_id_index ON barcode_mappings (token_id)');
        $database->statement('CREATE INDEX barcode_mappings_product_id_index ON barcode_mappings (product_id)');
        $database->statement('CREATE INDEX barcode_mappings_actor_user_id_index ON barcode_mappings (actor_user_id)');
        $database->statement('CREATE INDEX barcode_mappings_command_id_index ON barcode_mappings (command_id)');
        $database->statement('CREATE INDEX barcode_mapping_retirements_actor_user_id_index ON barcode_mapping_retirements (actor_user_id)');
        $database->statement('CREATE INDEX barcode_mapping_retirements_command_id_index ON barcode_mapping_retirements (command_id)');
        $database->statement('CREATE INDEX barcode_active_mappings_product_id_index ON barcode_active_mappings (product_id)');

        $database->statement('GRANT SELECT, INSERT ON TABLE barcode_tokens, barcode_mappings, barcode_mapping_retirements TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, DELETE ON TABLE barcode_active_mappings TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE barcode_tokens, barcode_mappings, barcode_mapping_retirements, barcode_active_mappings TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE barcode_tokens_id_seq, barcode_mappings_id_seq TO '.Identifier::quote($roles->backup));
    }
};
