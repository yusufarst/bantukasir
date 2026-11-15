<?php

namespace App\Console\Commands;

use App\Actions\Operations\RecoverCost;
use App\Domain\Operations\Reconciliation\RepairRefused;
use App\Policies\ObjectScope;
use App\Support\Numbers\Cost;
use App\Support\Numbers\NonCanonicalNumber;
use App\Support\Numbers\NumberOutOfRange;

/**
 * OP-06 `costing:recover` (ROUTE_CONTRACTS OP-06 and section 7; SECURITY_MODEL SEC-OP-06 and section 14;
 * OPERATIONS_RUNBOOK RB-12 steps 3 and 4; OPR-04; THR-10; TASK-036 scope item 5): on the Owner's written
 * decision, which names the mark, the cost source, the corrected amount and the reason, the operator runs
 * it outside opening hours with exactly those values, typed and validated here. It publishes the Owner's
 * correction with the larger bound and clears the marks of that source, or refuses with no change; a
 * repeated run with the same mark returns the first result. The environment and the note are
 * OperatorProcedure's.
 */
final class CostingRecover extends OperatorProcedure
{
    private const string KEY = 'regex:/^[1-9][0-9]{0,18}$/D';

    protected $signature = 'costing:recover
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--mark= : The internal key of the cost-incomplete mark that the Owner\'s decision names}
        {--evidence= : The internal key of its cost source, as the Owner\'s decision names it}
        {--amount= : The corrected total cost of that source in rupiah, up to six decimals, as the Owner\'s decision names it}
        {--zero-evidence= : For a corrected amount of zero only: its evidence or reason, 1 to 1,000 characters}
        {--reason= : The Owner\'s reason for the correction, 1 to 1,000 characters}
        {--note= : The operator\'s note for the audit, 1 to 1,000 characters, naming the Owner\'s written decision}';

    protected $description = 'OP-06: publish the Owner\'s correction of a marked cost source with the larger replay bound and clear its marks, or refuse with no change';

    protected function process(): string
    {
        return RecoverCost::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'mark' => ['required', 'string', self::KEY],
            'evidence' => ['required', 'string', self::KEY],
            'amount' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,6})?$/D'],
            'zero-evidence' => ['sometimes', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
            'reason' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ];
    }

    protected function perform(array $options, string $note): ?int
    {
        $mark = ObjectScope::key($options['mark'] ?? null) ?? throw new ProcedureRefused('The option --mark is not an internal key.');
        $evidence = ObjectScope::key($options['evidence'] ?? null) ?? throw new ProcedureRefused('The option --evidence is not an internal key.');

        try {
            $amount = Cost::of($options['amount'] ?? '')->assertUnit();
        } catch (NonCanonicalNumber|NumberOutOfRange) {
            throw new ProcedureRefused('The option --amount is not a cost in the range of one cost source.');
        }

        try {
            $result = app(RecoverCost::class)($mark, $evidence, $amount, $options['zero-evidence'] ?? null, $options['reason'] ?? '', $note);
        } catch (RepairRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->info($result['repeated']
            ? sprintf('The mark %d was recovered by an earlier run: its correction %d is the result, and nothing was changed now.', $mark, $result['correction'])
            : sprintf('The correction %d of the cost source %d was published with the larger bound, and the marks of that source were cleared (RECOVERY_PROCEDURE).', $result['correction'], $evidence));

        return null;
    }
}
