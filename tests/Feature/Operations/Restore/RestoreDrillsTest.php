<?php

namespace Tests\Feature\Operations\Restore;

use App\Database\CommandPoints;
use App\Database\DatabaseRoles;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Tests\Harness\Backup\BackupTestCase;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;

/**
 * restore_drills and OP-08 `restore:record-drill` (TASK-037 scope items 1 and 4, AC-037-07; SCHEMA
 * sections 5.12 and 10; SECURITY_MODEL SEC-OP-08 and section 14; BACKUP_RECOVERY RB-02 step 10;
 * EXECUTION_CONTEXT section 10, a table and an operator procedure; suites DB01, KEEP01, OPPROC01,
 * STATUS01): each check refuses its violation; the runtime role inserts and reads, and no role of the
 * application changes or removes a row; the actual grants are those of SCHEMA section 10; OP-08 records a
 * drill of a backup of this environment with the procedure as actor and its run in the security log,
 * refuses with nothing written what RB-02 cannot have measured, and leaves nothing after a failure.
 */
final class RestoreDrillsTest extends BackupTestCase
{
    private const string NOTE = 'Uji pemulihan rutin: unduh 2 s, pulihkan 9 s, aplikasi 3 s';

    public function test_each_check_refuses_its_violation(): void
    {
        $checks = [
            'restore_drills_kind_allowed' => ['kind' => 'PARTIAL'],
            'restore_drills_backup_id_format' => ['backup_id' => '20301112T000000Z'],
            'restore_drills_manifest_sha256_length' => ['manifest_sha256' => '\x00ff'],
            'restore_drills_result_allowed' => ['result' => 'PARTIAL'],
            'restore_drills_restore_seconds_not_negative' => ['restore_seconds' => -1],
            'restore_drills_operator_note_length' => ['operator_note' => ''],
            'restore_drills_finished_after_started' => ['finished_at' => '2030-11-12 00:59:59+00'],
            'restore_drills_actor' => ['actor_process' => 'owner_recovery'],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $c->table('restore_drills')->insert([...self::row(), ...$values]), '23514', $constraint, $this->runtime());
        }

        $this->runtime()->table('restore_drills')->insert(self::row());
        $this->assertSame(1, $this->observer()->table('restore_drills')->count());
    }

    public function test_the_runtime_role_inserts_and_no_role_of_the_application_changes_a_row(): void
    {
        $this->runtime()->table('restore_drills')->insert(self::row());

        $this->assertDenied($this->backup(), fn (Connection $c) => $c->table('restore_drills')->insert(self::row()));

        foreach ([$this->runtime(), $this->backup()] as $connection) {
            $this->assertDenied($connection, fn (Connection $c) => $c->table('restore_drills')->update(['result' => 'FAIL']));
            $this->assertDenied($connection, fn (Connection $c) => $c->table('restore_drills')->delete());
            $this->assertDenied($connection, fn (Connection $c) => $c->table('restore_drills')->truncate());
        }

        $this->assertSame(1, $this->runtime()->table('restore_drills')->count());
        $this->assertSame(1, $this->backup()->table('restore_drills')->count());

        $roles = DatabaseRoles::of($this->migration());
        $privileges = fn (string $role): array => array_map(
            fn (mixed $row): mixed => ((array) $row)['privilege_type'] ?? null,
            $this->migration()->select('select privilege_type from information_schema.role_table_grants where table_name = ? and grantee = ? order by privilege_type', ['restore_drills', $role]),
        );

        $this->assertSame(['INSERT', 'SELECT'], $privileges($roles->runtime));
        $this->assertSame(['SELECT'], $privileges($roles->backup));
    }

    public function test_op08_records_a_drill_of_a_backup_of_this_environment(): void
    {
        [$backupId, $manifest] = $this->backupWithManifest();

        $this->assertSame(0, $this->record(['--backup' => $backupId, '--manifest-sha256' => $manifest]), Artisan::output());

        $drill = (array) $this->observer()->selectOne("select kind, backup_id, encode(manifest_sha256, 'hex') as manifest, to_char(started_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') as started, result, restore_seconds, operator_note, actor_kind, actor_user_id, actor_process from restore_drills");
        $this->assertSame(['ROUTINE', $backupId, $manifest, '2030-11-12T01:00:00Z', 'PASS', 9, self::NOTE, 'OPERATOR_PROCEDURE', null, 'restore_drill_record'], array_values($drill));

        // Changes no business data: no audit event; the run is in the security log.
        $this->assertSame(0, $this->observer()->table('audit_events')->where('actor_process', 'restore_drill_record')->count());
        $entry = (array) $this->observer()->table('security_log_entries')->where('actor_process', 'restore_drill_record')->sole();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'SUCCEEDED'], [$entry['event_type'], $entry['outcome_code']]);

        // A drill that failed is recorded with what the operator measured.
        $this->assertSame(0, $this->record(['--backup' => $backupId, '--manifest-sha256' => str_repeat('ab', 32), '--result' => 'FAIL']), Artisan::output());
        $this->assertSame(['PASS', 'FAIL'], $this->observer()->table('restore_drills')->orderBy('id')->pluck('result')->all());
    }

    public function test_op08_refuses_with_nothing_written_what_the_drill_cannot_have_measured(): void
    {
        [$backupId, $manifest] = $this->backupWithManifest();
        $cases = [
            ['--backup' => '20301112T000000Z-AAAAAAAA', '--manifest-sha256' => $manifest],
            ['--backup' => $backupId, '--manifest-sha256' => str_repeat('ab', 32)],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--started-at' => '2030-11-12T01:00:10Z', '--finished-at' => '2030-11-12T01:00:00Z'],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--restore-seconds' => '601'],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--finished-at' => '2030-11-13T03:00:00Z'],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--started-at' => '2026-02-30T01:00:00Z'],
            ['--backup' => $backupId, '--manifest-sha256' => strtoupper($manifest)],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--kind' => 'DAILY'],
            ['--backup' => $backupId, '--manifest-sha256' => $manifest, '--restore-seconds' => '-1'],
            ['--backup' => $backupId.' ; true', '--manifest-sha256' => $manifest],
        ];

        foreach ($cases as $options) {
            $this->assertSame(1, $this->record($options), json_encode($options).' '.Artisan::output());
        }

        $this->assertSame(1, Artisan::call('restore:record-drill', ['--environment' => 'production', '--kind' => 'ROUTINE', '--backup' => $backupId, '--manifest-sha256' => $manifest, '--started-at' => '2030-11-12T01:00:00Z', '--finished-at' => '2030-11-12T01:10:00Z', '--result' => 'PASS', '--restore-seconds' => '9', '--note' => self::NOTE]));
        $this->assertSame(1, Artisan::call('restore:record-drill', ['--environment' => 'test', '--kind' => 'ROUTINE', '--backup' => $backupId, '--manifest-sha256' => $manifest, '--started-at' => '2030-11-12T01:00:00Z', '--finished-at' => '2030-11-12T01:10:00Z', '--result' => 'PASS', '--restore-seconds' => '9']));
        $this->assertSame(0, $this->observer()->table('restore_drills')->count());
        $this->assertSame('REFUSED', $this->observer()->table('security_log_entries')->where('actor_process', 'restore_drill_record')->orderByDesc('id')->value('outcome_code'));
    }

    public function test_a_failure_after_its_write_step_leaves_nothing(): void
    {
        [$backupId, $manifest] = $this->backupWithManifest();
        $this->app->instance(CommandPoints::class, new FailurePoints(1));

        try {
            $this->record(['--backup' => $backupId, '--manifest-sha256' => $manifest]);
            $this->fail('The injected failure did not happen at write step 1.');
        } catch (InjectedFailure) {
            $this->assertSame(0, $this->observer()->table('restore_drills')->count());
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(0, $this->record(['--backup' => $backupId, '--manifest-sha256' => $manifest]), Artisan::output());
    }

    /**
     * A backup of the test environment stored with its manifest by backup:run: its identifier and the
     * SHA-256 of its manifest object.
     *
     * @return array{string, string}
     */
    private function backupWithManifest(): array
    {
        $this->atJakarta('2030-11-12 07:00:00');
        $outcome = $this->runBackup();
        $this->assertNull($outcome->failure, $outcome->diagnostic);
        $this->atJakarta('2030-11-12 08:30:00');

        return [$outcome->backupId, $this->readText($this->rowOf($outcome->backupId)['manifest'] ?? null)];
    }

    /**
     * @param  array<string, string>  $options
     */
    private function record(array $options): int
    {
        return Artisan::call('restore:record-drill', [
            '--environment' => 'test',
            '--kind' => 'ROUTINE',
            '--started-at' => '2030-11-12T01:00:00Z',
            '--finished-at' => '2030-11-12T01:10:00Z',
            '--result' => 'PASS',
            '--restore-seconds' => '9',
            '--note' => self::NOTE,
            ...$options,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(): array
    {
        return [
            'kind' => 'ROUTINE',
            'backup_id' => '20301112T000000Z-AbCd1234',
            'manifest_sha256' => '\x'.str_repeat('ab', 32),
            'started_at' => '2030-11-12 01:00:00+00',
            'finished_at' => '2030-11-12 01:10:00+00',
            'result' => 'PASS',
            'restore_seconds' => 9,
            'operator_note' => 'Uji pemulihan',
            'recorded_at' => '2030-11-12 01:11:00+00',
            'actor_kind' => 'OPERATOR_PROCEDURE',
            'actor_user_id' => null,
            'actor_process' => 'restore_drill_record',
        ];
    }
}
