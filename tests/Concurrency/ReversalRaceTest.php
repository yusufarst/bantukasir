<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-10 (AC-026-01; TECHNICAL_RULES section 8, RACE-01, RACE-02 and RACE-10; SCHEMA
 * section 5.7, `reversals.original_movement_id` unique; ADR-008 section 7; BR-INV-35; TASK-026 section
 * 7, L3; suites INV03 and CMD01), each with processes of their own connections under barrier control:
 *
 * - RACE-10, two reversals of the same source under different intents: the second waits for the
 *   product's guard, which the first holds to its commit, then finds the source reversed and is
 *   refused STALE_VERSION; one reversal and one REVERSAL exist;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run;
 * - a posting of the product waits for the reversal's replay and takes its allocation from the
 *   valuation that the reversal published (BR-COST-26).
 */
final class ReversalRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'reversal.confirm';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $product = 0;

    /** The second receipt's movement, the source of the reversals. */
    private int $source = 0;

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
        $this->product = TokoContoh::product('RACE-R1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Batal']);
        TokoContoh::stock($this->product, '10', '100');
        $this->source = TokoContoh::movementOf(TokoContoh::stock($this->product, '10', '200'));
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_10_two_reversals_of_the_same_source_give_one_success(): void
    {
        // BR-INV-35: the first holds the product's guard to its commit; the second waits, then finds
        // the source reversed under the same guard: STALE_VERSION, nothing written.
        $first = $this->start($this->reversal(), ['after_lock:7']);
        $waiting = $this->reversal();
        $second = $this->start($waiting);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:7');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result);
        $this->assertSame([1, 1], [$this->watcher()->table('reversals')->count(), $this->watcher()->table('stock_movements')->where('movement_type', 'REVERSAL')->count()]);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $waiting['intent'])->count());
        $this->assertSame(['10.000', 3], array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $this->product)->first(['on_hand', 'last_sequence'])));
    }

    public function test_race_01_and_race_02_a_reversal_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // EXECUTION_CONTEXT section 10, a command; TECHNICAL_RULES section 8: the second request waits
        // on the first's identity, then answers its result; another payload under either is held,
        // never run. Each case reverses its own receipt.
        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $source = TokoContoh::movementOf(TokoContoh::stock($this->product, '1', '10'));
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->reversal($source, $intent, $key), ['after_identity']);
            $repeat = $this->start($this->reversal($source, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('reversals')->where('original_movement_id', $source)->count(), $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $source = TokoContoh::movementOf(TokoContoh::stock($this->product, '1', '10'));
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->reversal($source, $intent, $key), ['after_identity']);
            $other = $this->start($this->reversal($source, $intent, $sameKey ? $key : (string) Str::uuid(), 'Alasan lain'));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        $this->assertSame(0, $this->watcher()->table('reversals')->where('reason', 'Alasan lain')->count());
        $this->assertSame(4, $this->watcher()->table('reversals')->count());
    }

    public function test_a_posting_waits_for_the_reversal_and_takes_its_allocation_from_the_published_valuation(): void
    {
        // BR-COST-26, ADR-008 section 7: the reversal holds the product's guard from step 6 to its
        // commit; the manual issue of 5 waits, then finds 10 worth Rp100 published, and takes Rp50.
        $reversal = $this->start($this->reversal(), ['after_lock:7']);
        $issue = $this->start($this->issue('5'));
        Worker::ready($reversal, $issue);

        $reversal->run()->awaitBarrier('after_lock:7');
        $issue->run()->awaitBlocked($this->watcher());
        $reversal->release();

        $this->assertSame('COMMITTED', ($reversal->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($issue->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '50.000000', 'KNOWN', '50.000000']], array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $this->product)->get(['a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount'])->map(static fn (object $row): array => array_values((array) $row))->all()));
        $this->assertSame(['KNOWN', '50.000000'], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value'])));
    }

    /**
     * RC-10 of this movement, the second receipt's by default, by the Owner, under a new intent and key
     * unless given.
     *
     * @return array<string, mixed>
     */
    private function reversal(?int $source = null, ?string $intent = null, ?string $key = null, string $reason = 'Salah catat'): array
    {
        return [
            'operation' => self::OPERATION,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
            'payload' => ['movementId' => $source ?? $this->source, 'reason' => $reason],
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
