<?php

namespace App\Console\Commands;

use App\Actions\Operations\RecordRestoreDrill;
use App\Domain\Operations\Restore\RestoreDrills;
use App\Domain\Operations\Restore\RestoreRefused;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * OP-08 `restore:record-drill` (ROUTE_CONTRACTS OP-08 and section 7; SECURITY_MODEL SEC-OP-08 and section
 * 14; BACKUP_RECOVERY RB-02 step 10; TASK-037 scope item 4): after a drill, the operator records it in the
 * environment whose backup was drilled — the kind, the backup identifier, the manifest's SHA-256, the
 * drill's start and end in UTC, its result and the measured restore time — every value typed and
 * validated here. The environment and the note are OperatorProcedure's.
 */
final class RestoreRecordDrill extends OperatorProcedure
{
    private const string UTC = 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D';

    protected $signature = 'restore:record-drill
        {--environment= : The name of the environment, typed by the operator; it must equal the configured name and the database marker}
        {--kind= : ROUTINE for the drill of RB-02, FULL for the full recovery estimate}
        {--backup= : The identifier of the backup restored, as backup_runs holds it}
        {--manifest-sha256= : The SHA-256 of its manifest object, 64 lowercase hexadecimal digits}
        {--started-at= : When the drill started, in UTC: YYYY-MM-DDTHH:MM:SSZ}
        {--finished-at= : When the drill finished, in UTC: YYYY-MM-DDTHH:MM:SSZ}
        {--result= : PASS or FAIL}
        {--restore-seconds= : The measured time of the restore steps, in whole seconds}
        {--note= : The operator\'s note, 1 to 1,000 characters, with the measured durations of the steps}';

    protected $description = 'OP-08: record a restore drill in restore_drills';

    protected function process(): string
    {
        return RestoreDrills::PROCESS;
    }

    protected function optionRules(): array
    {
        return [
            'kind' => ['required', 'string', 'regex:/^(?:ROUTINE|FULL)$/D'],
            'backup' => ['required', 'string', 'regex:/^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}$/D'],
            'manifest-sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D'],
            'started-at' => ['required', 'string', self::UTC],
            'finished-at' => ['required', 'string', self::UTC],
            'result' => ['required', 'string', 'regex:/^(?:PASS|FAIL)$/D'],
            'restore-seconds' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})$/D'],
        ];
    }

    protected function perform(array $options, string $note): ?int
    {
        try {
            $startedAt = self::utc($options['started-at'] ?? '');
            $finishedAt = self::utc($options['finished-at'] ?? '');
        } catch (Throwable) {
            throw new ProcedureRefused('The options --started-at and --finished-at are times in UTC that exist.');
        }

        try {
            $id = app(RecordRestoreDrill::class)([
                'kind' => $options['kind'] ?? '',
                'backupId' => $options['backup'] ?? '',
                'manifestSha256' => $options['manifest-sha256'] ?? '',
                'startedAt' => $startedAt,
                'finishedAt' => $finishedAt,
                'result' => $options['result'] ?? '',
                'restoreSeconds' => (int) ($options['restore-seconds'] ?? '0'),
            ], $note);
        } catch (RestoreRefused $refusal) {
            throw new ProcedureRefused($refusal->getMessage());
        }

        $this->info(sprintf('Drill %d is recorded: the system status shows it as the latest restore drill.', $id));

        return null;
    }

    /**
     * A typed time in UTC, refused unless it exists as written (2026-02-30 does not).
     */
    private static function utc(string $value): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, 'UTC');

        if (! $time instanceof CarbonImmutable || $time->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new InvalidArgumentException('Not a time that exists.');
        }

        return $time;
    }
}
