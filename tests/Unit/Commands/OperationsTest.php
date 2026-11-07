<?php

namespace Tests\Unit\Commands;

use App\Actions\Access\AnonymizeAccount;
use App\Actions\Access\CreateStaffAccount;
use App\Actions\Access\DeactivateAccount;
use App\Actions\Access\IssuePasswordLink;
use App\Actions\Access\ReactivateAccount;
use App\Actions\Access\RevokePasswordLink;
use App\Actions\Access\UpdateOwnerDetails;
use App\Actions\Access\UpdateStaffAccount;
use App\Actions\Cash\CreateCashRegister;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\UpdateCashRegister;
use App\Actions\Catalog\AddBarcode;
use App\Actions\Catalog\ConfirmCsvImport;
use App\Actions\Catalog\CorrectBarcodeMisassignment;
use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\DeactivateCategory;
use App\Actions\Catalog\DeactivateProduct;
use App\Actions\Catalog\GenerateBarcode;
use App\Actions\Catalog\ReactivateCategory;
use App\Actions\Catalog\ReactivateProduct;
use App\Actions\Catalog\RemoveProductPhoto;
use App\Actions\Catalog\RenameCategory;
use App\Actions\Catalog\RetireBarcode;
use App\Actions\Catalog\SetProductPhoto;
use App\Actions\Catalog\UpdateProduct;
use App\Actions\Commands\CommandActions;
use App\Actions\Configuration\ChangeBusinessProfile;
use App\Actions\Costing\CorrectCost;
use App\Actions\Documents\RecordLabelPrint;
use App\Actions\Inventory\ConfirmAdjustment;
use App\Actions\Inventory\ConfirmManualIssue;
use App\Actions\Inventory\ConfirmReceiving;
use App\Actions\Inventory\ConfirmReversal;
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
        // The commands built so far, each a known operation (TASK-010: RC-32;
        // TASK-011: RC-35 to RC-42; TASK-015: RC-17 to RC-24; TASK-016: RC-27 to
        // RC-29; TASK-018: RC-25, RC-26 and RC-47; TASK-019: RC-31; TASK-020: RC-30;
        // TASK-021: RC-07; TASK-023: RC-08; TASK-024: RC-09; TASK-025: RC-15; TASK-026: RC-10;
        // TASK-027: RC-02, RC-33 and RC-34).
        $this->assertSame([
            'business_profile.change' => ChangeBusinessProfile::class,
            'user.create' => CreateStaffAccount::class,
            'user.update' => UpdateStaffAccount::class,
            'owner.update_details' => UpdateOwnerDetails::class,
            'user.deactivate' => DeactivateAccount::class,
            'user.reactivate' => ReactivateAccount::class,
            'user.anonymize' => AnonymizeAccount::class,
            'user.issue_link' => IssuePasswordLink::class,
            'user.revoke_link' => RevokePasswordLink::class,
            'product.create' => CreateProduct::class,
            'product.update' => UpdateProduct::class,
            'product.deactivate' => DeactivateProduct::class,
            'product.reactivate' => ReactivateProduct::class,
            'category.create' => CreateCategory::class,
            'category.rename' => RenameCategory::class,
            'category.deactivate' => DeactivateCategory::class,
            'category.reactivate' => ReactivateCategory::class,
            'barcode.add' => AddBarcode::class,
            'barcode.generate' => GenerateBarcode::class,
            'barcode.retire' => RetireBarcode::class,
            'product_photo.set' => SetProductPhoto::class,
            'product_photo.remove' => RemoveProductPhoto::class,
            'document.print_labels' => RecordLabelPrint::class,
            'csv_import.confirm' => ConfirmCsvImport::class,
            'barcode.correct_misassignment' => CorrectBarcodeMisassignment::class,
            'receiving.confirm' => ConfirmReceiving::class,
            'manual_issue.confirm' => ConfirmManualIssue::class,
            'adjustment.confirm' => ConfirmAdjustment::class,
            'cost_correction.confirm' => CorrectCost::class,
            'reversal.confirm' => ConfirmReversal::class,
            'cash_session.open' => OpenCashSession::class,
            'cash_register.create' => CreateCashRegister::class,
            'cash_register.update' => UpdateCashRegister::class,
        ], CommandActions::built());
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
