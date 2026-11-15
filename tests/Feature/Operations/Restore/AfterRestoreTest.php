<?php

namespace Tests\Feature\Operations\Restore;

use App\Database\CommandPoints;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;
use Tests\Harness\Restore\TestRestore;

/**
 * OP-04 `restore:after` after the restores of TEST_STRATEGY TS-24 (AC-037-01, AC-037-04, AC-037-07;
 * SECURITY_MODEL SEC-OP-04 and section 14; BACKUP_RECOVERY RB-01 step 12; ADR-007 section 6; SCHEMA
 * section 5.7 and guards G-12, G-26; D-072; RACE-12; suites REC02, OPEN01, OPPROC01): a recovery epoch never
 * issued before, every sign-in session revoked, stale intents refused EPOCH_STALE with one report, also after
 * the same backup is restored twice, while an intent whose identity is in the restored data answers
 * COMMITTED; an onboarding restore hold when the restored state is not RELEASED, which refuses every
 * onboarding and opening command until OP-05 records the Owner's outcome, and each outcome as D-072 says;
 * the common rules of the operator procedures; nothing left after a failure at any write step.
 */
final class AfterRestoreTest extends DatabaseTestCase
{
    use CommandRequests;

    private const string NOTE = 'Pemulihan insiden S1 atas izin Pemilik';

    private string $area = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
        $this->area = sys_get_temp_dir().'/bk-restore-test-'.Str::random(12);
        File::ensureDirectoryExists($this->area, 0700);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->area);

        parent::tearDown();
    }

    public function test_op04_fences_the_old_world_also_when_the_same_backup_is_restored_twice(): void
    {
        // RACE-12, AC-037-01. In the dump: a probe that committed. After it: one that the restore loses.
        $this->as('Agus');
        $kept = $this->envelope();
        $commandId = $this->probe($kept)->assertStatus(201)->json('commandId');
        $dump = TestRestore::dump($this->area);
        $lost = $this->envelope();
        $this->probe($lost)->assertStatus(201);
        $issued = [$this->epoch];

        TestRestore::restore($dump);
        $this->assertSame(0, $this->observer()->table('sessions')->count(), 'No sign-in session comes back from a backup (BACKUP_RECOVERY section 2).');

        // A sign-in on the restored data before the fence: OP-04 revokes it, and every other.
        $early = $this->signInBrowser($this->people['Agus']['email'], $this->people['Agus']['password']);
        $this->assertSame(1, $this->observer()->table('sessions')->count());
        $this->restoreAfter();
        $this->assertSame(0, $this->observer()->table('sessions')->count());
        $this->browse($early);
        $this->getJson('/data/shell')->assertStatus(401);

        $issued[] = $this->epoch = TokoContoh::recoveryEpoch();
        $this->assertNotContains($this->epoch, [$issued[0]]);
        $revoked = $this->observer()->table('security_log_entries')->where('actor_process', 'restore_after')->orderBy('id')->get(['event_type', 'outcome_code', 'subject_user_id'])->map(static fn (object $row): array => array_values((array) $row))->all();
        $this->assertSame([['OPERATOR_PROCEDURE_RUN', 'SUCCEEDED', null], ['SESSION_REVOKED', 'SUCCEEDED', $this->people['Agus']['id']]], $revoked);

        // The lost intent, of the earlier epoch: refused, also its status check, with one report.
        $this->browse($this->signInBrowser($this->people['Agus']['email'], $this->people['Agus']['password']));
        $this->probe($lost)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->check($lost)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(1, $this->observer()->table('stale_intent_reports')->where('source_intent_id', $lost['sourceIntentId'])->count());
        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->whereIn('command_id', $this->observer()->table('command_identities')->where('source_intent_id', $lost['sourceIntentId'])->select('id'))->count());

        // The intent whose identity the restored data hold: a verified outcome.
        $this->check($kept)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('commandId', $commandId);

        // A request of the new world, then the same backup restored again: another epoch, never issued
        // before, refuses the requests of the first restore's world too.
        $between = $this->envelope();
        $this->probe($between)->assertStatus(201);
        TestRestore::restore($dump);
        $this->restoreAfter();
        $issued[] = $this->epoch = TokoContoh::recoveryEpoch();
        $this->assertCount(3, array_unique($issued));

        $this->browse($this->signInBrowser($this->people['Agus']['email'], $this->people['Agus']['password']));
        $this->probe($between)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->probe($lost)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->check($kept)->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->probe($this->envelope())->assertStatus(201);
    }

    public function test_a_hold_refuses_every_onboarding_and_opening_command_until_op05_records_either_outcome(): void
    {
        // OPEN01's restore case, AC-037-04: the dump while onboarding was ACTIVE, the release after it.
        $category = TokoContoh::category();
        [$counted, $zeroed, $reversed, $other] = array_map(static fn (string $sku): int => TokoContoh::product($sku, ['categoryId' => $category]), ['OB-01', 'OB-02', 'OB-03', 'OB-04']);
        TokoContoh::startOnboarding();
        TokoContoh::openingStock($counted, '5');
        $zeroCount = TokoContoh::zeroCount($zeroed);
        $reversedLine = TokoContoh::openingLineOf(TokoContoh::openingStock($reversed, '3'));
        TokoContoh::reverse(TokoContoh::openingOf($reversed));
        $state = $this->stateRow();
        $version = self::whole($state['version'] ?? null);
        $dump = TestRestore::dump($this->area);
        TokoContoh::releaseOnboarding();
        $this->assertSame('RELEASED', $this->stateRow()['state']);

        foreach (['RELEASED_BEFORE_RESTORE', 'NEVER_RELEASED'] as $outcome) {
            TestRestore::restore($dump);
            $this->assertSame($state, $this->stateRow());
            $this->restoreAfter();

            // The hold of the restored state with this run's epoch; the version one on, the state's last
            // change and author as restored (RP-412's "aktif sejak", TASK-035 R-10).
            $hold = (array) $this->observer()->selectOne("select id, restored_state, encode(recovery_epoch, 'hex') as epoch from onboarding_restore_holds");
            $this->assertSame(['ACTIVE', TokoContoh::recoveryEpoch()], [$hold['restored_state'], $hold['epoch']]);
            $this->assertSame([...$state, 'version' => $version + 1], $this->stateRow());
            $holdKey = (string) self::whole($hold['id'] ?? null);
            $event = (array) $this->observer()->table('audit_events')->where('action', 'ONBOARDING_RESTORE_HOLD')->sole();
            $this->assertSame(['OPERATOR_PROCEDURE', 'restore_after', self::NOTE, 'onboarding', 1], [$event['actor_kind'], $event['actor_process'], $event['operator_note'], $event['subject_type'], $event['subject_id']]);

            $this->assertEveryOnboardingCommandRefused($other, $zeroCount, $reversedLine);

            $this->assertSame(0, $this->reconcile(['--hold' => $holdKey, '--outcome' => $outcome]), Artisan::output());
            $reconciliation = (array) $this->observer()->table('onboarding_restore_reconciliations')->sole();
            $this->assertSame([$hold['id'], $outcome, $this->people['Hendra']['id'], self::NOTE], [$reconciliation['hold_id'], $reconciliation['outcome'], $reconciliation['decided_by_user_id'], $reconciliation['operator_note']]);
            $decision = (array) $this->observer()->table('audit_events')->where('action', 'RESTORE_RECONCILIATION')->sole();
            $this->assertSame(['OPERATOR_PROCEDURE', 'restore_reconciliation', self::NOTE], [$decision['actor_kind'], $decision['actor_process'], $decision['operator_note']]);

            if ($outcome === 'RELEASED_BEFORE_RESTORE') {
                // Released before the restore: it stays released, and nothing starts it again (D-071).
                $this->assertSame(['state' => 'RELEASED', 'version' => $version + 2, 'changed_by_user_id' => null], array_diff_key($this->stateRow(), ['changed_at' => 0]));
                $this->assertSame(['RESTORE_RECONCILIATION', $this->people['Hendra']['id'], null], array_values((array) $this->observer()->table('onboarding_releases')->first(['kind', 'actor_user_id', 'command_id'])));
                $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::startOnboarding());
                $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::openingStock($other, '2'));
                TokoContoh::stock($other, '2');
            } else {
                // Never released: the restored period continues as the same period (D-072).
                $this->assertSame([...$state, 'version' => $version + 1], $this->stateRow());
                TokoContoh::openingStock($other, '2');
                TokoContoh::withdrawZeroCount($zeroCount);
                TokoContoh::replaceOpening($reversedLine, '4');
                TokoContoh::releaseOnboarding();
                $this->assertSame('RELEASE', $this->observer()->table('onboarding_releases')->value('kind'));
            }

            // A second decision of the same hold is refused with nothing written.
            $this->assertSame(1, $this->reconcile(['--hold' => $holdKey, '--outcome' => $outcome]));
            $this->assertSame(1, $this->observer()->table('onboarding_restore_reconciliations')->count());
        }
    }

    public function test_a_restored_state_not_started_is_held_and_released_data_need_no_hold(): void
    {
        // D-072 and D-071: a backup from before the start, the onboarding started and released after it.
        $other = TokoContoh::product('OB-05', ['categoryId' => TokoContoh::category()]);
        $dump = TestRestore::dump($this->area);
        TokoContoh::startOnboarding();
        TokoContoh::releaseOnboarding();
        $released = TestRestore::dump($this->area);

        TestRestore::restore($dump);
        $this->restoreAfter();
        $hold = (string) self::whole($this->observer()->table('onboarding_restore_holds')->value('id'));
        $this->assertSame('NOT_STARTED', $this->observer()->table('onboarding_restore_holds')->value('restored_state'));
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::startOnboarding());

        // Released before the restore: it can never be started again.
        $this->assertSame(0, $this->reconcile(['--hold' => $hold, '--outcome' => 'RELEASED_BEFORE_RESTORE']), Artisan::output());
        $this->assertSame('RELEASED', $this->stateRow()['state']);
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::startOnboarding());
        TokoContoh::stock($other, '1');

        // The restore of data released already: no hold and no audit event of one.
        TestRestore::restore($released);
        $this->restoreAfter();
        $this->assertSame(0, $this->observer()->table('onboarding_restore_holds')->count());
        $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'ONBOARDING_RESTORE_HOLD')->count());
        $this->assertStringContainsString('no hold is needed', Artisan::output());
    }

    public function test_both_restore_procedures_follow_the_common_rules_of_the_operator_procedures(): void
    {
        // AC-037-07; SECURITY_MODEL section 14; OPR-02; THR-10: the environment typed and equal to the
        // marker before anything is read; typed options only; the note required; nothing written.
        $epoch = TokoContoh::recoveryEpoch();
        $cases = [
            ['restore:after', ['--note' => self::NOTE]],
            ['restore:after', ['--environment' => 'production', '--note' => self::NOTE]],
            ['restore:after', ['--environment' => 'test']],
            ['restore:after', ['--environment' => 'test', '--note' => "Catatan\u{0007}"]],
            ['restore:reconcile', ['--environment' => 'test', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--report' => '1', '--hold' => '1', '--outcome' => 'NEVER_RELEASED', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--report' => '1', '--outcome' => 'NEVER_RELEASED', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--hold' => '1', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--hold' => '1', '--outcome' => 'RELEASED', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--report' => '1 or 1=1', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--report' => '99', '--note' => self::NOTE]],
            ['restore:reconcile', ['--environment' => 'test', '--hold' => '99', '--outcome' => 'NEVER_RELEASED', '--note' => self::NOTE]],
        ];

        foreach ($cases as [$command, $options]) {
            $this->assertSame(1, Artisan::call($command, $options), $command.' '.json_encode($options));
        }

        $this->assertSame($epoch, TokoContoh::recoveryEpoch());
        $this->assertSame(0, $this->observer()->table('audit_events')->whereIn('action', ['ONBOARDING_RESTORE_HOLD', 'RESTORE_RECONCILIATION'])->count());
        $this->assertSame(['REFUSED'], $this->observer()->table('security_log_entries')->whereIn('actor_process', ['restore_after', 'restore_reconciliation'])->distinct()->pluck('outcome_code')->all());
    }

    public function test_a_failure_after_every_write_step_of_op04_leaves_nothing(): void
    {
        // TS-21: the epoch, the sessions, the hold with the version, its audit event.
        TokoContoh::startOnboarding();
        $this->as('Agus');
        $before = [TokoContoh::recoveryEpoch(), $this->stateRow(), $this->observer()->table('sessions')->count()];
        $this->assertSame(1, $before[2]);

        for ($step = 1; $step <= 4; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            try {
                $this->restoreAfter();
                $this->fail('The injected failure did not happen at write step '.$step);
            } catch (InjectedFailure) {
                $this->assertSame($before, [TokoContoh::recoveryEpoch(), $this->stateRow(), $this->observer()->table('sessions')->count()], 'after write step '.$step);
                $this->assertSame(0, $this->observer()->table('onboarding_restore_holds')->count());
                $this->assertSame(0, $this->observer()->table('audit_events')->where('action', 'ONBOARDING_RESTORE_HOLD')->count());
            }
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->restoreAfter();
        $this->assertSame(1, $this->observer()->table('onboarding_restore_holds')->count());
    }

    /**
     * Every onboarding and opening command refused STALE_VERSION while the hold waits, with nothing
     * written (G-26; RC-11 to RC-14, RC-48).
     */
    private function assertEveryOnboardingCommandRefused(int $product, int $zeroCount, int $reversedLine): void
    {
        $facts = [$this->snapshot('opening_stock_lines'), $this->snapshot('onboarding_zero_count_withdrawals', 'zero_count_id'), $this->snapshot('stock_movements')];

        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::openingStock($product, '2'));
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::zeroCount($product));
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::withdrawZeroCount($zeroCount));
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::replaceOpening($reversedLine, '4'));
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::releaseOnboarding());
        $this->assertRefused(ErrorCode::StaleVersion, static fn () => TokoContoh::startOnboarding());

        $this->assertSame($facts, [$this->snapshot('opening_stock_lines'), $this->snapshot('onboarding_zero_count_withdrawals', 'zero_count_id'), $this->snapshot('stock_movements')]);
    }

    private function assertRefused(ErrorCode $code, Closure $command): void
    {
        try {
            $command();
            $this->fail('The command was not refused; expected '.$code->value.'.');
        } catch (CommandRefused $refusal) {
            $this->assertSame($code, $refusal->error);
        }
    }

    /**
     * The onboarding state's row as committed.
     *
     * @return array<string, mixed>
     */
    private function stateRow(): array
    {
        return (array) $this->observer()->table('onboarding_state')->where('id', 1)->first(['state', 'version', 'changed_at', 'changed_by_user_id']);
    }

    /**
     * A whole number that the database returned, as an internal key or a version is.
     */
    private static function whole(mixed $value): int
    {
        self::assertIsInt($value);

        return $value;
    }

    private function restoreAfter(): void
    {
        $this->assertSame(0, Artisan::call('restore:after', ['--environment' => 'test', '--note' => self::NOTE]), Artisan::output());
    }

    /**
     * @param  array<string, string>  $options
     */
    private function reconcile(array $options): int
    {
        return Artisan::call('restore:reconcile', ['--environment' => 'test', '--note' => self::NOTE, ...$options]);
    }
}
