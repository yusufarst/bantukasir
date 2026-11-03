<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Inventory\PostingBlockScenario;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-09 (AC-024-03; TECHNICAL_RULES sections 7.1 and 8; EXECUTION_CONTEXT section 10,
 * a command; TASK-024 section 7; suites INV03 and CMD01), each with processes of their own
 * connections under barrier control:
 *
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run;
 * - a decrease and a manual issue for the last unit serialize on the product's guard: exactly one
 *   succeeds, the other is refused INSUFFICIENT_STOCK, and stock is never negative (BR-INV-33);
 * - an increase and a decrease of one product serialize on its guard: the decrease that waited
 *   takes its allocation from the valuation the increase published (ADR-008 section 4.2);
 * - RACE-24: a posting block against an adjustment, in both orders (review finding R-01).
 */
final class AdjustmentRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'adjustment.confirm';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $product = 0;

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
        $this->product = TokoContoh::product('RACE-P1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Satu']);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_and_race_02_an_adjustment_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // The second request waits on the first's identity, then answers its result; another
        // payload under either is held, never run.
        TokoContoh::stock($this->product, '20', '20000');

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->decrease('3', $intent, $key), ['after_identity']);
            $repeat = $this->start($this->decrease('3', $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('adjustments')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->decrease('3', $intent, $key), ['after_identity']);
            $other = $this->start($this->decrease('4', $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        // Four decreases of three each, one allocation each.
        $this->assertSame(['8.000', 5], $this->balanceOf());
        $this->assertSame(4, $this->watcher()->table('issue_cost_allocations')->where('issue_type', 'ADJUSTMENT')->count());
        $this->assertSame(4, $this->watcher()->table('adjustments')->count());
    }

    public function test_a_decrease_and_a_manual_issue_for_the_last_unit_give_one_success_and_one_insufficient_stock(): void
    {
        // AC-024-02, BR-INV-33, G-02: the issue waits on the product guard and the balance, then
        // finds no stock left; stock is never negative.
        TokoContoh::stock($this->product, '1', '7000');

        $first = $this->start($this->decrease('1'), ['after_lock:7']);
        $issue = $this->command('manual_issue.confirm', ['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => [['productId' => $this->product, 'quantity' => '1']]]);
        $second = $this->start($issue);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:7');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $second->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $issue['intent'])->count());
        $this->assertSame(['0.000', 2], $this->balanceOf());
        $this->assertSame(['1.000', '-1.000'], $this->quantities());
        $this->assertSame([['ADJUSTMENT', 'KNOWN', '7000.000000']], $this->watcher()->table('issue_cost_allocations')->get(['issue_type', 'at_commit_status', 'at_commit_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(0, $this->watcher()->table('stock_balances')->where('on_hand', '<', 0)->count());
    }

    public function test_an_increase_and_a_decrease_of_one_product_serialize_on_its_guard(): void
    {
        // BR-INV-05, ADR-008 section 4.2: the decrease waits for the increase's guard, then takes
        // its allocation from the valuation the increase published: 10 units worth Rp10.000, then
        // 10 more worth Rp30.000 — 20 worth Rp40.000, so 5 take Rp10.000.
        TokoContoh::stock($this->product, '10', '10000');

        $increase = $this->start($this->command(self::OPERATION, $this->payload([['productId' => $this->product, 'direction' => 'INCREASE', 'quantity' => '10', 'cost' => ['status' => 'KNOWN', 'amount' => '30000']]])), ['before_commit']);
        $decrease = $this->start($this->decrease('5'));
        Worker::ready($increase, $decrease);

        $increase->run()->awaitBarrier('before_commit');
        $decrease->run()->awaitBlocked($this->watcher());
        $increase->release();

        $this->assertSame('COMMITTED', ($increase->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($decrease->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['10.000', '10.000', '-5.000'], $this->quantities());
        $this->assertSame([['KNOWN', '10000.000000', 2]], $this->watcher()->table('issue_cost_allocations')->get(['at_commit_status', 'at_commit_amount', 'based_on_publication_no'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(['KNOWN', '30000.000000', 3], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value', 'publication_no'])));
    }

    public function test_race_24_a_posting_block_against_an_adjustment_each_sees_the_others_result(): void
    {
        // RC-09 "Tests: RACE-24"; BR-INV-45, G-11: both hold the product guard. The block first:
        // the adjustment waits for it, then is refused and writes nothing (review finding R-01).
        TokoContoh::stock($this->product, '5', '5000');
        $block = $this->startBlock(['after_lock:6']);
        $refused = $this->decrease('2');
        $adjustment = $this->start($refused);
        Worker::ready($block, $adjustment);

        $block->run()->awaitBarrier('after_lock:6');
        $adjustment->run()->awaitBlocked($this->watcher());
        $block->release();

        $this->assertIsInt(($block->finish()->result ?? [])['block'] ?? null);
        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $adjustment->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $refused['intent'])->count());
        $this->assertSame(['5.000', 1], $this->balanceOf());
        $this->assertSame(0, $this->watcher()->table('adjustments')->count());

        // The adjustment first: the block waits, both commit, and the next adjustment is refused.
        $this->product = TokoContoh::product('RACE-P2', ['categoryId' => TokoContoh::category('Kedua'), 'name' => 'Produk Dua']);
        TokoContoh::stock($this->product, '5', '5000');
        $first = $this->start($this->decrease('2'), ['after_lock:6']);
        $block = $this->startBlock();
        Worker::ready($first, $block);

        $first->run()->awaitBarrier('after_lock:6');
        $block->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertIsInt(($block->finish()->result ?? [])['block'] ?? null);
        $this->assertSame(['3.000', 2], $this->balanceOf());

        $late = $this->start($this->command(self::OPERATION, $this->payload([['productId' => $this->product, 'direction' => 'INCREASE', 'quantity' => '1', 'cost' => ['status' => 'UNKNOWN']]])));
        Worker::ready($late);
        $late->run();
        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $late->finish()->result);
        $this->assertSame(['3.000', 2], $this->balanceOf());
    }

    /**
     * A posting block of the product, as the reconciliation will write it (PostingBlockScenario).
     *
     * @param  list<string>  $barriers
     */
    private function startBlock(array $barriers = []): Worker
    {
        $worker = Worker::start(PostingBlockScenario::class, ['product' => $this->product, 'epoch' => $this->epoch], $barriers);
        $this->workers[] = $worker;

        return $worker;
    }

    /**
     * The product's balance: on hand and its last sequence.
     *
     * @return list<mixed>|null
     */
    private function balanceOf(): ?array
    {
        $row = $this->watcher()->table('stock_balances')->where('product_id', $this->product)->first(['on_hand', 'last_sequence']);

        return $row === null ? null : array_values((array) $row);
    }

    /**
     * The quantities of the product's movements in the order of its sequence.
     *
     * @return list<mixed>
     */
    private function quantities(): array
    {
        return array_values($this->watcher()->table('stock_movements')->where('product_id', $this->product)->orderBy('product_sequence')->pluck('quantity')->all());
    }

    /**
     * The payload of RC-09 with these lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function payload(array $lines): array
    {
        return ['reason' => 'Hitung fisik', 'countReference' => 'HIT-01', 'reference' => 'REF-01', 'lines' => $lines];
    }

    /**
     * RC-09 of one decrease of the product, at the version it stands at, by the Owner.
     *
     * @return array<string, mixed>
     */
    private function decrease(string $quantity, ?string $intent = null, ?string $key = null): array
    {
        return $this->command(self::OPERATION, $this->payload([['productId' => $this->product, 'direction' => 'DECREASE', 'quantity' => $quantity]]), $intent, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, ?string $intent = null, ?string $key = null): array
    {
        $version = $this->watcher()->table('products')->where('id', $this->product)->value('version');

        return [
            'operation' => $operation,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['products' => [['productId' => $this->product, 'version' => is_int($version) ? $version : 0]]],
            'payload' => $payload,
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
