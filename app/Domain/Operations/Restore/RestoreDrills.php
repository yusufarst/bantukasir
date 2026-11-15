<?php

namespace App\Domain\Operations\Restore;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The record of the restore drills (SCHEMA section 5.12, `restore_drills`; SECURITY_MODEL SEC-OP-08;
 * BACKUP_RECOVERY RB-02 step 10), a service of the Operations module, which alone writes it: one row per
 * drill, written once by OP-08 with the procedure as its actor, never changed or removed. The system
 * status of TASK-039 reads the newest.
 */
final class RestoreDrills
{
    public const string PROCESS = 'restore_drill_record';

    public const array KINDS = ['ROUTINE', 'FULL'];

    public const array RESULTS = ['PASS', 'FAIL'];

    /**
     * The backup of this identifier as backup_runs holds it, when its manifest was stored: the SHA-256 of
     * its manifest object as hexadecimal; null otherwise (BACKUP_RECOVERY section 4 step 6).
     */
    public function storedManifestOf(string $backupId): ?string
    {
        $hash = DB::scalar("select encode(manifest_object_sha256, 'hex') from backup_runs where backup_id = ? and manifest_object_sha256 is not null", [$backupId]);

        return is_string($hash) ? $hash : null;
    }

    /**
     * Records one drill, in the caller's transaction, and returns its internal key.
     *
     * @param  array{kind: string, backupId: string, manifestSha256: string, startedAt: string, finishedAt: string, result: string, restoreSeconds: int}  $drill
     */
    public function record(array $drill, string $operatorNote, string $recordedAt): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A drill is recorded in the transaction of its procedure.');
        }

        $id = DB::table('restore_drills')->insertGetId([
            'kind' => $drill['kind'],
            'backup_id' => $drill['backupId'],
            'manifest_sha256' => '\\x'.$drill['manifestSha256'],
            'started_at' => $drill['startedAt'],
            'finished_at' => $drill['finishedAt'],
            'result' => $drill['result'],
            'restore_seconds' => $drill['restoreSeconds'],
            'operator_note' => $operatorNote,
            'recorded_at' => $recordedAt,
            'actor_kind' => 'OPERATOR_PROCEDURE',
            'actor_user_id' => null,
            'actor_process' => self::PROCESS,
        ]);

        return is_int($id) ? $id : throw new LogicException('The database returned no key for the drill.');
    }
}
