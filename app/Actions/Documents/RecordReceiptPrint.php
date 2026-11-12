<?php

namespace App\Actions\Documents;

use App\Actions\Commands\CommandAction;
use App\Actions\Commands\CommandContext;
use App\Actions\Commands\CommandServices;
use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\StoredResult;
use App\Domain\Documents\Receipts\ReceiptPrints;
use App\Models\User;
use App\Policies\ObjectScope;
use App\Queries\Inventory\Column as Read;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * RC-44 `document.print_receipt` (INTERACTION_CONTRACTS, "RC-44 to RC-47"; TECHNICAL_RULES section
 * 7.2, "Print, reprint or export request"; DOCUMENT_CONTRACTS DC-01; BR-DOC-04, BR-DOC-06, BR-AUD-06;
 * REQ-SALE-11; TASK-031 scope items 2 and 5): a print request of a Sale's receipt in 58 mm, 80 mm or
 * A4, by the Sale's own cashier while they hold CAP-21, or by the Owner (CAP-22); any other Sale, like
 * one that does not exist, is NOT_FOUND (SEC-AZ-06, TR-ERR-03). It records whether the request is a
 * copy — every print request of the Sale after its first, by anyone (DC-01) — decided under the lock
 * of the Sale's prints (ReceiptPrints::serialize), so that exactly one is the original. It writes only
 * its audit event PRINT and its result, never a Sale, a Payment, a movement or a session's change
 * (BR-DOC-04); a print follows the commit, and a failed print leaves the Sale intact and is simply
 * requested again (REQ-SALE-11). The answer leads to RP-103P in the size asked for; a repeat with the
 * same identity answers the same print request, so that the copy marker does not change on a retry.
 *
 * Write steps (TEST_STRATEGY TS-21): the audit event, the result.
 */
final class RecordReceiptPrint extends CommandAction
{
    public const string OPERATION = 'document.print_receipt';

    /** The result's type (SCHEMA section 5.3). */
    public const string RESULT = 'receipt_print';

    public function __construct(CommandServices $services, private readonly ReceiptPrints $prints)
    {
        parent::__construct($services);
    }

    public function operation(): string
    {
        return self::OPERATION;
    }

    /**
     * CAP-21, which the Owner holds too, for the actor guard; a Sale of another cashier needs CAP-22,
     * which the scope checks.
     */
    public function capabilities(): array
    {
        return [Capability::Cap21];
    }

    protected function perform(CommandContext $context, User $actor, int $commandId): StoredResult
    {
        $payload = $context->envelope->payload;
        $saleId = $payload['saleId'] ?? null;
        $size = $payload['size'] ?? null;

        if (! is_int($saleId) || ! is_string($size) || ! in_array($size, ReceiptPrints::SIZES, true)) {
            throw new LogicException('The payload was validated before the Action.');
        }

        $sale = self::sale($saleId, $actor);

        // Whether this request is the first of the Sale, under the lock of its prints.
        $this->prints->serialize($sale['id']);
        $copy = $this->prints->printed($sale['id'], $sale['number']);

        $this->services->audit->record(
            AuditCategory::Business,
            ReceiptPrints::AUDIT_ACTION,
            Actor::user($actor->id, $actor->display_name),
            ['size' => $size, 'copy' => $copy],
            subject: ['type' => ReceiptPrints::AUDIT_SUBJECT, 'id' => $sale['id']],
            reference: $sale['number'],
            commandId: $commandId,
            cashSessionId: $sale['session'],
        );
        $this->written();

        return new StoredResult(self::RESULT, $commandId, ['saleId' => $sale['id'], 'size' => $size, 'copy' => $copy]);
    }

    /**
     * The result for this viewer: the print request, whether it is a copy, and RP-103P; null for a
     * viewer who may not see the Sale any more.
     *
     * @return array{type: string, id: int|null, location: string, copy: bool}|null
     */
    public function present(User $viewer, int $commandId, StoredResult $result): ?array
    {
        $saleId = $result->detail['saleId'] ?? null;
        $size = $result->detail['size'] ?? null;
        $copy = $result->detail['copy'] ?? null;

        if (! is_int($saleId) || ! is_string($size) || ! is_bool($copy)) {
            throw new LogicException('A print request stored its Sale, its size and whether it is a copy.');
        }

        $actor = DB::table('sales')->where('id', $saleId)->value('actor_user_id');
        $own = $actor === $viewer->id && Capabilities::allows($viewer, Capability::Cap21);

        if (! $own && ! Capabilities::allows($viewer, Capability::Cap22)) {
            return null;
        }

        return ['type' => $result->type, 'id' => $result->ref, 'location' => ReceiptPrints::path($saleId, $commandId, $size), 'copy' => $copy];
    }

    /**
     * The Sale in the actor's scope: its key, number and session.
     *
     * @return array{id: int, number: string, session: int}
     *
     * @throws CommandRefused NOT_FOUND
     */
    private static function sale(int $saleId, User $actor): array
    {
        $row = ObjectScope::key($saleId) === null ? null : DB::table('sales')->where('id', $saleId)->first(['id', 'sale_number', 'cash_session_id', 'actor_user_id']);

        if ($row === null) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        $values = (array) $row;
        $own = Read::int($values, 'actor_user_id') === $actor->id && Capabilities::allows($actor, Capability::Cap21);

        if (! $own && ! Capabilities::allows($actor, Capability::Cap22)) {
            throw CommandRefused::of(ErrorCode::NotFound);
        }

        return ['id' => Read::int($values, 'id'), 'number' => Read::text($values, 'sale_number'), 'session' => Read::int($values, 'cash_session_id')];
    }
}
