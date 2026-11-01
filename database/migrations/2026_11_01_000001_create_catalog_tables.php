<?php

use App\Database\DatabaseRoles;
use App\Database\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * TASK-015: the tables of the Catalog module's master data (SCHEMA section
 * 5.6), with their checks, keys, indexes and grants (SCHEMA sections 10 and 11,
 * database/privileges.php):
 *
 * - categories (class M): name, active flag and the expected version. Names
 *   are not unique: no decision sets it (SCHEMA section 5.6).
 * - products (class M): the SKU unique without regard to letter case through
 *   lower() in the unique index, the only authority for it (SCH-14; BR-CAT-05);
 *   the composite target (id, base_unit, quantity_precision) of the stock
 *   movements of TASK-021 (BR-CAT-07, RACE-05); the deferred pointer to the
 *   selling price in force, which must name a revision of the same product
 *   (BR-CAT-13); the optional photo (D-037).
 * - selling_price_revisions (class F): every selling price of a product, with
 *   its actor, the actor's name frozen (SCHEMA section 3.5), and its command;
 *   insert-only. Its composite targets are for the Sale lines of TASK-028.
 *
 * A product and its first revision are written in one transaction, and the
 * deferred key refuses a commit without it. The runtime role takes no value
 * from a sequence (SCH-44), so the Action inserts the product with a pointer
 * that names nothing, inserts the revision, and moves the pointer to it before
 * the commit (App\Domain\Catalog\Products).
 *
 * The indexes of SCHEMA section 11: the SKU exact and by prefix without regard
 * to the database's collation, the trigram index on lower(name) of pg_trgm,
 * the stable order (name, id), the filters, and every foreign-key column.
 *
 * EXC-44: audit_events.product_id → products, the key that TASK-006 left for
 * this Task. Written from now on (App\Domain\Audit\AuditLog).
 *
 * Class: additive (RELEASE_PROCESS section 5). Expected duration: well under a
 * second. Lock impact: the keys to users, stored_files and command_identities
 * and the key added to audit_events, which checks its column, null on every row,
 * take a SHARE ROW EXCLUSIVE lock on those four tables, held to the end of the
 * migration's transaction: writes to them, every command's first write among
 * them, wait that long. Verification (REL-20): db:check-privileges passes;
 * tests/Feature/Catalog/CatalogTablesTest.php proves the constraint, deferred,
 * privilege and retention families as the runtime role.
 */
return new class extends Migration
{
    protected $connection = 'pgsql_migration';

    public function up(): void
    {
        $database = DB::connection($this->connection);
        $roles = DatabaseRoles::of($database);

        $database->statement(<<<'SQL'
            CREATE TABLE categories (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                name text NOT NULL CONSTRAINT categories_name_length CHECK (char_length(name) BETWEEN 1 AND 100),
                active boolean NOT NULL,
                version integer NOT NULL CONSTRAINT categories_version_positive CHECK (version >= 1),
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE products (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                sku text NOT NULL CONSTRAINT products_sku_length CHECK (char_length(sku) BETWEEN 1 AND 64),
                name text NOT NULL CONSTRAINT products_name_length CHECK (char_length(name) BETWEEN 1 AND 200),
                category_id bigint NOT NULL CONSTRAINT products_category_fk REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                brand text NULL CONSTRAINT products_brand_length CHECK (char_length(brand) BETWEEN 1 AND 100),
                base_unit text NOT NULL CONSTRAINT products_base_unit_length CHECK (char_length(base_unit) BETWEEN 1 AND 32),
                quantity_precision smallint NOT NULL CONSTRAINT products_quantity_precision_range CHECK (quantity_precision BETWEEN 0 AND 3),
                current_price_revision_id bigint NOT NULL,
                minimum_stock numeric(13,3) NOT NULL CONSTRAINT products_minimum_stock_range CHECK (minimum_stock BETWEEN 0 AND 9999999999.999),
                active boolean NOT NULL,
                photo_file_id bigint NULL CONSTRAINT products_photo_file_fk REFERENCES stored_files (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                version integer NOT NULL CONSTRAINT products_version_positive CHECK (version >= 1),
                created_command_id bigint NOT NULL CONSTRAINT products_created_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL,
                CONSTRAINT products_sku_format CHECK (sku !~ '[[:cntrl:]]' AND sku !~ '^[[:space:]]' AND sku !~ '[[:space:]]$'),
                CONSTRAINT products_minimum_stock_precision CHECK (minimum_stock = round(minimum_stock, quantity_precision)),
                CONSTRAINT products_id_unit_precision_unique UNIQUE (id, base_unit, quantity_precision)
            )
            SQL);

        $database->statement(<<<'SQL'
            CREATE TABLE selling_price_revisions (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                product_id bigint NOT NULL CONSTRAINT selling_price_revisions_product_fk REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                amount bigint NOT NULL CONSTRAINT selling_price_revisions_amount_range CHECK (amount BETWEEN 0 AND 999999999999),
                created_at timestamptz NOT NULL,
                actor_user_id bigint NOT NULL CONSTRAINT selling_price_revisions_actor_user_fk REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                actor_display_name text NOT NULL CONSTRAINT selling_price_revisions_actor_display_name_length CHECK (char_length(actor_display_name) BETWEEN 1 AND 100),
                command_id bigint NOT NULL CONSTRAINT selling_price_revisions_command_fk REFERENCES command_identities (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT selling_price_revisions_id_product_unique UNIQUE (id, product_id),
                CONSTRAINT selling_price_revisions_id_product_amount_unique UNIQUE (id, product_id, amount)
            )
            SQL);

        $database->statement(<<<'SQL'
            ALTER TABLE products ADD CONSTRAINT products_current_price_revision_fk
                FOREIGN KEY (current_price_revision_id, id) REFERENCES selling_price_revisions (id, product_id)
                DEFERRABLE INITIALLY DEFERRED
            SQL);

        // SCHEMA section 5.6 and section 11.
        $database->statement('CREATE UNIQUE INDEX products_sku_ci_uniq ON products (lower(sku))');
        $database->statement('CREATE INDEX products_sku_prefix_index ON products (lower(sku) text_pattern_ops)');
        $database->statement('CREATE INDEX products_name_trgm_index ON products USING gin (lower(name) gin_trgm_ops)');
        $database->statement('CREATE INDEX products_name_id_index ON products (name, id)');
        $database->statement('CREATE INDEX products_category_id_index ON products (category_id)');
        $database->statement('CREATE INDEX products_brand_ci_index ON products (lower(brand))');
        $database->statement('CREATE INDEX products_active_index ON products (active)');
        $database->statement('CREATE INDEX products_current_price_revision_id_index ON products (current_price_revision_id)');
        $database->statement('CREATE INDEX products_photo_file_id_index ON products (photo_file_id)');
        $database->statement('CREATE INDEX products_created_command_id_index ON products (created_command_id)');
        $database->statement('CREATE INDEX selling_price_revisions_product_id_index ON selling_price_revisions (product_id)');
        $database->statement('CREATE INDEX selling_price_revisions_actor_user_id_index ON selling_price_revisions (actor_user_id)');
        $database->statement('CREATE INDEX selling_price_revisions_command_id_index ON selling_price_revisions (command_id)');

        // EXC-44: the key of audit_events that TASK-006 left for this Task; its
        // column has its index since then.
        $database->statement(<<<'SQL'
            ALTER TABLE audit_events ADD CONSTRAINT audit_events_product_fk
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE RESTRICT
            SQL);

        $database->statement('GRANT SELECT, INSERT, UPDATE (name, active, version, updated_at) ON TABLE categories TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT, UPDATE (sku, name, category_id, brand, base_unit, quantity_precision, current_price_revision_id, minimum_stock, active, photo_file_id, version, updated_at) ON TABLE products TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT, INSERT ON TABLE selling_price_revisions TO '.Identifier::quote($roles->runtime));
        $database->statement('GRANT SELECT ON TABLE categories, products, selling_price_revisions TO '.Identifier::quote($roles->backup));
        $database->statement('GRANT SELECT ON SEQUENCE categories_id_seq, products_id_seq, selling_price_revisions_id_seq TO '.Identifier::quote($roles->backup));
    }
};
