<?php

namespace Tests\Concurrency;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-17 to RC-24 (AC-015-01; TECHNICAL_RULES section 8;
 * EXECUTION_CONTEXT section 10, a command; TASK-015 section 7), each with
 * processes of their own connections under barrier control:
 *
 * - RACE-01 and RACE-02 for each operation: the same key, or another key of the
 *   same intent, runs once;
 * - two creations with one SKU, also in another letter case: one commits, the
 *   other waits on the unique index and is refused DUPLICATE_SKU;
 * - RACE-29: a category's deactivation against a product's creation and against a
 *   product's edit that assigns it, in both orders;
 * - RACE-04, the catalogue's part: a role change against a running command, in both
 *   orders;
 * - RACE-05 and RACE-23, what TASK-015 holds of them: two edits against one version,
 *   a price change against a deactivation; the second waits on the product's lock
 *   and is stale. Their sides of a posting and a Sale are TASK-021's and TASK-028's.
 */
final class CatalogRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

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
                [$payload, $expected] = $make();
                $intent = (string) Str::uuid();
                $key = (string) Str::uuid();
                $first = $this->start($this->command($operation, $payload, $expected, $intent, $key), ['after_identity']);
                $second = $this->start($this->command($operation, $payload, $expected, $intent, $case === 'same key' ? $key : (string) Str::uuid()));
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

    public function test_two_creations_with_one_sku_give_one_success_also_in_another_letter_case(): void
    {
        foreach ([['RACE-01', 'RACE-01'], ['RACE-02', 'race-02'], ['ÉCLAIR', 'éclair']] as [$sku, $other]) {
            // The first holds its insert open, on the index's entry.
            $first = $this->start($this->command('product.create', $this->productPayload($sku), []), ['after_write:1']);
            $second = $this->start($this->command('product.create', $this->productPayload($other), []));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_write:1');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $sku);
            $this->assertSame(['refused' => 'DUPLICATE_SKU'], $second->finish()->result, $sku);
            $this->assertSame(1, $this->watcher()->table('products')->whereRaw('lower(sku) = lower(?)', [$sku])->count(), $sku);
            $this->assertSame($sku, $this->watcher()->table('products')->whereRaw('lower(sku) = lower(?)', [$sku])->value('sku'));
        }

        // Nothing of the refused creation stays: one revision each.
        $this->assertSame($this->watcher()->table('products')->count(), $this->watcher()->table('selling_price_revisions')->count());
    }

    public function test_race_29_a_deactivation_against_a_creation_in_both_orders(): void
    {
        // The deactivation holds the category first: the creation waits and is
        // refused, and nothing is written.
        $category = TokoContoh::category('Satu');
        $refused = $this->command('product.create', $this->productPayload('DULU-01', $category), []);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]), ['after_lock:6']);
        $creation = $this->start($refused);
        Worker::ready($deactivation, $creation);

        $deactivation->run()->awaitBarrier('after_lock:6');
        $creation->run()->awaitBlocked($this->watcher());
        $deactivation->release();

        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'CATEGORY_INACTIVE'], $creation->finish()->result);
        $this->assertSame(0, $this->watcher()->table('products')->where('sku', 'DULU-01')->count());
        $this->assertLeftNothing($refused);

        // The creation holds the category first: it commits, then the deactivation,
        // which changes no product.
        $category = TokoContoh::category('Dua');
        $creation = $this->start($this->command('product.create', $this->productPayload('NANTI-01', $category), []), ['after_lock:6']);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]));
        Worker::ready($creation, $deactivation);

        $creation->run()->awaitBarrier('after_lock:6');
        $deactivation->run()->awaitBlocked($this->watcher());
        $creation->release();

        $this->assertSame('COMMITTED', ($creation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([$category, true], array_values((array) $this->watcher()->table('products')->where('sku', 'NANTI-01')->first(['category_id', 'active'])));
        $this->assertFalse($this->watcher()->table('categories')->where('id', $category)->value('active'));
    }

    public function test_race_29_a_deactivation_against_an_edit_that_assigns_the_category_in_both_orders(): void
    {
        // The deactivation first: the edit waits and is refused, and the product
        // stays as it was.
        $product = TokoContoh::product('EDIT-01', ['categoryId' => $this->category]);
        $category = TokoContoh::category('Tiga');
        $refused = $this->command('product.update', $this->editPayload($product, ['categoryId' => $category]), ['productVersion' => 1]);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]), ['after_lock:6']);
        $edit = $this->start($refused);
        Worker::ready($deactivation, $edit);

        $deactivation->run()->awaitBarrier('after_lock:6');
        $edit->run()->awaitBlocked($this->watcher());
        $deactivation->release();

        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'CATEGORY_INACTIVE'], $edit->finish()->result);
        $this->assertSame([$this->category, 1], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['category_id', 'version'])));
        $this->assertLeftNothing($refused);

        // The edit first: it commits, and the deactivation changes no product.
        $category = TokoContoh::category('Empat');
        $edit = $this->start($this->command('product.update', $this->editPayload($product, ['categoryId' => $category]), ['productVersion' => 1]), ['after_lock:6']);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]));
        Worker::ready($edit, $deactivation);

        $edit->run()->awaitBarrier('after_lock:6');
        $deactivation->run()->awaitBlocked($this->watcher());
        $edit->release();

        $this->assertSame('COMMITTED', ($edit->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([$category, 2], array_values((array) $this->watcher()->table('products')->where('id', $product)->first(['category_id', 'version'])));
    }

    public function test_race_04_a_role_change_against_a_running_command_in_both_orders(): void
    {
        // The command holds the actor's row first: it commits, then the change.
        $staff = TokoContoh::staff('Satu', ['operationsAdmin']);
        $command = $this->start([...$this->command('category.create', ['name' => 'Satu'], []), 'actor' => $staff['id']], ['after_lock:2.1']);
        $change = $this->start($this->roleChange($staff['id'], $staff['email'], 'Satu'));
        Worker::ready($command, $change);

        $command->run()->awaitBarrier('after_lock:2.1');
        $change->run()->awaitBlocked($this->watcher());
        $command->release();

        $this->assertSame('COMMITTED', ($command->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($change->finish()->result ?? [])['outcome'] ?? null);

        // The change holds the row first: the command is refused before any fact.
        $staff = TokoContoh::staff('Dua', ['operationsAdmin']);
        $change = $this->start($this->roleChange($staff['id'], $staff['email'], 'Dua'), ['after_lock:2.2']);
        $command = $this->start([...$this->command('category.create', ['name' => 'Dua'], []), 'actor' => $staff['id']]);
        Worker::ready($change, $command);

        $change->run()->awaitBarrier('after_lock:2.2');
        $command->run()->awaitBlocked($this->watcher());
        $change->release();

        $this->assertSame('COMMITTED', ($change->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'FORBIDDEN'], $command->finish()->result);
        $this->assertSame(0, $this->watcher()->table('categories')->where('name', 'Dua')->count());
    }

    public function test_two_edits_against_one_version_the_second_is_stale_and_no_price_is_lost(): void
    {
        // RACE-05's and RACE-23's lock: the writer that holds the product first is
        // in force. The second reads the row before the first commits, chooses its
        // locks on it, and is refused under the lock.
        foreach ([
            'two prices' => [['sellingPrice' => '8000'], ['sellingPrice' => '9000'], [7500, 8000]],
            'a unit and a price' => [['baseUnit' => 'kg'], ['sellingPrice' => '9000'], [7500]],
            'a price and a unit' => [['sellingPrice' => '8000'], ['baseUnit' => 'kg', 'quantityPrecision' => 2], [7500, 8000]],
        ] as $case => [$firstFields, $secondFields, $prices]) {
            $product = TokoContoh::product('V-'.Str::random(8), ['categoryId' => $this->category]);
            $refused = $this->command('product.update', $this->editPayload($product, $secondFields), ['productVersion' => 1]);
            $first = $this->start($this->command('product.update', $this->editPayload($product, $firstFields), ['productVersion' => 1]), ['after_lock:6']);
            $second = $this->start($refused);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_lock:6');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result, $case);
            $this->assertSame(2, $this->watcher()->table('products')->where('id', $product)->value('version'), $case);
            $this->assertLeftNothing($refused);

            // The winner's price, the only new one, is in force, and the unit its own.
            $this->assertSame($prices, $this->watcher()->table('selling_price_revisions')->where('product_id', $product)->orderBy('id')->pluck('amount')->all(), $case);
            $this->assertSame(end($prices), $this->priceInForce($product), $case);
            $this->assertSame($firstFields['baseUnit'] ?? 'pcs', $this->watcher()->table('products')->where('id', $product)->value('base_unit'), $case);
        }
    }

    public function test_a_deactivation_against_a_price_change_in_both_orders(): void
    {
        foreach ([
            ['product.deactivate', 'product.update'],
            ['product.update', 'product.deactivate'],
        ] as [$firstOperation, $secondOperation]) {
            $product = TokoContoh::product('D-'.Str::random(8), ['categoryId' => $this->category]);
            $arguments = [
                'product.deactivate' => $this->command('product.deactivate', ['productId' => $product], ['productVersion' => 1]),
                'product.update' => $this->command('product.update', $this->editPayload($product, ['sellingPrice' => '8000']), ['productVersion' => 1]),
            ];
            $first = $this->start($arguments[$firstOperation], ['after_lock:6']);
            $second = $this->start($arguments[$secondOperation]);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_lock:6');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
            $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result);
            $this->assertLeftNothing($arguments[$secondOperation]);

            // The first alone is in force: the flag and the price.
            $priceChangedFirst = $firstOperation === 'product.update';
            $this->assertSame($priceChangedFirst, $this->watcher()->table('products')->where('id', $product)->value('active'));
            $this->assertSame($priceChangedFirst ? 8000 : 7500, $this->priceInForce($product));
        }
    }

    /**
     * A refused command left no identity, and so no fact of its own, audit event or
     * result, each of which names its identity (AC-015-01, TS-20).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function assertLeftNothing(array $arguments): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
    }

    private function priceInForce(int $product): mixed
    {
        return $this->watcher()->table('selling_price_revisions')
            ->where('id', $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id'))
            ->value('amount');
    }

    /**
     * Each operation with what it needs, a new record for each call.
     *
     * @return array<string, callable(): array{array<string, mixed>, array<string, mixed>}>
     */
    private function everyOperation(): array
    {
        return [
            'product.create' => fn (): array => [$this->productPayload('R-'.Str::random(8)), []],
            'product.update' => function (): array {
                $product = TokoContoh::product('R-'.Str::random(8), ['categoryId' => $this->category]);

                return [$this->editPayload($product, ['sellingPrice' => '8000']), ['productVersion' => 1]];
            },
            'product.deactivate' => function (): array {
                return [['productId' => TokoContoh::product('R-'.Str::random(8), ['categoryId' => $this->category])], ['productVersion' => 1]];
            },
            'product.reactivate' => function (): array {
                $product = TokoContoh::product('R-'.Str::random(8), ['categoryId' => $this->category]);
                $this->commit($this->command('product.deactivate', ['productId' => $product], ['productVersion' => 1]));

                return [['productId' => $product], ['productVersion' => 2]];
            },
            'category.create' => fn (): array => [['name' => 'Baru'], []],
            'category.rename' => fn (): array => [['categoryId' => TokoContoh::category(), 'name' => 'Nama Lain'], ['categoryVersion' => 1]],
            'category.deactivate' => fn (): array => [['categoryId' => TokoContoh::category()], ['categoryVersion' => 1]],
            'category.reactivate' => function (): array {
                $category = TokoContoh::category();
                $this->commit($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]));

                return [['categoryId' => $category], ['categoryVersion' => 2]];
            },
        ];
    }

    /**
     * Runs one command to its commit in a worker of its own.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function commit(array $arguments): void
    {
        $worker = $this->start($arguments);
        Worker::ready($worker);
        $worker->run();
        $this->assertSame('COMMITTED', ($worker->finish()->result ?? [])['outcome'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $sku, ?int $category = null): array
    {
        return [
            'sku' => $sku,
            'name' => 'Pulpen Hitam',
            'categoryId' => $category ?? $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
        ];
    }

    /**
     * RC-18's payload for the fields of TokoContoh::product(), with $fields
     * replacing some.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function editPayload(int $product, array $fields = []): array
    {
        return [
            'productId' => $product,
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
            ...$fields,
        ];
    }

    /**
     * RC-36 of the Owner, taking the Operations role from the account.
     *
     * @return array<string, mixed>
     */
    private function roleChange(int $id, string $email, string $name): array
    {
        $user = User::query()->findOrFail($id);

        return $this->command('user.update', ['userId' => $id, 'displayName' => $name, 'email' => $email, 'roles' => ['cashier']], ['userVersion' => $user->version]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, array $expected, ?string $intent = null, ?string $key = null): array
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
}
