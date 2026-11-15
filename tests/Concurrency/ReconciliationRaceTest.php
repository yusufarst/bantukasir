<?php

namespace Tests\Concurrency;

use App\Actions\Operations\RebuildProjection;
use App\Domain\Operations\Reconciliation\RepairRefused;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Operations\BlockPostingsScenario;
use Tests\Harness\Operations\RebuildProjectionScenario;
use Tests\Harness\Race\Worker;

/**
 * RACE-24 (AC-036-02; TECHNICAL_RULES section 8; INTERACTION_CONTRACTS section 7 and RC-16; SCHEMA section 5.7;
 * G-11; TASK-036 section 7, L3 RACE-24; suite INV01), with processes of their own connections under barrier
 * control: the reconciliation's `posting_block.block` against a posting of the same product, in both orders —
 * the block first, the posting waits on the product's guard and is refused POSTING_BLOCKED; the posting
 * first, the block waits and its comparison under the guard sees the posting — and RC-16's reopening against
 * a posting, in both orders; and OP-07's rebuild of a token against RC-30's correction of its retired
 * mapping, in both orders.
 */
final class ReconciliationRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->category = TokoContoh::category();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_24_the_reconciliations_block_against_a_posting_in_both_orders(): void
    {
        // The block first: the posting waits for the product's guard, then is refused.
        $first = $this->mismatching('RR-01');
        $block = $this->startBlock($first, ['after_lock:6']);
        $refused = $this->receipt($first, '5');
        $posting = $this->start($refused);
        Worker::ready($block, $posting);

        $block->run()->awaitBarrier('after_lock:6');
        $posting->run()->awaitBlocked($this->watcher());
        $block->release();

        $this->assertSame(['outcome' => 'Written'], $block->finish()->result);
        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $posting->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $refused['intent'])->count());
        $this->assertSame(1, $this->movementsOf($first));

        // The posting first: the block waits, then compares under the guard with the posting's movement
        // among the facts; the balance still differs, so it blocks, and the next posting is refused.
        $second = $this->mismatching('RR-02');
        $posting = $this->start($this->receipt($second, '5'), ['after_lock:6']);
        $block = $this->startBlock($second);
        Worker::ready($posting, $block);

        $posting->run()->awaitBarrier('after_lock:6');
        $block->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['outcome' => 'Written'], $block->finish()->result);
        $this->assertSame(2, $this->movementsOf($second));
        $this->assertSame('BALANCE_MISMATCH', $this->watcher()->table('inventory_posting_blocks')->where('product_id', $second)->value('detail_code'));

        // The posting first, of a product whose facts and projections agree: the block sees it, and
        // writes nothing.
        $third = TokoContoh::product('RR-03', ['categoryId' => $this->category]);
        TokoContoh::stock($third, '5', '5000');
        $posting = $this->start($this->receipt($third, '5'), ['after_lock:6']);
        $block = $this->startBlock($third);
        Worker::ready($posting, $block);

        $posting->run()->awaitBarrier('after_lock:6');
        $block->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['outcome' => 'Agrees'], $block->finish()->result);
        $this->assertSame(0, $this->watcher()->table('inventory_posting_blocks')->where('product_id', $third)->count());
    }

    public function test_race_24_a_reopening_against_a_posting_in_both_orders(): void
    {
        // RC-16: the reopening first, the posting waits, then commits.
        $product = TokoContoh::product('RR-04', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '5', '5000');
        $block = TokoContoh::postingBlock($product);
        $reopen = $this->start($this->command('posting_block.reopen', ['blockId' => $block, 'reason' => null]), ['after_lock:6']);
        $posting = $this->start($this->receipt($product, '1'));
        Worker::ready($reopen, $posting);

        $reopen->run()->awaitBarrier('after_lock:6');
        $posting->run()->awaitBlocked($this->watcher());
        $reopen->release();

        $this->assertSame('COMMITTED', ($reopen->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(2, $this->movementsOf($product));

        // The posting first, while the product is blocked again: it is refused, and the reopening waits,
        // then commits.
        $again = TokoContoh::postingBlock($product);
        $posting = $this->start($this->receipt($product, '1'), ['after_lock:6']);
        $reopen = $this->start($this->command('posting_block.reopen', ['blockId' => $again, 'reason' => null]));
        Worker::ready($posting, $reopen);

        $posting->run()->awaitBarrier('after_lock:6');
        $reopen->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame(['refused' => 'POSTING_BLOCKED'], $posting->finish()->result);
        $this->assertSame('COMMITTED', ($reopen->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(2, $this->movementsOf($product));
    }

    public function test_race_01_and_race_02_a_reopening_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // Review R-02; EXECUTION_CONTEXT section 10, a command: the second request waits on the first's
        // identity, then answers its result; one reopening and one audit event per intent.
        $product = TokoContoh::product('RR-05', ['categoryId' => $this->category]);
        TokoContoh::stock($product, '5', '5000');

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $block = TokoContoh::postingBlock($product);
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $payload = ['blockId' => $block, 'reason' => null];
            $original = $this->start($this->command('posting_block.reopen', $payload, [], $intent, $key), ['after_identity']);
            $repeat = $this->start($this->command('posting_block.reopen', $payload, [], $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $case);
            $this->assertSame(1, $this->watcher()->table('inventory_posting_block_reopenings')->where('block_id', $block)->count(), $case);
            $this->assertSame(1, $this->watcher()->table('audit_events')->where('action', 'POSTING_REOPEN')->where('subject_id', $block)->count(), $case);
        }
    }

    public function test_a_token_rebuild_and_a_correction_of_its_retired_mapping_serialize_in_both_orders(): void
    {
        // Review of pull request #50: a token retired as a misassignment has no unretired mapping and no
        // active row, so the rebuild takes the guard of its last mapping's product, which RC-30 takes too.
        $wrong = TokoContoh::product('RR-06', ['categoryId' => $this->category]);
        $right = TokoContoh::product('RR-07', ['categoryId' => $this->category]);
        $this->assertLessThan($right, $wrong);

        // The rebuild first: the correction waits for the guard, then commits; the rebuild changed nothing.
        $token = $this->retiredToken($wrong);
        $rebuild = $this->startRebuild($token, ['after_lock:6']);
        $correction = $this->start($this->correction($token, $wrong, $right));
        Worker::ready($rebuild, $correction);

        $rebuild->run()->awaitBarrier('after_lock:6');
        $correction->run()->awaitBlocked($this->watcher());
        $rebuild->release();

        $this->assertSame(['changed' => false], $rebuild->finish()->result);
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertActiveOnlyOn($token, $right);

        // The correction first: the rebuild waits for the guard, then finds the token moved, and refuses
        // with nothing changed; the correction's active row stays.
        $token = $this->retiredToken($wrong);
        $correction = $this->start($this->correction($token, $wrong, $right), ['after_lock:6']);
        $rebuild = $this->startRebuild($token);
        Worker::ready($correction, $rebuild);

        $correction->run()->awaitBarrier('after_lock:6');
        $rebuild->run()->awaitBlocked($this->watcher());
        $correction->release();

        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(RepairRefused::class, $rebuild->finish()->failureClass());
        $this->assertActiveOnlyOn($token, $right);
        $this->assertSame(0, $this->watcher()->table('audit_events')->where('action', 'PROJECTION_REBUILT')->where('subject_type', 'token')->where('subject_id', $token)->count());
    }

    /**
     * A manufacturer token of the product, retired as a misassignment: its internal key.
     */
    private function retiredToken(int $product): int
    {
        $token = 'R-'.Str::random(8);
        TokoContoh::retireBarcode(TokoContoh::barcode($product, $token), 'MISASSIGNMENT');
        $id = $this->watcher()->table('barcode_tokens')->where('token', $token)->value('id');

        return is_int($id) ? $id : 0;
    }

    /**
     * RC-30's re-attachment of the token to the target, as RD-05 showed it retired from the product.
     *
     * @return array<string, mixed>
     */
    private function correction(int $token, int $from, int $target): array
    {
        $text = $this->watcher()->table('barcode_tokens')->where('id', $token)->value('token');

        return $this->command('barcode.correct_misassignment', ['token' => is_string($text) ? $text : '', 'targetProductId' => $target, 'reason' => 'Salah pasang'], ['mapping' => ['productId' => $from, 'state' => 'RETIRED_MISASSIGNMENT']]);
    }

    /**
     * The token is active on this product alone, through its correction (G-09).
     */
    private function assertActiveOnlyOn(int $token, int $product): void
    {
        $this->assertSame([$product], $this->watcher()->table('barcode_active_mappings')->where('token_id', $token)->pluck('product_id')->all());
        $this->assertSame(2, $this->watcher()->table('barcode_mappings')->where('token_id', $token)->count());
    }

    /**
     * OP-07's rebuild of the token, for a run of its own (RebuildProjectionScenario).
     *
     * @param  list<string>  $barriers
     */
    private function startRebuild(int $token, array $barriers = []): Worker
    {
        $worker = Worker::start(RebuildProjectionScenario::class, ['subject' => RebuildProjection::TOKEN, 'id' => $token], $barriers);
        $this->workers[] = $worker;

        return $worker;
    }

    /**
     * A product with one receipt whose balance the schema's owner changed on the test database.
     */
    private function mismatching(string $sku): int
    {
        $product = TokoContoh::product($sku, ['categoryId' => $this->category]);
        TokoContoh::stock($product, '5', '5000');
        $this->migration()->table('stock_balances')->where('product_id', $product)->update(['on_hand' => $this->migration()->raw('on_hand + 1')]);

        return $product;
    }

    private function movementsOf(int $product): int
    {
        return $this->watcher()->table('stock_movements')->where('product_id', $product)->count();
    }

    /**
     * RC-07 of one line of the product at a KNOWN cost, at its version, by the Owner.
     *
     * @return array<string, mixed>
     */
    private function receipt(int $product, string $quantity): array
    {
        $version = $this->watcher()->table('products')->where('id', $product)->value('version');

        return $this->command('receiving.confirm', ['lines' => [['productId' => $product, 'quantity' => $quantity, 'cost' => ['status' => 'KNOWN', 'amount' => '1000']]]], ['products' => [['productId' => $product, 'version' => is_int($version) ? $version : 0]]]);
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
     * The reconciliation's block of the product, for a run of its own (BlockPostingsScenario).
     *
     * @param  list<string>  $barriers
     */
    private function startBlock(int $product, array $barriers = []): Worker
    {
        $worker = Worker::start(BlockPostingsScenario::class, ['product' => $product, 'run' => (string) Str::uuid()], $barriers);
        $this->workers[] = $worker;

        return $worker;
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
