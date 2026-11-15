<?php

namespace App\Console\Commands;

use App\Actions\Operations\BlockPostings;
use App\Actions\Operations\RunReconciliation;
use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use App\Policies\ObjectScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

/**
 * SC-01 `reconciliation:run` (ROUTE_CONTRACTS SC-01 and section 7; ENVIRONMENTS section 13;
 * OPERATIONS_RUNBOOK section 3 and RB-04; SECURITY_MODEL section 14; THR-10; TR-RUN-04; TASK-036 scope
 * item 2), in the application container. Its scheduler runs it daily at 03.00 Asia/Jakarta with no
 * option: every product, `SCHEDULE`. The operator runs it by hand only after a restore, a rebuild or an
 * incident, with `--operator`, the environment's name typed (OPR-02) and, for RB-04 step 6, one
 * product: `OPERATOR`, scope `ALL` or `PRODUCT`. Its options are typed and validated, and no stored value
 * reaches a process.
 *
 * It takes its own no-overlap lock, because an operator's run does not pass the scheduler's, for longer
 * than a run can last. After the run, completed or not, its security log entry names the scheduled
 * command (SECURITY_MODEL section 14); each block has its audit event, and the run its row. Its lines
 * hold the outcome, the counts and the internal keys of the Sales and tokens whose differences it
 * reports, for RB-04 — never an amount, a name or an address.
 */
final class ReconciliationRun extends Command
{
    private const string LOCK = 'reconciliation:run';

    /** Longer than the run's budget (config reconciliation.run_budget). */
    private const int LOCK_SECONDS = 3300;

    protected $signature = 'reconciliation:run
        {--operator : A run by the operator, by hand, after a restore, a rebuild or an incident}
        {--environment= : With --operator: the name of the environment, typed by the operator; it must equal the database marker}
        {--product= : With --operator: the internal key of the one product to reconcile again (RB-04 step 6)}';

    protected $description = 'SC-01: compare the facts with every projection, block the postings of a mismatching product and record the run';

    public function handle(RunReconciliation $run, SecurityLog $securityLog): int
    {
        $operator = $this->option('operator') === true;
        $product = $this->option('product');
        $environment = $this->option('environment');

        if (! $operator && ($product !== null || $environment !== null)) {
            $this->line('RECONCILIATION_REFUSED The options --environment and --product belong to a run by the operator (--operator).');

            return SymfonyCommand::FAILURE;
        }

        if ($operator) {
            try {
                $marker = EnvironmentMarker::require(DB::connection(), OperatorProcedure::ENVIRONMENTS);
            } catch (DatabaseRefused $refusal) {
                $this->line('RECONCILIATION_REFUSED '.$refusal->getMessage());

                return SymfonyCommand::FAILURE;
            }

            if ($environment !== $marker) {
                $this->line(sprintf('RECONCILIATION_REFUSED The typed environment %s is not the environment %s of this database (OPERATIONS_RUNBOOK OPR-02).', json_encode($environment), json_encode($marker)));

                return SymfonyCommand::FAILURE;
            }
        }

        $productId = is_string($product) ? ObjectScope::key(Str::trim($product)) : null;

        if ($product !== null && $productId === null) {
            $this->line('RECONCILIATION_REFUSED The option --product is not an internal key.');

            return SymfonyCommand::FAILURE;
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->line('RECONCILIATION_ALREADY_RUNNING');

            return SymfonyCommand::FAILURE;
        }

        $runId = (string) Str::uuid();
        $actor = SecurityActor::scheduledCommand(BlockPostings::PROCESS);

        try {
            $summary = $run($productId, $operator ? ReconciliationRuns::OPERATOR : ReconciliationRuns::SCHEDULE);
        } catch (InvalidArgumentException $refusal) {
            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Refused, $runId);
            $this->line('RECONCILIATION_REFUSED '.$refusal->getMessage());

            return SymfonyCommand::FAILURE;
        } catch (Throwable $failure) {
            // The run recorded itself FAILED where it could; its class says what failed, and its
            // message may hold an address.
            report($failure);
            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Refused, $runId);
            $this->line('RECONCILIATION_FAILED '.$failure::class);

            return SymfonyCommand::FAILURE;
        } finally {
            $lock->release();
        }

        $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, $actor, SecurityOutcome::Succeeded, $runId);
        $line = sprintf('RECONCILIATION_%s run=%d products_checked=%d mismatches=%d blocks_written=%d other_differences=%d', $summary->outcome, $summary->runId, $summary->productsChecked, $summary->mismatches, $summary->blocksWritten, count($summary->differingSales) + count($summary->differingTokens));
        $this->line($line);

        if ($summary->differingSales !== [] || $summary->differingTokens !== []) {
            // RB-04: the Sales whose running totals differ and the tokens whose active mapping differs,
            // by internal key, for the operator and the Owner; the scheduler keeps no output, so the log too.
            $this->line('RECONCILIATION_DIFFERENCES sales='.implode(',', $summary->differingSales).' tokens='.implode(',', $summary->differingTokens));
            Log::warning('reconciliation.differences', ['run' => $summary->runId, 'sales' => $summary->differingSales, 'tokens' => $summary->differingTokens]);
        }

        if ($summary->outcome !== ReconciliationRuns::CLEAN) {
            Log::warning('reconciliation.mismatch', ['run' => $summary->runId, 'mismatches' => $summary->mismatches, 'blocks_written' => $summary->blocksWritten]);
        }

        if ($productId === null && ! $summary->pinged) {
            $this->line('RECONCILIATION_PING_FAILED');
        }

        // Only a clean run succeeds: the migration job's verification needs one (RELEASE_PROCESS REL-20).
        return $summary->outcome === ReconciliationRuns::CLEAN ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }
}
