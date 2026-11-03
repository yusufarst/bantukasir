<?php

namespace Tests\Feature\Costing;

use App\Actions\Commands\CommandContext;
use App\Actions\Costing\CorrectCost;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-15 `cost_correction.confirm` through its route, and the replay it runs (AC-025-01 to AC-025-04;
 * INTERACTION_CONTRACTS RC-15 and section 1.5; TECHNICAL_RULES sections 7.2 and 9, TR-TIME-01;
 * ADR-008 sections 4.1, 4.3 and 5 and its test list; SCHEMA section 5.8; D-034, D-060, D-063;
 * BR-COST-09 to BR-COST-27; G-07, G-11, G-15, G-21; CAP-19; EXECUTION_CONTEXT section 10, a command;
 * suites FIN02, FIN03, INV03, CMD01, AUTH03; TASK-025 sections 6 and 7, L2 and L4): the correction of
 * the L-15 example replays every issue after it, rewriting the projections and the valuation and
 * never a fact, as postings would have computed them with the corrected cost from the start; an
 * UNKNOWN cost completed later completes what depended on it, and a known pool after zero is not
 * reached by it; G-15's number and old value; the refusals that write no mark; the movement cap and
 * the time budget, which refuse REPLAY_REJECTED with one mark per refused intent, also when the
 * budget ended the session or a statement met its timeout, and SERVICE_BUSY when the mark cannot be
 * written; a later correction of the same source clearing the marks, another source's not; the same
 * correction twice giving the same result; the failure after every write step; the measured time of
 * a longer replay; the roles, recent authentication and the fields.
 */
final class CorrectCostTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/cost_correction.confirm';

    /** The tables that a refused or failed correction leaves as they were, beside the rows compared. */
    private const array FACTS = ['cost_corrections', 'cost_incomplete_marks', 'cost_incomplete_mark_clearances', 'acquisition_cost_evidence', 'issue_cost_allocations', 'stock_movements', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    /** The product of the L-15 history (history()). */
    private int $pen = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => $this->category, 'name' => 'Pulpen Hitam']);
    }

    public function test_a_correction_replays_the_product_and_publishes_its_projections_and_valuation_never_a_fact(): void
    {
        // AC-025-02, AC-025-03; BR-COST-20 to BR-COST-25; ADR-008 section 4.3; COSTING section 4: 10
        // pens at Rp100.000, 10 at Rp140.000, 3 out by a manual issue (Rp36.000), 2 by an adjustment
        // (Rp24.000), 15 left worth Rp180.000. The second receipt is corrected to Rp160.000: the issues
        // become Rp39.000 and Rp26.000, the 15 left worth Rp195.000, in one publication.
        $evidence = $this->history($this->pen);
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        $envelope = $this->envelopeOf($evidence, '160000', 0);

        $response = $this->correct($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $correction = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['cost_correction', '/products/'.$this->pen.'/cost-history'], [$response->json('result.type'), $response->json('result.location')]);

        // The correction: the effective value before it, the new one, the reason, the Owner (G-15).
        $row = (array) $this->watcher()->table('cost_corrections')->where('id', $correction)->sole();
        $this->assertSame([$evidence, $this->pen, 1, 'KNOWN', '140000.000000', '160000.000000', null, 'Nota pemasok', $hendra, 'Hendra', $commandId], [$row['evidence_id'], $row['product_id'], $row['correction_no'], $row['old_status'], $row['old_amount'], $row['new_amount'], $row['zero_cost_note'], $row['reason'], $row['actor_user_id'], $row['actor_display_name'], $row['command_id']]);

        // The facts stay as committed; the projections and the valuation follow the latest evidence.
        $this->assertSame([
            ['MANUAL_ISSUE', 'KNOWN', '36000.000000', 'KNOWN', '39000.000000', 5],
            ['ADJUSTMENT', 'KNOWN', '24000.000000', 'KNOWN', '26000.000000', 5],
        ], $this->allocationsOf($this->pen));
        $this->assertSame('KNOWN|195000.000000|5|4', $this->valuationOf($this->pen));
        $this->assertSame(['KNOWN', '140000.000000'], array_values((array) $this->watcher()->table('acquisition_cost_evidence')->where('id', $evidence)->first(['cost_status', 'total_amount'])));

        // As postings would have computed it with Rp160.000 from the start (ADR-008 section 4.3).
        $twin = TokoContoh::product('PEN-02', ['categoryId' => $this->category, 'name' => 'Pulpen Kembar']);
        $this->history($twin, '160000');
        $this->assertSame([
            ['MANUAL_ISSUE', 'KNOWN', '39000.000000', 'KNOWN', '39000.000000', 3],
            ['ADJUSTMENT', 'KNOWN', '26000.000000', 'KNOWN', '26000.000000', 4],
        ], $this->allocationsOf($twin));
        $this->assertSame('KNOWN|195000.000000|4|4', $this->valuationOf($twin));

        // One audit event; the values and the reason are the Owner's part (BR-AUD-02).
        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
        $this->assertSame(['BUSINESS', 'COST_CORRECTION', $hendra, 'cost_correction', $correction, $this->pen, null], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id'], $event['reason']]);
        $this->assertSame(['correctionNo' => 1, 'evidenceId' => $evidence, 'marksCleared' => 0, 'movementId' => $this->movementOf($this->pen, 2), 'projectionsRewritten' => 2, 'publicationNo' => 5], self::dataOf($event['change_data']));
        $this->assertSame(['new' => ['amount' => '160000', 'status' => 'KNOWN'], 'old' => ['amount' => '140000', 'status' => 'KNOWN'], 'reason' => 'Nota pemasok', 'zeroNote' => null], self::dataOf($event['owner_only_data']));
        $this->assertStringNotContainsString('Nota pemasok', self::text($event['change_data']));

        // The result holds references only; a repeat answers it and writes nothing; a status check recovers it.
        $stored = (array) $this->watcher()->table('command_results')->where('command_id', $commandId)->sole();
        $this->assertSame(['cost_correction', $correction], [$stored['result_type'], $stored['result_ref']]);
        $facts = $this->factsAndRows();
        $this->correct($envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $correction);
        $this->assertSame($facts, $this->factsAndRows());
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/products/'.$this->pen.'/cost-history');
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');
    }

    public function test_the_same_correction_again_gives_the_same_result_and_rewrites_no_projection(): void
    {
        // AC-025-03: deterministic; only projections whose status or amount changed are rewritten,
        // while the valuation is published again (ADR-008 section 4.3).
        $evidence = $this->history($this->pen);
        TokoContoh::correctCost($evidence, '160000');
        $first = $this->allocationsOf($this->pen);

        TokoContoh::correctCost($evidence, '160000', 'Nota yang sama');

        $this->assertSame($first, $this->allocationsOf($this->pen));
        $this->assertSame('KNOWN|195000.000000|6|4', $this->valuationOf($this->pen));
        $this->assertSame([[1, '140000.000000', '160000.000000'], [2, '160000.000000', '160000.000000']], $this->watcher()->table('cost_corrections')->where('evidence_id', $evidence)->orderBy('correction_no')
            ->get(['correction_no', 'old_amount', 'new_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_an_unknown_cost_completed_later_completes_what_depended_on_it_and_not_a_known_pool_after_zero(): void
    {
        // AC-025-01; BR-COST-13 to BR-COST-15, BR-COST-22; ADR-008 section 4.1: 4 at UNKNOWN, all out
        // (UNKNOWN; the pool starts again at a known zero), 10 at Rp50.000, 4 out (Rp20.000), 2 at
        // UNKNOWN, 1 out (UNKNOWN). Completing the first to Rp8.000 completes the first issue only;
        // completing the second to Rp9.000 completes the last: 8 worth Rp39.000, 1 takes Rp4.875.
        $book = TokoContoh::product('BUKU-01', ['categoryId' => $this->category, 'name' => 'Buku Tulis']);
        TokoContoh::receive([['productId' => $book, 'quantity' => '4', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::issue([['productId' => $book, 'quantity' => '4']]);
        TokoContoh::stock($book, '10', '50000');
        TokoContoh::issue([['productId' => $book, 'quantity' => '4']]);
        TokoContoh::receive([['productId' => $book, 'quantity' => '2', 'cost' => ['status' => 'UNKNOWN']]]);
        TokoContoh::issue([['productId' => $book, 'quantity' => '1']]);
        $this->assertSame('UNKNOWN||6|6', $this->valuationOf($book));

        $first = TokoContoh::correctCost(TokoContoh::evidenceOf($this->movementOf($book, 1)), '8000', 'Nota ditemukan');

        $this->assertSame([
            ['MANUAL_ISSUE', 'UNKNOWN', null, 'KNOWN', '8000.000000', 7],
            ['MANUAL_ISSUE', 'KNOWN', '20000.000000', 'KNOWN', '20000.000000', 4],
            ['MANUAL_ISSUE', 'UNKNOWN', null, 'UNKNOWN', null, 6],
        ], $this->allocationsOf($book));
        $this->assertSame('UNKNOWN||7|6', $this->valuationOf($book));
        $this->assertSame(['UNKNOWN', null, '8000.000000'], array_values((array) $this->watcher()->table('cost_corrections')->where('id', $first)->first(['old_status', 'old_amount', 'new_amount'])));

        TokoContoh::correctCost(TokoContoh::evidenceOf($this->movementOf($book, 5)), '9000', 'Nota ditemukan');

        $this->assertSame([
            ['MANUAL_ISSUE', 'UNKNOWN', null, 'KNOWN', '8000.000000', 7],
            ['MANUAL_ISSUE', 'KNOWN', '20000.000000', 'KNOWN', '20000.000000', 4],
            ['MANUAL_ISSUE', 'UNKNOWN', null, 'KNOWN', '4875.000000', 8],
        ], $this->allocationsOf($book));
        $this->assertSame('KNOWN|34125.000000|8|6', $this->valuationOf($book));
    }

    public function test_g_15_the_correction_reviewed_is_the_latest_and_its_old_value_the_effective_one(): void
    {
        // G-15, STALE_VERSION: a correction of the first receipt, then another with the number it
        // reviewed already passed, refused with nothing written and no mark (D-063); then the next.
        $evidence = TokoContoh::evidenceOf($this->movementOf($this->pen, 1, TokoContoh::stock($this->pen, '5', '5000')));
        $this->owner();

        $this->correct($this->envelopeOf($evidence, '5500', 0))->assertStatus(201);
        $before = $this->factsAndRows();
        $this->correct($this->envelopeOf($evidence, '6000', 0))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($before, $this->factsAndRows());

        $this->correct($this->envelopeOf($evidence, '6000', 1))->assertStatus(201);
        $this->assertSame([[1, '5000.000000', '5500.000000'], [2, '5500.000000', '6000.000000']], $this->watcher()->table('cost_corrections')->where('evidence_id', $evidence)->orderBy('correction_no')
            ->get(['correction_no', 'old_amount', 'new_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame('KNOWN|6000.000000|3|1', $this->valuationOf($this->pen));
    }

    public function test_a_blocked_product_a_missing_source_and_an_explicit_zero(): void
    {
        // G-11, D-063: a blocked product takes no correction and writes no mark; a cost source that
        // does not exist is NOT_FOUND; BR-COST-04: the Owner's explicit zero carries its evidence.
        $evidence = $this->history($this->pen);
        $this->owner();
        $block = TokoContoh::postingBlock($this->pen);
        $before = $this->factsAndRows();

        $this->correct($this->envelopeOf($evidence, '160000', 0))->assertStatus(422)->assertJsonPath('code', 'POSTING_BLOCKED');
        $this->correct($this->envelopeOf(999999, '1', 0))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        // RC-15's order under the guard: a stale number is STALE_VERSION before the block (review finding R-11).
        $this->correct($this->envelopeOf($evidence, '160000', 1))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertSame($before, $this->factsAndRows());

        TokoContoh::reopenPostings($block);
        $zero = $this->envelope(['evidenceId' => $evidence, 'newCost' => ['amount' => '0', 'zeroEvidence' => 'Hadiah pemasok'], 'reason' => 'Barang hadiah'], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => 0]]);
        $this->correct($zero)->assertStatus(201);
        $this->assertSame(['0.000000', 'Hadiah pemasok'], array_values((array) $this->watcher()->table('cost_corrections')->where('evidence_id', $evidence)->first(['new_amount', 'zero_cost_note'])));
        // 10 at Rp100.000 and 10 at zero: 3 take Rp15.000 of Rp100.000, 2 then Rp10.000 of Rp85.000.
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '36000.000000', 'KNOWN', '15000.000000', 5], ['ADJUSTMENT', 'KNOWN', '24000.000000', 'KNOWN', '10000.000000', 5]], $this->allocationsOf($this->pen));
    }

    public function test_the_movement_cap_refuses_with_one_mark_per_intent_cleared_only_by_a_later_correction_of_the_same_source(): void
    {
        // AC-025-04; TR-TIME-01; BR-COST-27, BR-COST-28; SCHEMA section 5.8; G-21. Three movements
        // from the second receipt to the end, past a cap of two.
        $evidence = $this->history($this->pen);
        $first = TokoContoh::evidenceOf($this->movementOf($this->pen, 1));
        $this->owner();
        $hendra = $this->people['Hendra']['id'];
        config(['costing.replay.movement_cap' => 2]);
        $rows = $this->rows();
        $envelope = $this->envelopeOf($evidence, '160000', 0);

        $refused = $this->correct($envelope)->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED')->assertJsonPath('outcome', 'REJECTED')->assertJsonPath('messageKey', 'ux.state.replay_rejected.cost');
        $this->assertJsonChannel($refused, 'commands.refused', 'owner');

        // Nothing of the correction; its mark and the mark's audit event, in their own transaction.
        $this->assertSame($rows, $this->rows());
        $this->assertSame(0, $this->watcher()->table('cost_corrections')->count());
        $mark = (array) $this->watcher()->table('cost_incomplete_marks')->sole();
        $this->assertSame([$evidence, $this->pen, $this->movementOf($this->pen, 2), CorrectCost::OPERATION, $envelope['sourceIntentId'], 'MOVEMENT_CAP', 0, $hendra, 'Hendra'], [$mark['evidence_id'], $mark['product_id'], $mark['evidence_movement_id'], $mark['rejected_operation'], $mark['rejected_source_intent_id'], $mark['rejection_code'], $mark['corrections_seen'], $mark['actor_user_id'], $mark['actor_display_name']]);
        $event = (array) $this->watcher()->table('audit_events')->where('action', 'COST_INCOMPLETE_MARK')->sole();
        $this->assertSame(['cost_incomplete_mark', $mark['id'], $this->pen, null], [$event['subject_type'], $event['subject_id'], $event['product_id'], $event['command_id']]);
        $this->assertSame(['cleared' => false, 'correctionsSeen' => 0, 'evidenceId' => $evidence, 'movementId' => $this->movementOf($this->pen, 2), 'rejection' => 'MOVEMENT_CAP'], self::dataOf($event['change_data']));
        $this->assertSame(['attempted' => ['amount' => '160000', 'status' => 'KNOWN'], 'reason' => 'Nota pemasok', 'zeroNote' => null], self::dataOf($event['owner_only_data']));
        $this->assertSame('REJECTED', $this->watcher()->table('command_request_receipts')->where('source_intent_id', $envelope['sourceIntentId'])->value('outcome'));

        // A repeat of the same intent is refused again and writes no second mark; another intent writes its own.
        $this->correct([...$envelope, 'idempotencyKey' => (string) Str::uuid()])->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->assertSame([1, 1], [$this->watcher()->table('cost_incomplete_marks')->count(), $this->watcher()->table('audit_events')->where('action', 'COST_INCOMPLETE_MARK')->count()]);
        $this->correct($this->envelopeOf($evidence, '150000', 0))->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->assertSame(2, $this->watcher()->table('cost_incomplete_marks')->count());

        // At the cap the replay runs; a correction of another source clears nothing (G-21).
        config(['costing.replay.movement_cap' => 4]);
        $this->correct($this->envelopeOf($first, '110000', 0))->assertStatus(201);
        $this->assertSame(0, $this->watcher()->table('cost_incomplete_mark_clearances')->count());

        // A later correction of the same source clears every mark of it, in its own transaction.
        $cleared = $this->correct($this->envelopeOf($evidence, '160000', 0))->assertStatus(201);
        $correction = self::number($cleared->json('result.id'));
        $commandId = self::number($cleared->json('commandId'));
        $this->assertSame([
            ['LATER_CORRECTION', $correction, null, 'USER', $hendra, null, 'Hendra', $commandId],
            ['LATER_CORRECTION', $correction, null, 'USER', $hendra, null, 'Hendra', $commandId],
        ], $this->watcher()->table('cost_incomplete_mark_clearances')->orderBy('mark_id')->get(['clearing_kind', 'clearing_correction_id', 'clearing_reversal_id', 'actor_kind', 'actor_user_id', 'actor_process', 'actor_display_name', 'command_id'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(2, self::dataOf($this->watcher()->table('audit_events')->where('command_id', $commandId)->value('change_data'))['marksCleared'] ?? null);
    }

    public function test_the_time_budget_refuses_with_its_mark_also_when_it_ended_the_session(): void
    {
        // TR-TIME-01, ADR-008's test list: the replay finds its budget spent — the clock moves 26
        // seconds at its locks — and is refused REPLAY_REJECTED with a mark TIME_BUDGET.
        $evidence = $this->history($this->pen);
        $this->owner();
        $rows = $this->rows();
        $this->app->instance(CommandPoints::class, self::atStepSeven(function (): void {
            $this->travel(26)->seconds();
        }));

        $this->correct($this->envelopeOf($evidence, '160000', 0))->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->assertSame($rows, $this->rows());
        $this->assertSame(['TIME_BUDGET'], $this->watcher()->table('cost_incomplete_marks')->pluck('rejection_code')->all());

        // PostgreSQL ends the session at the budget, here one second, during the replay: nothing of
        // it committed, and the mark is written on a new connection.
        $this->app->instance(CommandPoints::class, self::atStepSeven(static function (): void {
            DB::select('select pg_catalog.pg_sleep(1.5)');
        }));
        $envelope = new Envelope(CorrectCost::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['correctionNo' => 0], ['evidenceId' => $evidence, 'newCost' => ['amount' => '170000'], 'reason' => 'Nota pemasok']);
        DB::statement("set transaction_timeout = '1s'");

        try {
            app(CorrectCost::class)->execute(new CommandContext($this->people['Hendra']['id'], $envelope, app(Clock::class)->now()));
            $this->fail('The budget ends the correction.');
        } catch (CommandRefused $refusal) {
            $this->assertSame('REPLAY_REJECTED', $refusal->error->value);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame($rows, $this->rows());
        $this->assertSame(['TIME_BUDGET', 'TIME_BUDGET'], $this->watcher()->table('cost_incomplete_marks')->orderBy('id')->pluck('rejection_code')->all());
        $this->assertSame($envelope->sourceIntentId, $this->watcher()->table('cost_incomplete_marks')->orderByDesc('id')->value('rejected_source_intent_id'));
        $this->assertSame(0, $this->watcher()->table('cost_corrections')->count());
    }

    public function test_a_statement_timeout_of_the_replay_refuses_with_its_mark_time_budget(): void
    {
        // SCHEMA section 5.8, "a timeout of the replay is TIME_BUDGET"; TR-TIME-01: a statement after
        // the locks of step 7 meets the statement timeout, here 200 ms; nothing of the correction
        // commits, its mark is written, and REPLAY_REJECTED is answered (review finding R-04).
        $evidence = $this->history($this->pen);
        $rows = $this->rows();
        $this->app->instance(CommandPoints::class, self::atStepSeven(static function (): void {
            DB::select('select pg_catalog.pg_sleep(0.5)');
        }));
        $envelope = new Envelope(CorrectCost::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['correctionNo' => 0], ['evidenceId' => $evidence, 'newCost' => ['amount' => '170000'], 'reason' => 'Nota pemasok']);
        DB::statement("set statement_timeout = '200ms'");

        try {
            app(CorrectCost::class)->execute(new CommandContext($this->people['Hendra']['id'], $envelope, app(Clock::class)->now()));
            $this->fail('The statement timeout ends the correction.');
        } catch (CommandRefused $refusal) {
            $this->assertSame('REPLAY_REJECTED', $refusal->error->value);
        } finally {
            DB::statement('reset statement_timeout');
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }

        $this->assertSame($rows, $this->rows());
        $this->assertSame(0, $this->watcher()->table('cost_corrections')->count());
        $mark = (array) $this->watcher()->table('cost_incomplete_marks')->sole();
        $this->assertSame(['TIME_BUDGET', $envelope->sourceIntentId, 0], [$mark['rejection_code'], $mark['rejected_source_intent_id'], $mark['corrections_seen']]);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $envelope->sourceIntentId)->count());
    }

    public function test_a_mark_that_cannot_be_written_answers_service_busy_and_a_repeat_writes_it_once(): void
    {
        // SCHEMA section 5.8, TR-TIME-01: a refused correction is never reported without its mark.
        $evidence = $this->history($this->pen);
        $this->owner();
        config(['costing.replay.movement_cap' => 2]);
        $envelope = $this->envelopeOf($evidence, '160000', 0);
        $this->app->instance(CommandPoints::class, new FailurePoints(null, 'mark'));

        $this->correct($envelope)->assertStatus(503)->assertJsonPath('code', 'SERVICE_BUSY');
        $this->assertSame(0, $this->watcher()->table('cost_incomplete_marks')->count());
        $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('source_intent_id', $envelope['sourceIntentId'])->value('outcome'));

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->correct($envelope)->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->correct($envelope)->assertStatus(422)->assertJsonPath('code', 'REPLAY_REJECTED');
        $this->assertSame(1, $this->watcher()->table('cost_incomplete_marks')->count());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_projection_or_valuation_changed(): void
    {
        // AC-025-03, TS-21: six steps — the correction, the valuation, the projections, the
        // clearances, the audit event, the result; a failure during the publication changes nothing.
        $evidence = $this->history($this->pen);
        $this->owner();

        for ($step = 1; $step <= 6; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->factsAndRows();

            $this->correct($this->envelopeOf($evidence, '160000', 0))->assertStatus(500);

            $this->assertSame($before, $this->factsAndRows(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', CorrectCost::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        $points = new FailurePoints(7);
        $this->app->instance(CommandPoints::class, $points);
        $this->correct($this->envelopeOf($evidence, '160000', 0))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:6', 'after_lock:7', ...array_map(static fn (int $step): string => 'after_write:'.$step, range(1, 6))], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame('KNOWN|195000.000000|5|4', $this->valuationOf($this->pen));
    }

    public function test_the_time_of_a_correction_over_a_longer_history_is_measured_within_its_budget(): void
    {
        // TASK-025 section 8, BR-COST-28: the measured time of a correction whose replay covers 200
        // movements of one product — 100 receipts and 100 manual issues — from the lock of its product
        // to its commit, on the test database; written for the evidence on the test's error stream.
        // Not a PERF01 pass. The first receipt, 10 at Rp100.000, becomes Rp110.000: each issue of one
        // then takes Rp11.000, where it took less, so every projection is rewritten.
        TokoContoh::stock($this->pen, '10', '100000');

        for ($pair = 1; $pair < 100; $pair++) {
            TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]);
            TokoContoh::stock($this->pen, '1', '11000');
        }

        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]);
        $evidence = TokoContoh::evidenceOf($this->movementOf($this->pen, 1));

        $started = microtime(true);
        $correction = TokoContoh::correctCost($evidence, '110000');
        $elapsed = (int) round((microtime(true) - $started) * 1000);

        $event = self::dataOf($this->watcher()->table('audit_events')->where('subject_type', 'cost_correction')->where('subject_id', $correction)->value('change_data'));
        fwrite(STDERR, sprintf("TASK-025 replay measurement: a correction replaying %d movements took %d ms\n", self::number($this->watcher()->table('stock_movements')->where('product_id', $this->pen)->max('product_sequence')), $elapsed));
        $this->assertSame([200, 100], [self::number($this->watcher()->table('stock_movements')->where('product_id', $this->pen)->count()), $event['projectionsRewritten'] ?? null]);
        $this->assertSame('KNOWN|99000.000000|201|200', $this->valuationOf($this->pen));
        $this->assertLessThan(25_000, $elapsed, 'Within the heavy budget less its reserve (TR-TIME-01).');
    }

    public function test_only_the_owner_with_recent_authentication_corrects_a_cost(): void
    {
        // AC-025-02; CAP-19; THR-19: staff and the combined account are refused at the gate, with no
        // receipt, and by the guard in the transaction (SEC-AZ-04); AUTH03: without a recent password
        // confirmation the Owner's correction answers 423 and runs nothing.
        $evidence = $this->history($this->pen);
        $before = $this->factsAndRows();

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->correct($this->envelopeOf($evidence, '1', 0))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CorrectCost::OPERATION)->where('actor_user_id', $this->people[$person]['id'])->count(), $person);
        }

        foreach (['Agus', 'Dewi'] as $person) {
            $envelope = new Envelope(CorrectCost::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, ['correctionNo' => 0], ['evidenceId' => $evidence, 'newCost' => ['amount' => '1'], 'reason' => 'Coba']);

            try {
                app(CorrectCost::class)->execute(new CommandContext($this->people[$person]['id'], $envelope, app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->as('Hendra');
        $this->correct($this->envelopeOf($evidence, '1', 0))->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', CorrectCost::OPERATION)->count());
        $this->assertSame($before, $this->factsAndRows());

        $this->browse(null);
        $this->correct($this->envelopeOf($evidence, '1', 0))->assertStatus(401);
        $this->assertSame($before, $this->factsAndRows());
    }

    public function test_the_fields_are_validated_with_their_keys_before_any_lock(): void
    {
        // TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES sections 11.1 and 11.3; COPY_DECK section 16.1;
        // BR-COST-03, BR-COST-04: a correction records a known value, never UNKNOWN.
        $evidence = $this->history($this->pen);
        $this->owner();
        $base = ['evidenceId' => $evidence, 'newCost' => ['amount' => '5000'], 'reason' => 'Nota'];
        $before = $this->factsAndRows();

        foreach ([
            [['reason' => null], 'payload.reason', 'ux.validation.required', []],
            [['reason' => str_repeat('r', 1001)], 'payload.reason', 'ux.validation.text_max', ['maks' => 1000]],
            [['newCost' => ['amount' => null]], 'payload.newCost.amount', 'ux.validation.required', []],
            [['newCost' => ['amount' => '1,5']], 'payload.newCost.amount', 'ux.validation.money', []],
            [['newCost' => ['amount' => '1000000000000']], 'payload.newCost.amount', 'ux.validation.money_max', []],
            [['newCost' => ['amount' => '0']], 'payload.newCost.zeroEvidence', 'ux.validation.zero_evidence', []],
            [['newCost' => ['amount' => '0', 'zeroEvidence' => str_repeat('z', 1001)]], 'payload.newCost.zeroEvidence', 'ux.validation.text_max', ['maks' => 1000]],
        ] as $index => [$payload, $path, $key, $params]) {
            $refused = $this->correct($this->envelope([...$base, ...$payload], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => 0]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        // What no page sends: UNKNOWN, an amount with evidence, a number, a control character, another field.
        foreach ([
            ['newCost' => ['status' => 'UNKNOWN']],
            ['newCost' => ['amount' => '5000', 'zeroEvidence' => 'x']],
            ['newCost' => ['amount' => 5000]],
            ['newCost' => ['amount' => '-1']],
            ['newCost' => ['amount' => '1.0000001']],
            ['evidenceId' => (string) $evidence],
            ['reason' => "Nota\nbaru"],
            ['productId' => $this->pen],
        ] as $payload) {
            $this->correct($this->envelope([...$base, ...$payload], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => 0]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // The correction reviewed is a JSON whole number of at least 0.
        foreach ([[], ['correctionNo' => -1], ['correctionNo' => '0'], ['correctionNo' => 0, 'version' => 1]] as $expected) {
            $this->correct($this->envelope($base, ['operation' => CorrectCost::OPERATION, 'expected' => $expected]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->factsAndRows());
    }

    /**
     * The L-15 history of a product (COSTING section 4): 10 at Rp100.000, 10 at the second cost, a
     * manual issue of 3 and an adjustment of −2. Returns the evidence of the second receipt.
     */
    private function history(int $product, string $second = '140000'): int
    {
        TokoContoh::stock($product, '10', '100000');
        TokoContoh::stock($product, '10', $second);
        TokoContoh::issue([['productId' => $product, 'quantity' => '3']]);
        TokoContoh::adjust([['productId' => $product, 'direction' => 'DECREASE', 'quantity' => '2']]);

        return TokoContoh::evidenceOf($this->movementOf($product, 2));
    }

    /** The Owner, with the recent authentication of RC-15 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-15 for this cost source, new total and correction reviewed.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $evidence, string $amount, int $correctionNo): array
    {
        return $this->envelope(['evidenceId' => $evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota pemasok'], ['operation' => CorrectCost::OPERATION, 'expected' => ['correctionNo' => $correctionNo]]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function correct(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * Command points that run this at the locks of step 7, and nothing else.
     *
     * @param  Closure(): void  $atSeven
     */
    private static function atStepSeven(Closure $atSeven): CommandPoints
    {
        return new class($atSeven) implements CommandPoints
        {
            /**
             * @param  Closure(): void  $atSeven
             */
            public function __construct(private readonly Closure $atSeven) {}

            public function afterIdentity(): void {}

            public function afterLock(string $step): void
            {
                if ($step === '7') {
                    ($this->atSeven)();
                }
            }

            public function afterWrite(int $step): void {}

            public function beforeCommit(): void {}

            public function afterOwnWrite(string $write): void {}
        };
    }

    /**
     * The movement of a product at this place of its sequence; with a receiving document, its first line's.
     */
    private function movementOf(int $product, int $sequence, ?int $document = null): int
    {
        $query = $this->watcher()->table('stock_movements as m')->where('m.product_id', $product);

        if ($document !== null) {
            $query->join('receiving_lines as l', 'l.id', '=', 'm.receiving_line_id')->where('l.receiving_document_id', $document);
        } else {
            $query->where('m.product_sequence', $sequence);
        }

        return self::number($query->value('m.id'));
    }

    /**
     * The allocations of a product in their order: the type, the fact's status and amount, the
     * projection's status, amount and publication.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $product): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.product_sequence')
            ->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount', 'p.publication_no'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A product's valuation as "status|value|publication|last sequence".
     */
    private function valuationOf(int $product): string
    {
        $row = (array) $this->watcher()->table('inventory_valuations')->where('product_id', $product)->sole();

        return implode('|', [self::text($row['value_status']), self::text($row['value']), (string) self::number($row['publication_no']), (string) self::number($row['last_sequence'])]);
    }

    /**
     * Data of jsonb with its keys sorted at every level, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);

        return self::sortedKeys($data);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortedKeys(array $data): array
    {
        ksort($data);

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::sortedKeys($value) : $value, $data);
    }

    /** One independent session for every read of the test, so that the test stays within the server's connections. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The rows that a correction changes: the valuations and the projections, in key order.
     *
     * @return array<string, mixed>
     */
    private function rows(): array
    {
        return [
            'valuations' => array_values($this->watcher()->table('inventory_valuations')->orderBy('product_id')->get()->map(static fn (object $row): array => (array) $row)->all()),
            'projections' => array_values($this->watcher()->table('issue_cost_allocation_projections')->orderBy('allocation_id')->get()->map(static fn (object $row): array => (array) $row)->all()),
        ];
    }

    /**
     * The facts, with the rows that a correction changes.
     *
     * @return array<string, mixed>
     */
    private function factsAndRows(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return ['facts' => $counts, ...$this->rows()];
    }
}
