<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-08 (AC-023-03; TECHNICAL_RULES sections 7.1 and 8; EXECUTION_CONTEXT section
 * 10, a command; TASK-023 section 7; suites INV02 and CMD01), each with processes of their own
 * connections under barrier control:
 *
 * - RACE-03, its issue part: two manual issues for the last unit — exactly one succeeds, the
 *   other is refused INSUFFICIENT_STOCK, and stock is never negative; the Sale part is TASK-028's;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run;
 * - a receipt and an issue of one product serialize on its guard: the issue that waited for the
 *   receipt takes its allocation from the valuation the receipt published (ADR-008 section 4.2).
 */
final class ManualIssueRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'manual_issue.confirm';

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

    public function test_race_03_two_issues_for_the_last_unit_give_one_success_and_one_insufficient_stock(): void
    {
        // AC-023-03, BR-INV-04, BR-INV-20: the second waits on the product guard and the balance,
        // then finds no stock left, in both orders of arrival.
        TokoContoh::stock($this->product, '1', '7000');

        $first = $this->start($this->issue('1'), ['after_lock:7']);
        $refused = $this->issue('1');
        $second = $this->start($refused);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:7');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $second->finish()->result);
        $this->assertLeftNothing($refused);
        $this->assertSame(['0.000', 2], $this->balanceOf());
        $this->assertSame(['1.000', '-1.000'], $this->quantities());

        // The last unit took the whole value; the refused issue wrote no allocation.
        $this->assertSame([['KNOWN', '7000.000000']], $this->watcher()->table('issue_cost_allocations')->get(['at_commit_status', 'at_commit_amount'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(['KNOWN', '0.000000', 2], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value', 'publication_no'])));

        // Two units, two issues of two each: one posts, the other finds none left.
        TokoContoh::stock($this->product, '2', '2000');
        $earlier = $this->start($this->issue('2'), ['before_commit']);
        $later = $this->start($this->issue('2'));
        Worker::ready($earlier, $later);

        $earlier->run()->awaitBarrier('before_commit');
        $later->run()->awaitBlocked($this->watcher());
        $earlier->release();

        $this->assertSame('COMMITTED', ($earlier->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $later->finish()->result);
        $this->assertSame(['0.000', 4], $this->balanceOf());
        $this->assertSame(0, $this->watcher()->table('stock_balances')->where('on_hand', '<', 0)->count());
    }

    public function test_race_01_and_race_02_an_issue_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // The second request waits on the first's identity, then answers its result; another
        // payload under either is held, never run.
        TokoContoh::stock($this->product, '20', '20000');

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->issue('3', $intent, $key), ['after_identity']);
            $repeat = $this->start($this->issue('3', $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('manual_issues')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->issue('3', $intent, $key), ['after_identity']);
            $other = $this->start($this->issue('4', $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        // Four issues of three each, one allocation each.
        $this->assertSame(['8.000', 5], $this->balanceOf());
        $this->assertSame(4, $this->watcher()->table('issue_cost_allocations')->count());
        $this->assertSame(4, $this->watcher()->table('issue_cost_allocation_projections')->count());
    }

    public function test_a_receipt_and_an_issue_of_one_product_serialize_on_its_guard(): void
    {
        // BR-INV-05, ADR-008 section 4.2: the issue waits for the receipt's guard, then takes its
        // allocation from the valuation the receipt published: 10 units worth Rp10.000, then 10
        // worth Rp30.000 — 20 worth Rp40.000, so 5 take Rp10.000.
        TokoContoh::stock($this->product, '10', '10000');

        $receipt = $this->start($this->receipt('10', '30000'), ['before_commit']);
        $issue = $this->start($this->issue('5'));
        Worker::ready($receipt, $issue);

        $receipt->run()->awaitBarrier('before_commit');
        $issue->run()->awaitBlocked($this->watcher());
        $receipt->release();

        $this->assertSame('COMMITTED', ($receipt->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($issue->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['10.000', '10.000', '-5.000'], $this->quantities());
        $this->assertSame([['KNOWN', '10000.000000', 2]], $this->watcher()->table('issue_cost_allocations')->get(['at_commit_status', 'at_commit_amount', 'based_on_publication_no'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame(['KNOWN', '30000.000000', 3], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value', 'publication_no'])));
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
     * A refused command left no identity, and so no fact of its own, audit event or result (TS-20).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function assertLeftNothing(array $arguments): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
    }

    /**
     * RC-08 of one line of the product, at the version it stands at, by the Owner.
     *
     * @return array<string, mixed>
     */
    private function issue(string $quantity, ?string $intent = null, ?string $key = null): array
    {
        return $this->command(self::OPERATION, ['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => [['productId' => $this->product, 'quantity' => $quantity]]], $intent, $key);
    }

    /**
     * RC-07 of one line of the product at a KNOWN cost, by the Owner.
     *
     * @return array<string, mixed>
     */
    private function receipt(string $quantity, string $amount): array
    {
        return $this->command('receiving.confirm', ['lines' => [['productId' => $this->product, 'quantity' => $quantity, 'cost' => ['status' => 'KNOWN', 'amount' => $amount]]]]);
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
