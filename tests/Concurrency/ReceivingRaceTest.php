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
 * The races of RC-07 (AC-021-03, AC-021-05, AC-021-06; TECHNICAL_RULES sections 7.1 and 8;
 * EXECUTION_CONTEXT section 10, a command; TASK-021 section 7; suites INV01 and SEARCH02), each
 * with processes of their own connections under barrier control:
 *
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run;
 * - RACE-05: an edit of the unit and the precision against the product's first movement, in both
 *   orders: the edit commits first and the posting finds a version it did not review, or the
 *   posting commits first and the edit is refused; a posted fact never changes meaning;
 * - RACE-24: a posting block against a posting, in both orders;
 * - RACE-04's target guard: a deactivation against a posting, in both orders;
 * - the product guards, taken in ascending order: postings that cross end in no deadlock, and
 *   two receipts of one product take the next places of its sequence.
 */
final class ReceivingRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'receiving.confirm';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

    /** The lower of the two products' keys. */
    private int $first = 0;

    private int $second = 0;

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
        $this->category = TokoContoh::category();
        $this->first = TokoContoh::product('RACE-P1', ['categoryId' => $this->category, 'name' => 'Produk Satu']);
        $this->second = TokoContoh::product('RACE-P2', ['categoryId' => $this->category, 'name' => 'Produk Dua']);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_and_race_02_a_confirmation_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // AC-021-03: the second request waits on the first's identity, then answers its result.
        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->receipt([[$this->first, '5', '5000']], $intent, $key), ['after_identity']);
            $repeat = $this->start($this->receipt([[$this->first, '5', '5000']], $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $case);
            $this->assertSame(1, $this->watcher()->table('receiving_documents')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
        }

        // One document per intent, each of five units.
        $this->assertSame(['10.000', 2], $this->balanceOf($this->first));

        // Another payload under the same key, or under another key of the same intent: held.
        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->receipt([[$this->first, '5', '5000']], $intent, $key), ['after_identity']);
            $other = $this->start($this->receipt([[$this->first, '7', '5000']], $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        $this->assertSame(['20.000', 4], $this->balanceOf($this->first));
        $this->assertSame(['5.000', '5.000', '5.000', '5.000'], $this->quantitiesOf($this->first));
    }

    public function test_race_05_an_edit_of_the_unit_commits_before_the_first_movement_or_is_refused(): void
    {
        // AC-021-05, BR-CAT-07: the edit holds the product FOR UPDATE, which a posting's FOR NO KEY
        // UPDATE waits for, and the other way round. The edit first: the posting waits, then finds
        // a version that the user did not review, and posts nothing.
        $edit = $this->start($this->command('product.update', $this->unitChange($this->first, 'Produk Satu'), ['productVersion' => 1]), ['after_lock:6']);
        $refused = $this->receipt([[$this->first, '5', '5000']]);
        $posting = $this->start($refused);
        Worker::ready($edit, $posting);

        $edit->run()->awaitBarrier('after_lock:6');
        $posting->run()->awaitBlocked($this->watcher());
        $edit->release();

        $this->assertSame('COMMITTED', ($edit->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'STALE_VERSION'], $posting->finish()->result);
        $this->assertLeftNothing($refused);
        $this->assertSame(['kg', 3, 2], $this->unitOf($this->first));
        $this->assertSame([], $this->quantitiesOf($this->first));

        // The posting first: the edit waits, then finds the movement and is refused at its fields.
        $posting = $this->start($this->receipt([[$this->second, '5', '5000']]), ['after_lock:6']);
        $refused = $this->command('product.update', $this->unitChange($this->second, 'Produk Dua'), ['productVersion' => 1]);
        $edit = $this->start($refused);
        Worker::ready($posting, $edit);

        $posting->run()->awaitBarrier('after_lock:6');
        $edit->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'VALIDATION_FAILED'], $edit->finish()->result);
        $this->assertLeftNothing($refused);

        // The movement keeps the unit it was posted in, which is still the product's.
        $this->assertSame(['pcs', 0, 1], $this->unitOf($this->second));
        $this->assertSame([['pcs', 0]], $this->watcher()->table('stock_movements')->where('product_id', $this->second)->get(['base_unit', 'quantity_precision'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_race_24_a_posting_block_against_a_posting_each_sees_the_others_result(): void
    {
        // AC-021-06, BR-INV-45, G-11: both hold the product guard. The block first: the posting
        // waits for it, then is refused.
        $block = $this->startBlock($this->first, ['after_lock:6']);
        $refused = $this->receipt([[$this->first, '5', '5000']]);
        $posting = $this->start($refused);
        Worker::ready($block, $posting);

        $block->run()->awaitBarrier('after_lock:6');
        $posting->run()->awaitBlocked($this->watcher());
        $block->release();

        $this->assertIsInt(($block->finish()->result ?? [])['block'] ?? null);
        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $posting->finish()->result);
        $this->assertLeftNothing($refused);
        $this->assertSame([], $this->quantitiesOf($this->first));

        // The posting first: the block waits, both commit, and the next posting is refused.
        $posting = $this->start($this->receipt([[$this->second, '5', '5000']]), ['after_lock:6']);
        $block = $this->startBlock($this->second);
        Worker::ready($posting, $block);

        $posting->run()->awaitBarrier('after_lock:6');
        $block->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertIsInt(($block->finish()->result ?? [])['block'] ?? null);
        $this->assertSame(['5.000', 1], $this->balanceOf($this->second));

        $late = $this->start($this->receipt([[$this->second, '1', '1000']]));
        Worker::ready($late);
        $late->run();
        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $late->finish()->result);
        $this->assertSame(['5.000', 1], $this->balanceOf($this->second));
    }

    public function test_a_deactivation_against_a_posting_each_sees_the_others_result(): void
    {
        // RACE-04's target guard, RC-19 against RC-07: the change that commits first is the one in
        // force. The deactivation first: the posting is refused PRODUCT_INACTIVE.
        $deactivation = $this->start($this->command('product.deactivate', ['productId' => $this->first], ['productVersion' => 1]), ['after_lock:6']);
        $refused = $this->receipt([[$this->first, '5', '5000']]);
        $posting = $this->start($refused);
        Worker::ready($deactivation, $posting);

        $deactivation->run()->awaitBarrier('after_lock:6');
        $posting->run()->awaitBlocked($this->watcher());
        $deactivation->release();

        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'PRODUCT_INACTIVE'], $posting->finish()->result);
        $this->assertLeftNothing($refused);

        // The posting first: a movement moves no version, so the deactivation that waited for it
        // still finds the version it expects, and both commit.
        $posting = $this->start($this->receipt([[$this->second, '5', '5000']]), ['after_lock:6']);
        $deactivation = $this->start($this->command('product.deactivate', ['productId' => $this->second], ['productVersion' => 1]));
        Worker::ready($posting, $deactivation);

        $posting->run()->awaitBarrier('after_lock:6');
        $deactivation->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([false, 2], array_values((array) $this->watcher()->table('products')->where('id', $this->second)->first(['active', 'version'])));
        $this->assertSame(['5.000', 1], $this->balanceOf($this->second));
    }

    public function test_the_product_guards_are_taken_in_ascending_order_so_crossing_postings_end_in_no_deadlock(): void
    {
        // TR §7.1 step 6, TR-LOCK-01. A holds the higher product; B, whose lines name the higher
        // before the lower, takes the lower and waits for the higher; C, on the lower, then waits
        // for B. Nobody waits in a circle, so all three commit in the order of the guards.
        $this->assertLessThan($this->second, $this->first);
        $a = $this->start($this->receipt([[$this->second, '1', '1000']]), ['after_lock:6']);
        $b = $this->start($this->receipt([[$this->second, '2', '2000'], [$this->first, '2', '2000']]));
        $c = $this->start($this->receipt([[$this->first, '3', '3000']]));
        Worker::ready($a, $b, $c);

        $a->run()->awaitBarrier('after_lock:6');
        $b->run()->awaitBlocked($this->watcher());
        $c->run()->awaitBlocked($this->watcher());
        $a->release();

        foreach (['A' => $a, 'B' => $b, 'C' => $c] as $name => $worker) {
            $this->assertSame('COMMITTED', ($worker->finish()->result ?? [])['outcome'] ?? null, $name);
        }

        // Each product's sequence is the order its guard was held in: A then B on the higher, B
        // then C on the lower.
        $this->assertSame(['1.000', '2.000'], $this->quantitiesOf($this->second));
        $this->assertSame(['2.000', '3.000'], $this->quantitiesOf($this->first));
        $this->assertSame([['3.000', 2], ['5.000', 2]], [$this->balanceOf($this->second), $this->balanceOf($this->first)]);
    }

    public function test_two_receipts_of_one_product_serialize_on_its_guard_and_take_the_next_places_of_its_sequence(): void
    {
        // BR-INV-05, G-03: the second waits for the first's guard, then reads the balance and the
        // valuation that the first committed.
        $earlier = $this->start($this->receipt([[$this->first, '4', '4000']]), ['before_commit']);
        $later = $this->start($this->receipt([[$this->first, '6', '9000']]));
        Worker::ready($earlier, $later);

        $earlier->run()->awaitBarrier('before_commit');
        $later->run()->awaitBlocked($this->watcher());
        $earlier->release();

        $this->assertSame('COMMITTED', ($earlier->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($later->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['4.000', '6.000'], $this->quantitiesOf($this->first));
        $this->assertSame(['10.000', 2], $this->balanceOf($this->first));
        $this->assertSame(['KNOWN', '13000.000000', 2, 2], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->first)->first(['value_status', 'value', 'publication_no', 'last_sequence'])));
    }

    /**
     * A product's balance: on hand and its last sequence, or null before its first movement.
     *
     * @return list<mixed>|null
     */
    private function balanceOf(int $product): ?array
    {
        $row = $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']);

        return $row === null ? null : array_values((array) $row);
    }

    /**
     * The quantities of a product's movements in the order of its sequence.
     *
     * @return list<mixed>
     */
    private function quantitiesOf(int $product): array
    {
        return array_values($this->watcher()->table('stock_movements')->where('product_id', $product)->orderBy('product_sequence')->pluck('quantity')->all());
    }

    /**
     * A product's unit, precision and version.
     *
     * @return list<mixed>
     */
    private function unitOf(int $product): array
    {
        return array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['base_unit', 'quantity_precision', 'version']));
    }

    /**
     * A refused command left no identity, and so no fact of its own, audit event or result,
     * each of which names its identity (TS-20).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function assertLeftNothing(array $arguments): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
    }

    /**
     * RC-18's payload of the product with the unit and precision of a weighed product.
     *
     * @return array<string, mixed>
     */
    private function unitChange(int $product, string $name): array
    {
        return [
            'productId' => $product,
            'name' => $name,
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'kg',
            'quantityPrecision' => 3,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
        ];
    }

    /**
     * RC-07 of these lines — a product, a quantity and a KNOWN cost each — at the versions their
     * products stand at, by the Owner.
     *
     * @param  list<array{int, string, string}>  $lines
     * @return array<string, mixed>
     */
    private function receipt(array $lines, ?string $intent = null, ?string $key = null): array
    {
        $payload = [];
        $versions = [];

        foreach ($lines as [$product, $quantity, $amount]) {
            $payload[] = ['productId' => $product, 'quantity' => $quantity, 'cost' => ['status' => 'KNOWN', 'amount' => $amount]];
            $version = $this->watcher()->table('products')->where('id', $product)->value('version');
            $versions[$product] = ['productId' => $product, 'version' => is_int($version) ? $version : 0];
        }

        return $this->command(self::OPERATION, ['lines' => $payload], ['products' => array_values($versions)], $intent, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, array $expected = [], ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => $operation,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => $expected,
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

    /**
     * A posting block of the product, as the reconciliation will write it (PostingBlockScenario).
     *
     * @param  list<string>  $barriers
     */
    private function startBlock(int $product, array $barriers = []): Worker
    {
        $worker = Worker::start(PostingBlockScenario::class, ['product' => $product, 'epoch' => $this->epoch], $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
