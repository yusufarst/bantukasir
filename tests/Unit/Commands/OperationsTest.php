<?php

namespace Tests\Unit\Commands;

use App\Actions\Commands\CommandActions;
use App\Actions\Configuration\ChangeBusinessProfile;
use App\Domain\Commands\Operations;
use LogicException;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeAction;
use Tests\TestCase;

/**
 * The server's fixed list of operations (TASK-008 scope item 8; ROUTE_CONTRACTS
 * section 6 and section 8; INTERACTION_CONTRACTS section 1.4), read from the
 * documents: the 48 operations of RC-01 to RC-48 and the scheduled
 * posting_block.block, each in the family that INTERACTION_CONTRACTS section
 * 1.4 gives it, whose label is `commands.family.<family>` (COPY_DECK section
 * 14.1). No Action is registered yet: each command's Task adds its own.
 */
final class OperationsTest extends TestCase
{
    /** The families of INTERACTION_CONTRACTS section 1.4 by their row, with their label key's last part. */
    private const array FAMILY_ROWS = [
        'Sale checkout' => 'sale',
        'Open cash session' => 'open_session',
        'Close, own or Owner-assisted' => 'close_session',
        'Drawer-funded refund or return' => 'refund_drawer',
        'Owner-funded or BANK_TRANSFER refund or return' => 'refund_other',
        'Receiving confirmation, manual issue, adjustment' => 'stock_posting',
        'Opening stock' => 'opening_stock',
        'Reversal' => 'reversal',
        'Cost correction or completion' => 'cost_correction',
        'Product creation' => 'product_create',
        'Price change; product edit; product deactivation or reactivation' => 'product_edit',
        'Category create, rename, deactivate or reactivate' => 'category',
        'Product photo upload, replace or remove' => 'photo',
        'Barcode add, generate, retire' => 'barcode',
        'Barcode misassignment correction' => 'barcode_correction',
        'CSV import confirmation' => 'csv_import',
        'Onboarding set or release' => 'onboarding',
        'BusinessProfile change' => 'profile',
        'Cash register configuration' => 'register',
        'User management' => 'users',
        'Reconciliation reopening' => 'reopen',
        'Owner review note' => 'review_note',
        'Print, reprint or export' => 'print',
    ];

    public function test_the_list_is_the_operations_of_route_contracts_section_6(): void
    {
        $document = (string) file_get_contents(base_path('docs/03-workflows/ROUTE_CONTRACTS.md'));
        $operations = [];

        foreach (explode("\n", $document) as $line) {
            if (preg_match('/^\| RC-(\d+) \| `([a-z_.]+)` \|/', $line, $match) === 1) {
                $operations[] = $match[2];
            }
        }

        $this->assertCount(48, $operations);
        $listed = array_keys(Operations::FAMILIES);
        $this->assertSame([...$operations, 'posting_block.block'], $listed);

        foreach ($listed as $operation) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_.]{0,63}$/', $operation);
            $this->assertTrue(Operations::exists($operation));
        }

        $this->assertFalse(Operations::exists('sale.refund'));
        $this->assertSame(['posting_block.block'], array_values(array_filter($listed, static fn (string $operation): bool => ! Operations::submittedByUsers($operation))));
        $this->assertThrows(fn () => Operations::familyOf('sale.refund'), LogicException::class);
        // The commands built so far, each a known operation (TASK-010: RC-32).
        $this->assertSame(['business_profile.change' => ChangeBusinessProfile::class], CommandActions::built());
    }

    public function test_each_operation_is_in_its_family_of_interaction_contracts_section_1_4(): void
    {
        $document = (string) file_get_contents(base_path('docs/03-workflows/INTERACTION_CONTRACTS.md'));
        $section = substr($document, (int) strpos($document, '### 1.4'), (int) strpos($document, '### 1.5') - (int) strpos($document, '### 1.4'));
        $families = [];

        foreach (explode("\n", $section) as $line) {
            $cells = array_map(trim(...), explode('|', $line));

            if (count($cells) === 4 && array_key_exists($cells[1], self::FAMILY_ROWS)) {
                preg_match_all('/`([a-z_.]+)`/', $cells[2], $operations);

                foreach ($operations[1] as $operation) {
                    $families[$operation] ??= self::FAMILY_ROWS[$cells[1]];
                }
            }
        }

        // SC-01 belongs to the reconciliation's block or reopening.
        $families['posting_block.block'] = 'reopen';

        ksort($families);
        $listed = Operations::FAMILIES;
        ksort($listed);
        $this->assertSame($families, $listed);
    }

    public function test_a_probe_operation_exists_only_in_the_local_and_testing_environments_and_never_replaces_a_real_one(): void
    {
        $this->assertThrows(fn () => Operations::allowForTesting('sale.checkout', 'sale'), LogicException::class);
        $this->assertThrows(fn () => Operations::allowForTesting('probe', 'testing'), LogicException::class);
        $this->assertSame(['local', 'testing'], Operations::PROBE_ENVIRONMENTS);

        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;

            try {
                $this->assertThrows(fn () => Operations::allowForTesting('testing.other', 'testing'), LogicException::class);
                $this->assertThrows(fn () => CommandActions::allowForTesting(Probe::OPERATION, ProbeAction::class), LogicException::class);
            } finally {
                $this->app['env'] = 'testing';
            }
        }
    }

    public function test_print_reprint_and_export_are_the_exempt_family(): void
    {
        $exempt = array_keys(array_filter(Operations::FAMILIES, static fn (string $family): bool => $family === Operations::EXEMPT_FAMILY));
        sort($exempt);

        $this->assertSame(['document.export_session_report', 'document.print_labels', 'document.print_receipt', 'document.print_session_report'], $exempt);
    }
}
