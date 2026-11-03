<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-15 (AC-025-03, AC-025-04; TECHNICAL_RULES section 8, RACE-11; ADR-008 section 7;
 * SCHEMA section 5.8; BR-COST-26, BR-COST-27; G-21; TASK-025 section 7, L3; suite FIN03), each with
 * processes of their own connections under barrier control:
 *
 * - RACE-11, the replay first: a posting of the product waits for the product's guard, which the
 *   replay holds to its commit, then takes its allocation from the corrected valuation;
 * - RACE-11, the posting first: the correction waits, then its replay recomputes the posting's
 *   allocation — its projection, never its fact;
 * - the mark against a later correction: a correction refused at the movement cap, whose mark's own
 *   transaction finds that a correction of the same source committed meanwhile, writes its mark
 *   together with its clearance by that correction.
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
     * RC-15 of the second receipt's evidence, as the first correction, by the Owner; with a movement
     * cap for the worker when given.
     *
     * @return array<string, mixed>
     */
    private function correction(string $amount, ?int $cap = null): array
    {
        return [
            'operation' => self::OPERATION,
            'actor' => $this->owner,
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['correctionNo' => 0],
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
