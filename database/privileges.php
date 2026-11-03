<?php

/*
|--------------------------------------------------------------------------
| The privilege matrix (SCHEMA section 10; EXECUTION_CONTEXT EXC-43)
|--------------------------------------------------------------------------
|
| SCHEMA section 10 in machine-readable form. The migration that creates a
| table grants its privileges explicitly (SCH-45) and adds its row here in the
| same change. db:check-privileges compares the database's catalogue with this
| matrix and fails on any difference; db:apply-grants applies it with the
| migration role, after a restore (BACKUP_RECOVERY RB-01 step 9).
| App\Database\PrivilegeMatrix reads and checks it.
|
| tables      Every table of the schema public. "class" is its class of SCHEMA
|             section 4 (null for the framework's migrations table), which
|             bounds what the runtime role may hold. "runtime" lists the
|             runtime role's privileges (SCH-41): select, insert and delete
|             as true, and update as true for every column or as the list of
|             its columns. "backup" is optional: the backup role always reads
|             every table, and inserts only where it says insert (SCH-42).
|             "row_security" is optional, and only for a table of class L: its
|             row security policies by name, each with its command (SELECT,
|             INSERT or DELETE) and its USING and WITH CHECK expressions as
|             PostgreSQL deparses them (pg_get_expr), or null. Each policy is
|             permissive and names no role, so it applies to the runtime and
|             backup roles alike; row security is enabled on the table and
|             never forced (SCHEMA SCH-06 and section 5.4, OPEN_ITEMS T-23).
| sequences   The sequences that the runtime role uses, with usage (SCH-44).
|             Every other sequence, those behind identity and serial columns
|             included, gives the runtime role nothing; a sequence that no
|             column owns must be listed. The backup role reads every sequence.
|
| The migration role owns the schema and everything in it (SCH-40), and
| PUBLIC holds nothing (SCH-43).
|
*/

return [

    'tables' => [

        // Framework tables (SCHEMA section 5.11).
        'cache' => [
            'class' => 'E',
            'runtime' => ['select' => true, 'insert' => true, 'update' => true, 'delete' => true],
        ],
        'cache_locks' => [
            'class' => 'E',
            'runtime' => ['select' => true, 'insert' => true, 'update' => true, 'delete' => true],
        ],
        'migrations' => [
            'class' => null,
            'runtime' => [],
        ],
        'sessions' => [
            'class' => 'E',
            'runtime' => ['select' => true, 'insert' => true, 'update' => true, 'delete' => true],
        ],
        'password_reset_tokens' => [
            'class' => 'E',
            'runtime' => ['select' => true, 'insert' => true, 'update' => true, 'delete' => true],
        ],

        // Access (SCHEMA section 5.1; TASK-006).
        'users' => [
            'class' => 'M',
            'runtime' => [
                'select' => true,
                'insert' => true,
                'update' => ['display_name', 'email', 'password', 'role_operations_admin', 'role_cashier', 'status', 'deactivated_at', 'anonymized_at', 'version', 'updated_at'],
            ],
        ],

        // Audit (SCHEMA section 5.4; TASK-006).
        'audit_events' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'security_log_entries' => [
            'class' => 'L',
            'runtime' => ['select' => true, 'insert' => true, 'delete' => true],
            // The retention of SEC-LOG-05 held by the database (OPEN_ITEMS T-23):
            // a removal admits only an entry older than 365 days (8,760 hours).
            'row_security' => [
                'security_log_entries_add' => ['command' => 'INSERT', 'using' => null, 'check' => 'true'],
                'security_log_entries_read' => ['command' => 'SELECT', 'using' => 'true', 'check' => null],
                'security_log_entries_retention' => ['command' => 'DELETE', 'using' => "(occurred_at < (now() - '8760:00:00'::interval))", 'check' => null],
            ],
        ],

        // Commands (SCHEMA section 5.3; TASK-008).
        'recovery_epoch' => [
            'class' => 'S',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['epoch', 'set_at']],
        ],
        'command_identities' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'command_results' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'command_request_receipts' => [
            'class' => 'E',
            'runtime' => [
                'select' => true,
                'insert' => true,
                'update' => ['outcome', 'outcome_at', 'held_code', 'settled_by_command_id', 'settled_by_user_id'],
                'delete' => true,
            ],
        ],
        'stale_intent_reports' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'intent_reconciliations' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Configuration, Media and the installation's onboarding state
        // (SCHEMA sections 5.2, 5.5 and 5.7; TASK-010).
        'business_profile_revisions' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'business_profile_current' => [
            'class' => 'S',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['revision_id', 'changed_at']],
        ],
        'stored_files' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'onboarding_state' => [
            'class' => 'S',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['state', 'version', 'changed_at', 'changed_by_user_id']],
        ],
        'onboarding_releases' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Catalog (SCHEMA section 5.6; TASK-015).
        'categories' => [
            'class' => 'M',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['name', 'active', 'version', 'updated_at']],
        ],
        'products' => [
            'class' => 'M',
            'runtime' => [
                'select' => true,
                'insert' => true,
                'update' => ['sku', 'name', 'category_id', 'brand', 'base_unit', 'quantity_precision', 'current_price_revision_id', 'minimum_stock', 'active', 'photo_file_id', 'version', 'updated_at'],
            ],
        ],
        'selling_price_revisions' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Catalog, the CSV import's record (SCHEMA section 5.6; TASK-019).
        'csv_product_imports' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Catalog, the barcodes (SCHEMA section 5.6; TASK-016).
        'barcode_tokens' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'barcode_mappings' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'barcode_mapping_retirements' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'barcode_active_mappings' => [
            'class' => 'P',
            'runtime' => ['select' => true, 'insert' => true, 'delete' => true],
        ],

        // Inventory (SCHEMA section 5.7; TASK-021).
        'stock_movements' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'stock_balances' => [
            'class' => 'P',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['on_hand', 'last_sequence', 'updated_at']],
        ],
        'receiving_documents' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'receiving_lines' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'inventory_posting_blocks' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'inventory_posting_block_reopenings' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Inventory, the manual issue (SCHEMA section 5.7; TASK-023).
        'manual_issues' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'manual_issue_lines' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Inventory, the adjustment (SCHEMA section 5.7; TASK-024).
        'adjustments' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'adjustment_lines' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],

        // Costing, the issue allocation (SCHEMA section 5.8; ADR-008; TASK-023).
        'issue_cost_allocations' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'issue_cost_allocation_projections' => [
            'class' => 'P',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['current_status', 'current_amount', 'publication_no', 'updated_at']],
        ],

        // Costing (SCHEMA section 5.8; TASK-021).
        'acquisition_cost_evidence' => [
            'class' => 'F',
            'runtime' => ['select' => true, 'insert' => true],
        ],
        'inventory_valuations' => [
            'class' => 'P',
            'runtime' => ['select' => true, 'insert' => true, 'update' => ['value_status', 'value', 'publication_no', 'last_sequence', 'published_at']],
        ],

        // Operations (SCHEMA section 5.12; TASK-013): written by the backup role
        // (SCH-42), read by the runtime role.
        'backup_runs' => [
            'class' => 'F',
            'runtime' => ['select' => true],
            'backup' => ['insert' => true],
        ],

    ],

    'sequences' => [],

];
