<?php

namespace Tests\Concurrency;

use App\Queries\Catalog\ProductSearch;
use App\Queries\Catalog\SearchFilters;
use App\Queries\Catalog\SearchFirst;
use App\Queries\Catalog\TokenMapping;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-30 (AC-020-03; TECHNICAL_RULES section 8; EXECUTION_CONTEXT section 10, a
 * command; TASK-020 section 7; suite BARCODE01), each with processes of their own connections
 * under barrier control:
 *
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once;
 * - RACE-17: a move against an attach of the same token, and two moves of it: exactly one
 *   succeeds, and a token is never active on two products;
 * - RACE-18, the lookup side: during a move or a re-attachment every lookup resolves the token
 *   to the product before or after it, never to none and never to both. A cart line that keeps
 *   its product is TASK-028's;
 * - RACE-19: two corrections of one retired token, and a correction against an ordinary attach
 *   or a retirement of that token: the first wins and the other is refused;
 * - the locks, taken in ascending order: two corrections that cross end in no deadlock.
 */
final class BarcodeCorrectionRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'barcode.correct_misassignment';

    private const string ACTIVE = 'ACTIVE';

    private const string RETIRED = 'RETIRED_MISASSIGNMENT';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

    private int $wrong = 0;

    private int $right = 0;

    private int $third = 0;

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
        $this->wrong = TokoContoh::product('RACE-01', ['categoryId' => $this->category, 'name' => 'Produk Salah']);
        $this->right = TokoContoh::product('RACE-02', ['categoryId' => $this->category, 'name' => 'Produk Benar']);
        $this->third = TokoContoh::product('RACE-03', ['categoryId' => $this->category, 'name' => 'Produk Ketiga']);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_and_race_02_a_correction_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        foreach (['same key', 'another key'] as $case) {
            $token = 'R-'.Str::random(8);
            TokoContoh::barcode($this->wrong, $token);
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $first = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE, $intent, $key), ['after_identity']);
            $second = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE, $intent, $case === 'same key' ? $key : (string) Str::uuid()));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $original = $first->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null], $second->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $case);
            $this->assertActiveOnlyOn($token, $this->right, 2);
        }
    }

    public function test_race_17_a_move_against_an_attach_of_the_same_token_leaves_one_active_mapping(): void
    {
        foreach ([
            // The attach sees the token active, committed, and waits for nothing.
            'to a third product, by RC-27' => [fn (string $token): array => ['barcode.add', ['productId' => $this->third, 'token' => $token]], false],
            'by RC-17' => [fn (string $token): array => ['product.create', $this->newProduct($token)], false],
            // On the target, the attach waits for the move's lock, then finds it there.
            'to the target, by RC-27' => [fn (string $token): array => ['barcode.add', ['productId' => $this->right, 'token' => $token]], true],
            // On the wrong product too.
            'to the wrong product, by RC-27' => [fn (string $token): array => ['barcode.add', ['productId' => $this->wrong, 'token' => $token]], true],
        ] as $case => [$make, $waits]) {
            $token = 'T-'.Str::random(8);
            TokoContoh::barcode($this->wrong, $token);
            [$operation, $payload] = $make($token);
            $refused = $this->command($operation, $payload);
            $move = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE), ['after_write:4']);
            $attach = $this->start($refused);
            Worker::ready($move, $attach);

            $move->run()->awaitBarrier('after_write:4');

            if ($waits) {
                $attach->run()->awaitBlocked($this->watcher());
                $move->release();
                $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null, $case);
                $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $attach->finish()->result, $case);
            } else {
                $attach->run();
                $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $attach->finish()->result, $case);
                $move->release();
                $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null, $case);
            }

            $this->assertLeftNothing($refused);
            $this->assertActiveOnlyOn($token, $this->right, 2);
        }
    }

    public function test_race_17_an_attach_that_registers_the_token_first_refuses_a_correction_of_it(): void
    {
        // The token is not committed yet, so there is nothing to correct.
        $token = 'T-'.Str::random(8);
        $attach = $this->start($this->command('barcode.add', ['productId' => $this->wrong, 'token' => $token]), ['after_write:3']);
        $refused = $this->correction($token, $this->right, $this->wrong, self::ACTIVE);
        $move = $this->start($refused);
        Worker::ready($attach, $move);

        $attach->run()->awaitBarrier('after_write:3');
        $move->run();
        $this->assertSame(['refused' => 'BARCODE_NOT_ELIGIBLE'], $move->finish()->result);
        $attach->release();
        $this->assertSame('COMMITTED', ($attach->finish()->result ?? [])['outcome'] ?? null);

        $this->assertLeftNothing($refused);
        $this->assertActiveOnlyOn($token, $this->wrong, 1);
    }

    public function test_race_17_two_moves_of_one_token_give_one_success(): void
    {
        foreach ([
            // The second finds the token moved, as the Owner did not review it.
            'to two products' => [$this->third, 'STALE_VERSION'],
            // The second finds it where it asked: there is nothing to move.
            'to one product' => [$this->right, 'BARCODE_NOT_ELIGIBLE'],
        ] as $case => [$target, $code]) {
            $token = 'T-'.Str::random(8);
            TokoContoh::barcode($this->wrong, $token);
            $refused = $this->correction($token, $target, $this->wrong, self::ACTIVE);
            $first = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE), ['after_lock:6']);
            $second = $this->start($refused);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_lock:6');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => $code], $second->finish()->result, $case);
            $this->assertLeftNothing($refused);
            $this->assertActiveOnlyOn($token, $this->right, 2);
        }
    }

    public function test_race_18_a_lookup_during_a_move_resolves_to_the_product_before_or_after_it(): void
    {
        // AC-020-03. Every write step of the move, uncommitted: the retirement, the active
        // row's removal, the correction, its active row, the audit event, the result.
        $token = 'T-'.Str::random(8);
        TokoContoh::barcode($this->wrong, $token);
        $barriers = ['after_write:1', 'after_write:2', 'after_write:3', 'after_write:4', 'after_write:5', 'before_commit'];
        $move = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE), $barriers);
        Worker::ready($move);

        $this->assertLookups($token, $this->wrong);
        $move->run();

        foreach ($barriers as $barrier) {
            $move->awaitBarrier($barrier);
            $this->assertLookups($token, $this->wrong, $barrier);
            $move->release();
        }

        $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLookups($token, $this->right);
    }

    public function test_race_18_a_lookup_during_a_re_attachment_resolves_to_no_product_until_it_commits(): void
    {
        // A retired token leads to no product before the correction, and to one after it,
        // never to both (BR-CAT-24).
        $token = 'T-'.Str::random(8);
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, $token), 'MISASSIGNMENT');
        $barriers = ['after_write:1', 'after_write:2', 'after_write:3', 'before_commit'];
        $move = $this->start($this->correction($token, $this->right, $this->wrong, self::RETIRED), $barriers);
        Worker::ready($move);

        $this->assertLookups($token, null);
        $move->run();

        foreach ($barriers as $barrier) {
            $move->awaitBarrier($barrier);
            $this->assertLookups($token, null, $barrier);
            $move->release();
        }

        $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLookups($token, $this->right);
    }

    public function test_race_19_two_corrections_of_one_retired_token_give_one_success(): void
    {
        foreach ([
            'to two products' => [$this->third, 'STALE_VERSION'],
            'to one product' => [$this->right, 'BARCODE_NOT_ELIGIBLE'],
        ] as $case => [$target, $code]) {
            $token = 'T-'.Str::random(8);
            TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, $token), 'MISASSIGNMENT');
            $refused = $this->correction($token, $target, $this->wrong, self::RETIRED);
            $first = $this->start($this->correction($token, $this->right, $this->wrong, self::RETIRED), ['after_lock:6']);
            $second = $this->start($refused);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_lock:6');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => $code], $second->finish()->result, $case);
            $this->assertLeftNothing($refused);

            // The retirement corrected once, and the token active on the winner's product alone.
            $this->assertActiveOnlyOn($token, $this->right, 2);
        }
    }

    public function test_race_19_an_ordinary_attach_of_a_retired_token_is_always_refused_during_a_correction(): void
    {
        $token = 'T-'.Str::random(8);
        TokoContoh::retireBarcode(TokoContoh::barcode($this->wrong, $token), 'MISASSIGNMENT');
        $correction = $this->start($this->correction($token, $this->right, $this->wrong, self::RETIRED), ['after_write:2']);
        $attach = $this->start($this->command('barcode.add', ['productId' => $this->third, 'token' => $token]));
        Worker::ready($correction, $attach);

        // Still retired, as committed.
        $correction->run()->awaitBarrier('after_write:2');
        $attach->run();
        $this->assertSame(['refused' => 'BARCODE_NOT_ELIGIBLE'], $attach->finish()->result);
        $correction->release();
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);

        // Once it is committed, the token is active: a duplicate.
        $late = $this->start($this->command('barcode.add', ['productId' => $this->third, 'token' => $token]));
        Worker::ready($late);
        $late->run();
        $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $late->finish()->result);

        $this->assertActiveOnlyOn($token, $this->right, 2);
    }

    public function test_a_retirement_and_a_move_of_one_mapping_each_see_the_others_result(): void
    {
        // The move first: the retirement finds the mapping no longer active.
        $token = 'T-'.Str::random(8);
        $mapping = TokoContoh::barcode($this->wrong, $token);
        $move = $this->start($this->correction($token, $this->right, $this->wrong, self::ACTIVE), ['after_lock:6']);
        $retirement = $this->start($this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => 'OTHER']));
        Worker::ready($move, $retirement);

        $move->run()->awaitBarrier('after_lock:6');
        $retirement->run()->awaitBlocked($this->watcher());
        $move->release();

        $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'STALE_VERSION'], $retirement->finish()->result);
        $this->assertSame(['MISASSIGNMENT'], $this->watcher()->table('barcode_mapping_retirements')->where('mapping_id', $mapping)->pluck('reason_class')->all());
        $this->assertActiveOnlyOn($token, $this->right, 2);

        // The retirement first: another reason ends what could be corrected, and a
        // misassignment is not the active mapping that the Owner reviewed.
        foreach (['OTHER' => 'BARCODE_NOT_ELIGIBLE', 'MISASSIGNMENT' => 'STALE_VERSION'] as $reasonClass => $code) {
            $token = 'T-'.Str::random(8);
            $mapping = TokoContoh::barcode($this->wrong, $token);
            $refused = $this->correction($token, $this->right, $this->wrong, self::ACTIVE);
            $retirement = $this->start($this->command('barcode.retire', ['mappingId' => $mapping, 'reasonClass' => $reasonClass]), ['after_lock:6']);
            $move = $this->start($refused);
            Worker::ready($retirement, $move);

            $retirement->run()->awaitBarrier('after_lock:6');
            $move->run()->awaitBlocked($this->watcher());
            $retirement->release();

            $this->assertSame('COMMITTED', ($retirement->finish()->result ?? [])['outcome'] ?? null, $reasonClass);
            $this->assertSame(['refused' => $code], $move->finish()->result, $reasonClass);
            $this->assertLeftNothing($refused);
            $this->assertSame(0, $this->watcher()->table('barcode_active_mappings')->where('mapping_id', $mapping)->count(), $reasonClass);
        }
    }

    public function test_two_corrections_that_cross_take_their_locks_in_one_order_and_both_commit(): void
    {
        // One token from the lower product to the higher, another the other way: each takes
        // the lower lock first, so the second waits there, and neither waits in a circle.
        $up = 'T-'.Str::random(8);
        $down = 'T-'.Str::random(8);
        TokoContoh::barcode($this->wrong, $up);
        TokoContoh::barcode($this->right, $down);
        $this->assertLessThan($this->right, $this->wrong);

        $first = $this->start($this->correction($up, $this->right, $this->wrong, self::ACTIVE), ['after_lock:6']);
        $second = $this->start($this->correction($down, $this->wrong, $this->right, self::ACTIVE));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:6');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($second->finish()->result ?? [])['outcome'] ?? null);
        $this->assertActiveOnlyOn($up, $this->right, 2);
        $this->assertActiveOnlyOn($down, $this->wrong, 2);
    }

    /**
     * What every lookup of the token resolves to: RD-01 in both modes and RD-06 by a product,
     * RD-05 by its active one. Each is one statement of its own snapshot: exactly this product,
     * or none where the token has none.
     */
    private function assertLookups(string $token, ?int $product, string $when = ''): void
    {
        $expected = $product === null ? [] : [$product];
        $search = app(ProductSearch::class);

        $this->assertSame($expected, self::idsOf($search(new SearchFilters(q: $token, exact: true))), 'exact '.$when);
        $this->assertSame($expected, self::idsOf($search(new SearchFilters(q: $token))), 'search '.$when);

        $checked = app(SearchFirst::class)($token, null, null)['barcode'];
        $this->assertSame($product, $checked['id'] ?? null, 'check '.$when);

        $active = app(TokenMapping::class)($token)->activeProduct;
        $this->assertSame($product, $active['id'] ?? null, 'lookup '.$when);
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, page: int, perPage: int, hasMore: bool}  $page
     * @return list<mixed>
     */
    private static function idsOf(array $page): array
    {
        return array_map(static fn (array $row): mixed => $row['id'] ?? null, $page['rows']);
    }

    /**
     * The token holds this many mappings, and is active on this product alone: a token is never
     * active on two products (G-09).
     */
    private function assertActiveOnlyOn(string $token, int $product, int $mappings): void
    {
        $observer = $this->watcher();
        $id = $observer->table('barcode_tokens')->where('token', $token)->value('id');

        $this->assertSame([$product], $observer->table('barcode_active_mappings')->where('token_id', $id)->pluck('product_id')->all(), $token);
        $this->assertSame($mappings, $observer->table('barcode_mappings')->where('token_id', $id)->count(), $token);
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
     * RC-17's payload of a new product with this barcode.
     *
     * @return array<string, mixed>
     */
    private function newProduct(string $token): array
    {
        return [
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Produk Baru',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            'barcode' => ['token' => $token],
        ];
    }

    /**
     * RC-30 of a token to a product, as RD-05 showed it.
     *
     * @return array<string, mixed>
     */
    private function correction(string $token, int $target, int $expectedProduct, string $state, ?string $intent = null, ?string $key = null): array
    {
        return [
            ...$this->command(self::OPERATION, ['token' => $token, 'targetProductId' => $target, 'reason' => 'Salah pasang'], $intent, $key),
            'expected' => ['mapping' => ['productId' => $expectedProduct, 'state' => $state]],
        ];
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
