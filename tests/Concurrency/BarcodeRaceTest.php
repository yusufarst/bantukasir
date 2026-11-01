<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-27 to RC-29 and RC-17's barcode (AC-016-03; TECHNICAL_RULES section
 * 8; EXECUTION_CONTEXT section 10, a command; TASK-016 section 7), each with processes
 * of their own connections under barrier control:
 *
 * - RACE-01 and RACE-02 for each operation: the same key, or another key of the same
 *   intent, runs once;
 * - RACE-17, its attach part: two attaches of one token, by RC-27 or RC-17, to one
 *   product or two; one commits, the other waits and is refused DUPLICATE_BARCODE, and
 *   a token is never active on two products. Its move is TASK-020's;
 * - RACE-19, its ordinary part: an attach against the retirement of its token is
 *   always refused, and two retirements of one mapping give one fact. Its corrections
 *   are TASK-020's.
 */
final class BarcodeRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

    private int $product = 0;

    private int $other = 0;

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
        $this->product = TokoContoh::product('RACE-01', ['categoryId' => $this->category]);
        $this->other = TokoContoh::product('RACE-02', ['categoryId' => $this->category]);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_and_race_02_each_operation_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        foreach ($this->everyOperation() as $operation => $make) {
            foreach (['same key', 'another key'] as $case) {
                $payload = $make();
                $intent = (string) Str::uuid();
                $key = (string) Str::uuid();
                $first = $this->start($this->command($operation, $payload, $intent, $key), ['after_identity']);
                $second = $this->start($this->command($operation, $payload, $intent, $case === 'same key' ? $key : (string) Str::uuid()));
                Worker::ready($first, $second);

                $first->run()->awaitBarrier('after_identity');
                $second->run()->awaitBlocked($this->watcher());
                $first->release();

                $original = $first->finish()->result ?? [];
                $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], $operation.' '.$case);
                $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null], $second->finish()->result, $operation.' '.$case);
                $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $operation.' '.$case);
            }
        }
    }

    public function test_race_17_two_attaches_of_one_token_give_one_success(): void
    {
        foreach ([
            // Two products: the second waits on the index's entry of the token.
            'two products' => [fn (string $token): array => $this->add($this->product, $token), 1, fn (string $token): array => $this->add($this->other, $token), $this->product],
            // One product: the second waits on the product's lock, then finds the token.
            'one product' => [fn (string $token): array => $this->add($this->product, $token), 1, fn (string $token): array => $this->add($this->product, $token), $this->product],
            // A creation holds the token: its fourth write.
            'a creation first' => [fn (string $token): array => $this->create('SKU-A-'.$token, $token), 4, fn (string $token): array => $this->add($this->other, $token), null],
            'an add first' => [fn (string $token): array => $this->add($this->product, $token), 1, fn (string $token): array => $this->create('SKU-B-'.$token, $token), $this->product],
            'two creations' => [fn (string $token): array => $this->create('SKU-C-'.$token, $token), 4, fn (string $token): array => $this->create('SKU-D-'.$token, $token), null],
        ] as $case => [$winner, $step, $loser, $product]) {
            $token = 'T-'.Str::random(8);
            [$winnerOperation, $winnerPayload] = $winner($token);
            [$loserOperation, $loserPayload] = $loser($token);
            $refused = $this->command($loserOperation, $loserPayload);

            $first = $this->start($this->command($winnerOperation, $winnerPayload), ['after_write:'.$step]);
            $second = $this->start($refused);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_write:'.$step);
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $second->finish()->result, $case);
            $this->assertLeftNothing($refused);

            // One token, one mapping, and active on the winner's product alone.
            $observer = $this->watcher();
            $id = $observer->table('barcode_tokens')->where('token', $token)->value('id');
            $this->assertSame(1, $observer->table('barcode_tokens')->where('token', $token)->count(), $case);
            $this->assertSame(1, $observer->table('barcode_mappings')->where('token_id', $id)->count(), $case);
            $active = $observer->table('barcode_active_mappings')->where('token_id', $id)->pluck('product_id')->all();
            $this->assertCount(1, $active, $case);
            $this->assertSame($product ?? $observer->table('products')->where('sku', 'SKU-'.($case === 'a creation first' ? 'A' : 'C').'-'.$token)->value('id'), $active[0] ?? null, $case);
        }
    }

    public function test_race_19_an_attach_against_the_retirement_of_its_token_is_always_refused(): void
    {
        // The retirement holds its work uncommitted: an attach to another product still
        // finds the token active, without waiting.
        $mapping = TokoContoh::barcode($this->product, 'PENSIUN-1');
        $retirement = $this->start($this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'MISASSIGNMENT']), ['after_write:2']);
        $attach = $this->start($this->command('barcode.add', ['productId' => $this->other, 'token' => 'PENSIUN-1']));
        Worker::ready($retirement, $attach);

        $retirement->run()->awaitBarrier('after_write:2');
        $attach->run();
        $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $attach->finish()->result);
        $retirement->release();
        $this->assertSame('COMMITTED', ($retirement->finish()->result ?? [])['outcome'] ?? null);

        // Once it is committed, the token is retired for every product.
        foreach ([$this->product, $this->other] as $product) {
            $late = $this->start($this->command('barcode.add', ['productId' => $product, 'token' => 'PENSIUN-1']));
            Worker::ready($late);
            $late->run();
            $this->assertSame(['refused' => 'BARCODE_NOT_ELIGIBLE'], $late->finish()->result);
        }

        // An attach to the same product waits on its lock, then finds it retired.
        $mapping = TokoContoh::barcode($this->product, 'PENSIUN-2');
        $retirement = $this->start($this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER']), ['after_lock:6']);
        $attach = $this->start($this->command('barcode.add', ['productId' => $this->product, 'token' => 'PENSIUN-2']));
        Worker::ready($retirement, $attach);

        $retirement->run()->awaitBarrier('after_lock:6');
        $attach->run()->awaitBlocked($this->watcher());
        $retirement->release();

        $this->assertSame('COMMITTED', ($retirement->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'BARCODE_NOT_ELIGIBLE'], $attach->finish()->result);

        // Neither token is active on any product, and each has its one original.
        $observer = $this->watcher();
        $tokens = $observer->table('barcode_tokens')->whereIn('token', ['PENSIUN-1', 'PENSIUN-2'])->pluck('id')->all();
        $this->assertCount(2, $tokens);
        $this->assertSame(0, $observer->table('barcode_active_mappings')->whereIn('token_id', $tokens)->count());
        $this->assertSame(2, $observer->table('barcode_mappings')->whereIn('token_id', $tokens)->count());
    }

    public function test_two_retirements_of_one_mapping_give_one_fact(): void
    {
        $mapping = TokoContoh::barcode($this->product, 'PENSIUN-1');
        $refused = $this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER']);
        $first = $this->start($this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'MISASSIGNMENT']), ['after_lock:6']);
        $second = $this->start($refused);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:6');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result);
        $this->assertLeftNothing($refused);

        // The first's reason alone, and no active row (G-09).
        $this->assertSame(['MISASSIGNMENT'], $this->watcher()->table('barcode_mapping_retirements')->where('mapping_id', $mapping)->pluck('reason_class')->all());
        $this->assertSame(0, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->count());
    }

    public function test_a_generation_and_an_attach_on_one_product_each_commit_after_the_other(): void
    {
        // The product's lock orders them, and each keeps its own token.
        $first = $this->start($this->command('barcode.generate', ['productId' => $this->product]), ['after_lock:6']);
        $second = $this->start($this->command('barcode.add', ['productId' => $this->product, 'token' => 'ALIAS-1']));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:6');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($second->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(2, $this->watcher()->table('barcode_active_mappings')->where('product_id', $this->product)->count());
    }

    /**
     * A refused command left no identity, and so no fact of its own, audit event or
     * result, each of which names its identity (AC-016-03, TS-20).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function assertLeftNothing(array $arguments): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
    }

    /**
     * Each operation with what it needs, a new record for each call.
     *
     * @return array<string, callable(): array<string, mixed>>
     */
    private function everyOperation(): array
    {
        return [
            'barcode.add' => fn (): array => ['productId' => $this->product, 'token' => 'R-'.Str::random(8)],
            'barcode.generate' => fn (): array => ['productId' => $this->product],
            'barcode.retire' => fn (): array => ['mappingId' => TokoContoh::barcode($this->product, 'R-'.Str::random(8)), 'reasonClass' => 'OTHER'],
            'product.create' => fn (): array => $this->create('R-'.Str::random(8), 'R-'.Str::random(8))[1],
        ];
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function add(int $product, string $token): array
    {
        return ['barcode.add', ['productId' => $product, 'token' => $token]];
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function create(string $sku, string $token): array
    {
        return ['product.create', [
            'sku' => $sku,
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            'barcode' => ['token' => $token],
        ]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => $operation,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
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
