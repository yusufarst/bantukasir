<?php

namespace Tests\Feature\Operations\Restore;

use App\Actions\Sales\CheckoutSale;
use App\Database\CommandPoints;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\RequestReceipts;
use App\Support\Time\Clock;
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
 * OP-05 `restore:reconcile` of the requests from before a restore (AC-037-03 to AC-037-05, AC-037-07;
 * SECURITY_MODEL SEC-OP-05 and section 14; ADR-009 sections 1 and 3; INTERACTION_CONTRACTS sections 2.1,
 * 2.3 and 2.5; BACKUP_RECOVERY RB-01 steps 15, 16, 17 and 21; SCHEMA section 5.3 and guard G-27; D-073,
 * D-089; suites REC02, SESSION05, OPPROC01), after the restores of TEST_STRATEGY TS-24: a request reported
 * EPOCH_STALE is recorded NOT_COMMITTED with a fence of the current epoch, which settles its held receipts
 * in the same transaction, so that its status check answers NOT_COMMITTED and a late original is refused
 * INTENT_ABANDONED; the Owner's own user-management request is reconciled before its security change is
 * applied again; an earlier-epoch checkout receipt keeps a close refused until the status check finds its
 * identity in the restored data or OP-05 settles it; nothing is left after a failure at any write step.
 */
final class RestoreReconciliationTest extends DatabaseTestCase
{
    use CommandRequests;

    private const string NOTE = 'Keputusan Pemilik: tidak tersimpan pada data pulihan';

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

    public function test_op05_records_a_reported_request_not_committed_with_a_fence_that_settles_its_receipts(): void
    {
        // AC-037-03, REC02: a probe committed after the backup point, lost by the restore.
        $this->as('Agus');
        $dump = TestRestore::dump($this->area);
        $lost = $this->envelope();
        $this->probe($lost)->assertStatus(201);
        $old = $this->epoch;
        $this->restoreAndFence($dump);

        // Its repeats are held, and its status check reports it, once.
        $this->browse($this->signInBrowser($this->people['Agus']['email'], $this->people['Agus']['password']));
        $this->probe($lost)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->probe([...$lost, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->check($lost)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(['HELD', 'HELD'], $this->outcomesOf($lost));
        $report = $this->reportOf($lost);

        $this->assertSame(0, $this->reconcile($report), Artisan::output());

        $hendra = $this->people['Hendra']['id'];
        $fence = (array) $this->observer()->selectOne("select id, kind, actor_kind, actor_user_id, encode(recovery_epoch, 'hex') as epoch from command_identities where source_intent_id = ?", [$lost['sourceIntentId']]);
        $this->assertSame(['FENCE', 'USER', $hendra, $this->epoch], [$fence['kind'], $fence['actor_kind'], $fence['actor_user_id'], $fence['epoch']]);
        $this->assertNotSame($old, $this->epoch);
        $reconciliation = (array) $this->observer()->table('intent_reconciliations')->sole();
        $this->assertSame([$report, 'NOT_COMMITTED', $fence['id'], $hendra, self::NOTE], [$reconciliation['report_id'], $reconciliation['outcome'], $reconciliation['fence_command_id'], $reconciliation['decided_by_user_id'], $reconciliation['operator_note']]);

        // The settlement of every held receipt, which keeps why it was held (D-089).
        foreach ($this->receiptsOf($lost) as $receipt) {
            $this->assertSame(['NOT_COMMITTED', 'EPOCH_STALE', $fence['id'], $hendra], [$receipt['outcome'], $receipt['held_code'], $receipt['settled_by_command_id'], $receipt['settled_by_user_id']]);
        }

        $event = (array) $this->observer()->table('audit_events')->where('action', 'RESTORE_RECONCILIATION')->sole();
        $this->assertSame(['OPERATOR_PROCEDURE', 'restore_reconciliation', self::NOTE, 'stale_intent_report', $report], [$event['actor_kind'], $event['actor_process'], $event['operator_note'], $event['subject_type'], $event['subject_id']]);
        $this->assertSame('SUCCEEDED', $this->observer()->table('security_log_entries')->where('actor_process', 'restore_reconciliation')->value('outcome_code'));

        // The status check now answers NOT_COMMITTED; a late original is refused before any fact.
        $this->check($lost)->assertOk()->assertExactJson(['outcome' => 'NOT_COMMITTED', 'operation' => $lost['operation']]);
        $this->probe([...$lost, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'INTENT_ABANDONED');
        $this->assertSame(0, $this->observer()->table('harness_probe_facts')->where('note', 'Catatan uji')->count());

        // A decision is recorded once.
        $before = [$this->snapshot('intent_reconciliations', 'report_id'), $this->snapshot('command_identities')];
        $this->assertSame(1, $this->reconcile($report));
        $this->assertStringContainsString('reconciled already', Artisan::output());
        $this->assertSame($before, [$this->snapshot('intent_reconciliations', 'report_id'), $this->snapshot('command_identities')]);
    }

    public function test_the_owners_user_management_request_is_reconciled_before_its_security_change_is_applied_again(): void
    {
        // AC-037-04, RB-01 steps 16 and 17: the Owner deactivated Sari after the backup point.
        $sari = TokoContoh::staff('Sari')['id'];
        $dump = TestRestore::dump($this->area);
        $this->as('Hendra');
        $this->confirm();
        $deactivation = $this->envelope(['userId' => $sari], ['operation' => 'user.deactivate', 'expected' => ['userVersion' => $this->versionOf($sari)]]);
        $this->postJson('/commands/user.deactivate', $deactivation)->assertStatus(201);
        $this->restoreAndFence($dump);
        $this->assertSame('ACTIVE', $this->observer()->table('users')->where('id', $sari)->value('status'));

        // The Owner signs in again on his device: his held request is listed on every device (RD-04).
        $this->browse($this->signInBrowser($this->people['Hendra']['email'], $this->people['Hendra']['password']));
        $this->confirm();
        $this->postJson('/commands/user.deactivate', $deactivation)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame([$deactivation['sourceIntentId']], array_column(self::rowsOf($this->getJson('/data/command-requests')->assertOk()), 'sourceIntentId'));

        // First the reconciliation of his own user-management request, which releases the reference…
        $this->assertSame(0, $this->reconcile($this->reportOf($deactivation)), Artisan::output());
        $this->assertSame([], self::rowsOf($this->getJson('/data/command-requests')->assertOk()));
        $this->check($deactivation)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');

        // …then the security change after the backup point applied again, as a new request.
        $this->postJson('/commands/user.deactivate', $this->envelope(['userId' => $sari], ['operation' => 'user.deactivate', 'expected' => ['userVersion' => $this->versionOf($sari)]]))->assertStatus(201);
        $this->assertSame('DEACTIVATED', $this->observer()->table('users')->where('id', $sari)->value('status'));
        $this->postJson('/commands/user.deactivate', [...$deactivation, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('code', 'INTENT_ABANDONED');
    }

    public function test_an_earlier_epoch_checkout_receipt_keeps_the_close_refused_until_it_is_answered_or_settled(): void
    {
        // AC-037-05, SESSION05: Rina's two checkouts that reached the server before the backup point and
        // were never marked — one committed, one never — in her OPEN session.
        $product = TokoContoh::product('RS-01', ['categoryId' => TokoContoh::category()]);
        TokoContoh::stock($product, '10', '1000');
        $session = TokoContoh::openSession('Rina', TokoContoh::register());
        $sale = TokoContoh::sell('Rina', $session, [['productId' => $product, 'quantity' => '1']]);
        $committed = $this->readText($this->observer()->table('command_identities as i')->join('sales as s', 's.command_id', '=', 'i.id')->where('s.id', $sale)->value('i.source_intent_id'));
        $never = (string) Str::uuid();
        $receipts = app(RequestReceipts::class);
        $now = app(Clock::class)->now()->format('Y-m-d H:i:s.uP');
        $receipts->record($this->people['Rina']['id'], CheckoutSale::OPERATION, $committed, (string) Str::uuid(), $this->epoch, $now);
        $receipts->record($this->people['Rina']['id'], CheckoutSale::OPERATION, $never, (string) Str::uuid(), $this->epoch, $now);
        $dump = TestRestore::dump($this->area);
        $old = $this->epoch;
        $this->restoreAndFence($dump);

        $this->assertCloseRefused($session);

        // The status check of the committed one finds its identity in the restored data.
        $this->browse($this->signInBrowser($this->people['Rina']['email'], $this->people['Rina']['password']));
        $this->check(['operation' => CheckoutSale::OPERATION, 'sourceIntentId' => $committed, 'recoveryEpoch' => $old])->assertOk()->assertJsonPath('outcome', 'COMMITTED');
        $this->assertCloseRefused($session);

        // The other is reported, then held, and keeps the close refused until OP-05 settles it.
        $other = ['operation' => CheckoutSale::OPERATION, 'sourceIntentId' => $never, 'recoveryEpoch' => $old];
        $this->check($other)->assertStatus(409)->assertJsonPath('code', 'EPOCH_STALE');
        $this->assertSame(['HELD'], $this->outcomesOf($other));
        $this->assertCloseRefused($session);

        $this->assertSame(0, $this->reconcile($this->reportOf($other)), Artisan::output());
        $this->assertSame(['NOT_COMMITTED'], $this->outcomesOf($other));
        TokoContoh::closeSession($session);
        $this->assertSame('CLOSED', $this->observer()->table('cash_sessions')->where('id', $session)->value('status'));
    }

    public function test_a_failure_after_every_write_step_of_op05_leaves_nothing(): void
    {
        // TS-21: a request — the fence and the reconciliation, the settlement, the audit event; a hold —
        // the release with the state, the reconciliation, the audit event.
        $this->as('Agus');
        $dump = TestRestore::dump($this->area);
        $lost = $this->envelope();
        $this->probe($lost)->assertStatus(201);
        TokoContoh::startOnboarding();
        $this->restoreAndFence($dump);
        $this->browse($this->signInBrowser($this->people['Agus']['email'], $this->people['Agus']['password']));
        $this->probe($lost)->assertStatus(409);
        $report = $this->reportOf($lost);
        $hold = $this->observer()->table('onboarding_restore_holds')->value('id');
        $this->assertIsInt($hold);

        $state = fn (): array => [$this->snapshot('command_identities'), $this->snapshot('intent_reconciliations', 'report_id'), $this->outcomesOf($lost), $this->snapshot('onboarding_state'), $this->snapshot('onboarding_restore_reconciliations', 'hold_id'), $this->snapshot('onboarding_releases'), $this->observer()->table('audit_events')->where('action', 'RESTORE_RECONCILIATION')->count()];
        $before = $state();

        foreach ([[3, ['--report' => (string) $report]], [3, ['--hold' => (string) $hold, '--outcome' => 'RELEASED_BEFORE_RESTORE']]] as [$steps, $options]) {
            for ($step = 1; $step <= $steps; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));

                try {
                    $this->reconcileWith($options);
                    $this->fail('The injected failure did not happen at write step '.$step);
                } catch (InjectedFailure) {
                    $this->assertSame($before, $state(), 'after write step '.$step);
                }
            }
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(0, $this->reconcileWith(['--report' => (string) $report]), Artisan::output());
        $this->assertSame(0, $this->reconcileWith(['--hold' => (string) $hold, '--outcome' => 'RELEASED_BEFORE_RESTORE']), Artisan::output());
        $this->assertSame('RELEASED', $this->observer()->table('onboarding_state')->value('state'));
    }

    private function assertCloseRefused(int $session): void
    {
        $this->assertRefusedWith(ErrorCode::RecountRequired, static fn (): int => TokoContoh::closeSession($session));
        $this->assertSame('OPEN', $this->observer()->table('cash_sessions')->where('id', $session)->value('status'));
    }

    private function assertRefusedWith(ErrorCode $code, Closure $command): void
    {
        try {
            $command();
            $this->fail('The command was not refused; expected '.$code->value.' (G-27).');
        } catch (CommandRefused $refusal) {
            $this->assertSame($code, $refusal->error);
        }
    }

    /**
     * The restore of the dump over the test database (TS-24), then OP-04: the epoch of the new world.
     */
    private function restoreAndFence(string $dump): void
    {
        TestRestore::restore($dump);
        $this->assertSame(0, Artisan::call('restore:after', ['--environment' => 'test', '--note' => 'Pemulihan insiden S1']), Artisan::output());
        $this->epoch = TokoContoh::recoveryEpoch();
    }

    /**
     * The stale-intent report of an intent: its key.
     *
     * @param  array<string, mixed>  $envelope
     */
    private function reportOf(array $envelope): int
    {
        $report = $this->observer()->table('stale_intent_reports')->where('operation', $envelope['operation'])->where('source_intent_id', $envelope['sourceIntentId'])->value('id');
        $this->assertIsInt($report, 'The intent has its stale-intent report.');

        return $report;
    }

    private function reconcile(int $report): int
    {
        return $this->reconcileWith(['--report' => (string) $report]);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function reconcileWith(array $options): int
    {
        return Artisan::call('restore:reconcile', ['--environment' => 'test', '--note' => self::NOTE, ...$options]);
    }

    private function confirm(): void
    {
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    private function versionOf(int $user): int
    {
        $version = $this->observer()->table('users')->where('id', $user)->value('version');
        $this->assertIsInt($version);

        return $version;
    }
}
