<?php

namespace App\Queries\Sales;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Configuration\BusinessProfiles;
use App\Domain\Media\StoredFiles;
use App\Models\User;
use App\Policies\NotFound;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use App\Support\Numbers\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The committed receipt of RP-103, UXP-103 "Struk" (ROUTE_CONTRACTS RP-103; KEY_SCREENS KS-05; DOCUMENT_CONTRACTS
 * DC-01; SCHEMA section 5.9; BR-SALE-14, BR-DOC-01, BR-DOC-02, BR-DOC-06; TASK-029 scope item 3), read from
 * the Sale's ReceiptSnapshot only, never from current records: the number, the issue time, the cashier's
 * frozen name, the session's number, the payment with its cash amounts, the customer's name, every line
 * as it was frozen, and the shop's identity of the BusinessProfile revision that the snapshot names, with
 * its logo. Never a cost or a profit (BR-COST-08). The scope (SEC-AZ-03, SEC-AZ-06): the Sale's own
 * cashier while they hold CAP-21, and the Owner, who sees every Sale (CAP-22); anyone else, like a Sale
 * that does not exist, is NOT_FOUND (TR-ERR-03).
 */
final class Receipts
{
    /** The issue time, as the pages receive every instant: UTC with microseconds. */
    private const string ISSUED_AT = "to_char(rs.issued_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as issued_at";

    /** The lines of a Sale at most (TECHNICAL_RULES section 11.2), which bounds a snapshot's. */
    private const int MAX_LINES = 200;

    public function __construct(private readonly BusinessProfiles $profiles) {}

    /**
     * @return array{receipt: array<string, mixed>, shop: array<string, string|null>}
     *
     * @throws NotFound
     */
    public function forViewer(mixed $identifier, User $viewer): array
    {
        $key = ObjectScope::key($identifier);
        $row = $key === null ? null : DB::table('receipt_snapshots as rs')
            ->join('sales as s', 's.id', '=', 'rs.sale_id')
            ->where('rs.sale_id', $key)
            ->select([
                'rs.sale_id', 'rs.sale_number', 'rs.total_amount', 'rs.cashier_display_name', 'rs.business_profile_revision_id', 'rs.logo_file_id',
                'rs.cash_session_number', 'rs.payment_method', 'rs.tendered_amount', 'rs.change_amount', 'rs.customer_name', 'rs.lines', 's.actor_user_id',
            ])
            ->selectRaw(self::ISSUED_AT)
            ->first();

        if ($row === null) {
            throw new NotFound;
        }

        $values = (array) $row;
        $own = Read::int($values, 'actor_user_id') === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);

        if (! $own && ! Capabilities::allows($viewer, Capability::Cap22)) {
            throw new NotFound;
        }

        $revision = $this->profiles->revision(Read::int($values, 'business_profile_revision_id'));

        if ($revision === null) {
            throw new LogicException('A receipt names a BusinessProfile revision, which is never deleted (SCHEMA section 5.2).');
        }

        $logo = Read::nullableInt($values, 'logo_file_id');
        $fields = $revision->fields;

        return [
            'receipt' => [
                'saleId' => Read::int($values, 'sale_id'),
                'number' => Read::text($values, 'sale_number'),
                'issuedAt' => Read::text($values, 'issued_at'),
                'cashier' => Read::text($values, 'cashier_display_name'),
                'sessionNumber' => Read::text($values, 'cash_session_number'),
                'method' => Read::text($values, 'payment_method'),
                'total' => self::money($values['total_amount'] ?? null),
                'tendered' => ($values['tendered_amount'] ?? null) === null ? null : self::money($values['tendered_amount']),
                'change' => ($values['change_amount'] ?? null) === null ? null : self::money($values['change_amount']),
                'customerName' => Read::nullableText($values, 'customer_name'),
                'lines' => self::lines(Read::text($values, 'lines')),
            ],
            'shop' => [
                'name' => $fields->shopName,
                'identity' => $fields->documentIdentity,
                'address' => $fields->address,
                'contact' => $fields->contact,
                'email' => $fields->email,
                'header' => $fields->documentHeader,
                'footer' => $fields->documentFooter,
                'logo' => $logo === null ? null : StoredFiles::deliveryPath($logo),
            ],
        ];
    }

    private static function money(mixed $value): string
    {
        if (! is_int($value) && ! is_string($value)) {
            throw new LogicException('A receipt holds its amounts as whole rupiah (SCHEMA section 5.9).');
        }

        return Money::fromDatabase($value)->toCanonical();
    }

    /**
     * The frozen lines, in the order of the cart, every value a string (SCHEMA section 5.9,
     * `receipt_snapshots.lines`).
     *
     * @return list<array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string}>
     */
    private static function lines(string $json): array
    {
        $decoded = json_decode($json, true, 4, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_is_list($decoded) || count($decoded) > self::MAX_LINES) {
            throw new LogicException('A receipt holds its lines as a list (SCHEMA section 5.9).');
        }

        $lines = [];

        foreach ($decoded as $line) {
            $values = is_array($line) ? $line : [];
            $frozen = [];

            foreach (['lineNo', 'sku', 'name', 'unit', 'quantity', 'unitPrice', 'lineTotal'] as $field) {
                $value = $values[$field] ?? null;

                if (! is_string($value)) {
                    throw new LogicException('A frozen line holds every value as a string (SCHEMA section 5.9).');
                }

                $frozen[$field] = $value;
            }

            /** @var array{lineNo: string, sku: string, name: string, unit: string, quantity: string, unitPrice: string, lineTotal: string} $frozen */
            $lines[] = $frozen;
        }

        return $lines;
    }
}
