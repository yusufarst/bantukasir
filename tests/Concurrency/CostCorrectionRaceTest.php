<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-15 (AC-025-03, AC-025-04; TECHNICAL_RULES section 8, RACE-01, RACE-02 and
 * RACE-11; ADR-008 section 7; SCHEMA section 5.8; BR-COST-26, BR-COST-27; G-21; D-063; TASK-025
 * section 7, L3; suites FIN03 and CMD01), each with processes of their own connections under
 * barrier control:
 *
 * - RACE-11, the replay first: a posting of the product waits for the product's guard, which the
 *   replay holds to its commit, then takes its allocation from the corrected valuation;
 * - RACE-11, the posting first: the correction waits, then its replay recomputes the posting's
 *   allocation — its projection, never its fact;
 * - the mark against a later correction: a correction refused at the movement cap, whose mark's own
 *   transaction finds that a correction of the same source committed meanwhile, writes its mark
 *   together with its clearance by that correction;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run; a refused correction and its repeat write one mark;
 * - a correction that waits for the product's guard beyond the lock timeout answers SERVICE_BUSY and
 *   writes no mark (D-063).
 */
final class CostCorrectionRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'cost_correction.confirm';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $product = 0;

    /** The evidence of the second receipt. */
    private int $evidence = 0;

    private ?Connection $watcher = null;

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->product = TokoContoh::product('RACE-C1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Biaya']);
        TokoContoh::stock($this->product, '10', '100000');
        TokoContoh::stock($this->product, '10', '140000');
        $movement = $this->watcher()->table('stock_movements')->where('product_id', $this->product)->where('product_sequence', 2)->value('id');
        $this->evidence = TokoContoh::evidenceOf(is_int($movement) ? $movement : 0);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_11_a_posting_waits_for_the_replay_and_takes_its_allocation_from_the_corrected_valuation(): void
    {
        // The correction holds the product's guard from step 6 to its commit; the manual issue of 5
        // waits, then finds 20 worth Rp260.000 published, and takes Rp65.000.
        $correction = $this->start($this->correction('160000'), ['after_lock:7']);
        $issue = $this->start($this->issue('5'));
        Worker::ready($correction, $issue);

        $correction->run()->awaitBarrier('after_lock:7');
        $issue->run()->awaitBlocked($this->watcher());
        $correction->release();

        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($issue->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '65000.000000', 3, 'KNOWN', '65000.000000']], $this->allocations());
        $this->assertSame(['KNOWN', '195000.000000', 4], $this->valuation());
    }

    public function test_race_11_a_correction_waits_for_a_posting_and_replays_it_without_touching_its_fact(): void
    {
        // The manual issue holds the guard first and takes Rp60.000 of 20 worth Rp240.000; the
        // correction waits, then replays it: its projection becomes Rp65.000, its fact stays.
        $issue = $this->start($this->issue('5'), ['after_lock:7']);
        $correction = $this->start($this->correction('160000'));
        Worker::ready($issue, $correction);

        $issue->run()->awaitBarrier('after_lock:7');
        $correction->run()->awaitBlocked($this->watcher());
        $issue->release();

        $this->assertSame('COMMITTED', ($issue->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '60000.000000', 2, 'KNOWN', '65000.000000']], $this->allocations());
        $this->assertSame(['KNOWN', '195000.000000', 4], $this->valuation());
    }

    public function test_a_mark_written_after_a_later_correction_of_the_same_source_comes_with_its_clearance(): void
    {
        // SCHEMA section 5.8, "Race with a later correction": the refused correction stops before its
        // mark's transaction locks the product; a correction of the same source commits meanwhile;
        // the mark then sees more corrections than the refused attempt did, and is cleared by it.
        TokoContoh::issue([['productId' => $this->product, 'quantity' => '1']]);
        $refused = $this->correction('150000', 1);
        $late = $this->start($refused, ['after_lock:2.1']);
        $later = $this->start($this->correction('160000'));
        Worker::ready($late, $later);

        // Its own transaction's actor guard first, then the mark's, after the refusal.
        $late->run()->awaitBarrier('after_lock:2.1');
        $late->release();
        $late->awaitBarrier('after_lock:2.1');
        $later->run();
        $committed = $later->finish()->result ?? [];
        $this->assertSame('COMMITTED', $committed['outcome'] ?? null);
        $late->release();

        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $late->finish()->result);
        $mark = (array) $this->watcher()->table('cost_incomplete_marks')->sole();
        $this->assertSame([$refused['intent'], 'MOVEMENT_CAP', 0], [$mark['rejected_source_intent_id'], $mark['rejection_code'], $mark['corrections_seen']]);
        $correction = $this->watcher()->table('cost_corrections')->where('command_id', $committed['commandId'] ?? null)->value('id');
        $this->assertSame([['LATER_CORRECTION', $correction, 'USER', $this->owner, null]], $this->watcher()->table('cost_incomplete_mark_clearances')->where('mark_id', $mark['id'])
            ->get(['clearing_kind', 'clearing_correction_id', 'actor_kind', 'actor_user_id', 'command_id'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_race_01_and_race_02_a_correction_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // EXECUTION_CONTEXT section 10, a command; TECHNICAL_RULES section 8: the second request waits
        // on the first's identity, then answers its result; another payload under either is held,
        // never run (review finding R-01).
        $seen = 0;

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->correction('150000', null, $seen, $intent, $key), ['after_identity']);
            $repeat = $this->start($this->correction('150000', null, $seen, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('cost_corrections')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
            $seen++;
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->correction('150000', null, $seen, $intent, $key), ['after_identity']);
            $other = $this->start($this->correction('170000', null, $seen, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
            $seen++;
        }

        // Four corrections, numbered 1 to 4, each the one reviewed; the other payload never ran.
        $this->assertSame([1, 2, 3, 4], array_values($this->watcher()->table('cost_corrections')->where('evidence_id', $this->evidence)->orderBy('correction_no')->pluck('correction_no')->all()));
        $this->assertSame(0, $this->watcher()->table('cost_corrections')->where('new_amount', '170000')->count());
    }

    public function test_race_01_a_refused_correction_and_its_repeat_write_one_mark(): void
    {
        // AC-025-04, SCHEMA section 5.8: the repeat waits on the refused attempt's identity, which the
        // refusal rolls back; it runs the replay itself and is refused too, and the intent's mark is
        // written once, whichever mark's transaction takes the product's guard first (review finding R-01).
        TokoContoh::issue([['productId' => $this->product, 'quantity' => '1']]);
        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $original = $this->start($this->correction('150000', 1, 0, $intent, $key), ['after_identity']);
        $repeat = $this->start($this->correction('150000', 1, 0, $intent, $key));
        Worker::ready($original, $repeat);

        $original->run()->awaitBarrier('after_identity');
        $repeat->run()->awaitBlocked($this->watcher());
        $original->release();

        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $original->finish()->result);
        $this->assertSame(['refused' => 'REPLAY_REJECTED'], $repeat->finish()->result);
        $this->assertSame([[$intent, 'MOVEMENT_CAP']], $this->watcher()->table('cost_incomplete_marks')->get(['rejected_source_intent_id', 'rejection_code'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(1, $this->watcher()->table('audit_events')->where('action', 'COST_INCOMPLETE_MARK')->count());
        $this->assertSame([0, 0], [$this->watcher()->table('cost_corrections')->count(), $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count()]);
    }

    public function test_a_correction_that_waits_beyond_the_lock_timeout_answers_service_busy_and_writes_no_mark(): void
    {
        // AC-025-04, D-063, TR-TIME-01: a manual issue holds the product's guard; the correction cannot
        // take it within the lock timeout, so its replay never started: SERVICE_BUSY, its identity
        // rolled back, and no correction, mark, clearance or mark's audit event (review finding R-02).
        $issue = $this->start($this->issue('5'), ['after_lock:7']);
        $waiting = $this->correction('160000');
        $correction = $this->start($waiting);
        Worker::ready($issue, $correction);

        $issue->run()->awaitBarrier('after_lock:7');
        $correction->run();

        $this->assertSame(['refused' => 'SERVICE_BUSY'], $correction->finish()->result);
        $issue->release();
        $this->assertSame('COMMITTED', ($issue->finish()->result ?? [])['outcome'] ?? null);

        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $waiting['intent'])->count());

        foreach (['cost_corrections', 'cost_incomplete_marks', 'cost_incomplete_mark_clearances'] as $table) {
            $this->assertSame(0, $this->watcher()->table($table)->count(), $table);
        }

        $this->assertSame(0, $this->watcher()->table('audit_events')->where('action', 'COST_INCOMPLETE_MARK')->count());
        // The issue took its allocation from the valuation as it was: 5 of 20 worth Rp240.000.
        $this->assertSame([['KNOWN', '60000.000000', 2, 'KNOWN', '60000.000000']], $this->allocations());
    }

    /**
     * The product's allocations: the fact's status, amount and publication, the projection's status and amount.
     *
     * @return list<list<mixed>>
     */
    private function allocations(): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $this->product)->orderBy('a.id')
            ->get(['a.at_commit_status', 'a.at_commit_amount', 'a.based_on_publication_no', 'p.current_status', 'p.current_amount'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * The product's valuation: status, value and publication.
     *
     * @return list<mixed>
     */
    private function valuation(): array
    {
        return array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value', 'publication_no']));
    }

    /**
     * RC-15 of the second receipt's evidence by the Owner, reviewing the correction of this number (0
     * for none), under a new intent and key unless given; with a movement cap for the worker when given.
     *
     * @return array<string, mixed>
     */
    private function correction(string $amount, ?int $cap = null, int $seen = 0, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => self::OPERATION,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['correctionNo' => $seen],
            'payload' => ['evidenceId' => $this->evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota pemasok'],
            ...($cap === null ? [] : ['replayCap' => $cap]),
        ];
    }

    /**
     * RC-08 of the product, by the Owner.
     *
     * @return array<string, mixed>
     */
    private function issue(string $quantity): array
    {
        $version = $this->watcher()->table('products')->where('id', $this->product)->value('version');

        return [
            'operation' => 'manual_issue.confirm',
            'actor' => $this->owner,
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['products' => [['productId' => $this->product, 'version' => is_int($version) ? $version : 0]]],
            'payload' => ['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => [['productId' => $this->product, 'quantity' => $quantity]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(array $arguments, array $barriers = []): Worker
    {
        $worker = Worker::start(UserCommandScenario::class, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
