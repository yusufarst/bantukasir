<?php

namespace App\Console\Commands;

use App\Actions\Operations\RebuildProjection;
use App\Domain\Operations\Reconciliation\RepairRefused;
use App\Policies\ObjectScope;

/**
 * OP-07 `projection:rebuild` (ROUTE_CONTRACTS OP-07 and section 7; SECURITY_MODEL SEC-OP-07 and section 14;
 * OPERATIONS_RUNBOOK RB-04 step 5; THR-10; TASK-036 scope item 4): on the Owner's request after a
 * reconciliation incident, the operator rebuilds from immutable facts the projections of exactly one of
 * a product (`--product`), a Sale (`--sale`) or a token (`--token`), each named by its internal key, or
 * refuses with no change. Then RB-04 step 6 runs `reconciliation:run` for the product again, and the
 * Owner reopens its postings with RC-16 (step 7). Its own options are typed internal keys; the
 * environment and the note are OperatorProcedure's.
 */
final class ProjectionRebuild extends OperatorProcedure
{
    private const string KEY = 'regex:/^[1-9][0-9]{0,18}$/D';

    protected $signature = 'projection:rebuild
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--product= : The internal key of the product whose balance, valuation and allocation projections to rebuild}
        {--sale= : The internal key of the Sale whose running totals of refunds and returns to rebuild}
        {--token= : The internal key of the barcode token whose active mapping to rebuild}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters, naming the Owner\'s request}';

    protected $description = 'OP-07: rebuild the projections of one product, Sale or token from immutable facts, or refuse with no change';

    protected function process(): string
    {
        return RebuildProjection::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'product' => ['sometimes', 'string', self::KEY],
            'sale' => ['sometimes', 'string', self::KEY],
            'token' => ['sometimes', 'string', self::KEY],
        ];
    }

    protected function perform(array $options, string $note): ?int
    {
        $named = array_intersect_key($options, array_flip([RebuildProjection::PRODUCT, RebuildProjection::SALE, RebuildProjection::TOKEN]));

        if (count($named) !== 1) {
            throw new ProcedureRefused('Name exactly one of --product, --sale or --token.');
        }

        $subject = (string) array_key_first($named);
        $id = ObjectScope::key($named[$subject]) ?? throw new ProcedureRefused(sprintf('The option --%s is not an internal key.', $subject));

        try {
            $changed = app(RebuildProjection::class)($subject, $id, $note);
        } catch (RepairRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->info($changed
            ? sprintf('The projections of %s %d were rebuilt from its facts, with the audit event PROJECTION_REBUILT. Run reconciliation:run --operator --product for a product again (RB-04 step 6).', $subject, $id)
            : sprintf('Every projection of %s %d already equals its facts: nothing was changed.', $subject, $id));

        return null;
    }
}
