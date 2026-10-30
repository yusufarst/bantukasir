<?php

namespace App\Console\Commands;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The framework of the operator procedures (SECURITY_MODEL section 14, common
 * rules; OPERATIONS_RUNBOOK OPR-02, OPR-06; THR-10; ROUTE_CONTRACTS section
 * 7; TASK-010 scope item 3), which OP-01 uses and OP-02 to OP-08 reuse: a
 * console command of the application, run by the operator — a person, never
 * an AI agent — inside the target environment's application container with
 * the runtime role (SCHEMA SCH-46).
 *
 * 1. Positive identification: the operator types the environment's name with
 *    --environment; it must equal the configured name and the database's
 *    marker (ENVIRONMENTS ENV-04), and nothing is read or written before.
 * 2. Typed options only, trimmed and validated, each refused with its reason;
 *    no option is ever passed to a shell or a path. The operator's note is
 *    required (1 to 1,000 characters), recorded with the audit events.
 * 3. The procedure, in its own transaction (perform()).
 * 4. After the transaction, committed or refused, the security log entry
 *    OPERATOR_PROCEDURE_RUN with the procedure as actor (SEC-LOG-03), naming
 *    the run by a random identifier.
 *
 * Its text is for the operator, in English like the runbooks. A one-time link
 * that a procedure creates is printed to the operator's terminal only, never
 * to a log (OPR-06); no secret is ever printed.
 */
abstract class OperatorProcedure extends Command
{
    /** Every environment a procedure may identify (ENVIRONMENTS ENV-04). */
    public const array ENVIRONMENTS = ['local', 'test', 'drill', 'staging', 'production'];

    /** A text option: valid UTF-8 without a control character (TR-VAL-05, THR-10). */
    public const string PLAIN_TEXT = '/\A[^\p{Cc}]*\z/u';

    /**
     * The rules the procedures use, whose messages are empty, so that the
     * validator looks up no translation: the refusal names the option.
     */
    private const array RULES = ['required', 'string', 'max', 'regex', 'email'];

    /**
     * The named procedure, the actor of its audit events and of its security
     * log entry (SCHEMA section 3.4).
     */
    abstract protected function process(): string;

    /**
     * The rules of the procedure's own options, by option name, besides
     * --environment and --note.
     *
     * @return array<string, list<string>>
     */
    abstract protected function optionRules(): array;

    /**
     * Runs the procedure with its validated, trimmed options and the note,
     * and returns the account its security log entry concerns.
     *
     * @param  array<string, string>  $options
     *
     * @throws ProcedureRefused
     */
    abstract protected function perform(array $options, string $note): ?int;

    final public function handle(SecurityLog $securityLog): int
    {
        try {
            $marker = EnvironmentMarker::require(DB::connection(), self::ENVIRONMENTS);
        } catch (DatabaseRefused $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        if ($this->option('environment') !== $marker) {
            $this->error(sprintf('The typed environment %s is not the environment %s of this database (OPERATIONS_RUNBOOK OPR-02).', json_encode($this->option('environment')), json_encode($marker)));

            return self::FAILURE;
        }

        $run = (string) Str::uuid();
        $options = $this->typedOptions();
        $validator = Validator::make($options, [
            ...$this->optionRules(),
            'note' => ['required', 'string', 'max:1000', 'regex:'.self::PLAIN_TEXT],
        ], array_fill_keys(self::RULES, ''));

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $option) {
                $this->error(sprintf('The option --%s is missing or not valid.', $option));
            }

            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, SecurityActor::operatorProcedure($this->process()), SecurityOutcome::Refused, $run);

            return self::FAILURE;
        }

        $note = $options['note'];
        unset($options['note']);

        try {
            $subject = $this->perform($options, $note);
        } catch (ProcedureRefused $refusal) {
            $this->error($refusal->getMessage());
            $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, SecurityActor::operatorProcedure($this->process()), SecurityOutcome::Refused, $run);

            return self::FAILURE;
        }

        $securityLog->recordRun(SecurityEvent::OperatorProcedureRun, SecurityActor::operatorProcedure($this->process()), SecurityOutcome::Succeeded, $run, $subject);
        $this->info(sprintf('Run %s recorded in the security log.', $run));

        return self::SUCCESS;
    }

    /**
     * The procedure's options and the note, trimmed of outer white space
     * (TR-VAL-05); a missing option is an empty text, which the rules refuse.
     *
     * @return array<string, string>
     */
    private function typedOptions(): array
    {
        $options = [];

        foreach ([...array_keys($this->optionRules()), 'note'] as $name) {
            $value = $this->option($name);
            $options[$name] = is_string($value) ? Str::trim($value) : '';
        }

        return array_filter($options, static fn (string $value): bool => $value !== '');
    }
}
