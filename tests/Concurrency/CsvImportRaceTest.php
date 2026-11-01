<?php

namespace Tests\Concurrency;

use App\Actions\Catalog\ConfirmCsvImport;
use App\Domain\Catalog\Import\CsvImportFiles;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-31 (AC-019-03; TECHNICAL_RULES RACE-22, RACE-29; EXECUTION_CONTEXT section
 * 10, a command; TASK-019, L3), each with processes of their own connections under barrier
 * control:
 *
 * - RACE-01 and RACE-02: the confirmation sent twice, with the same key or another key of
 *   the same intent, runs once, and the second answers the original, its file gone;
 * - RACE-22: the confirmation against the creation of a product with the same SKU or
 *   barcode, in both orders: a conflict refuses the whole import with nothing created;
 *   and two confirmations of one file under two intents, of which one commits;
 * - RACE-29: a category's deactivation against the confirmation, in both orders.
 *
 * The workers and the test share a temporary area of this test, never the real disk
 * (TASK-010 finding F-8).
 */
final class CsvImportRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private int $category = 0;

    private string $root = '';

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bk-csv-race-'.Str::random(12);
        File::ensureDirectoryExists($this->root);
        config(['filesystems.disks.'.CsvImportFiles::DISK.'.root' => $this->root]);
        Storage::forgetDisk(CsvImportFiles::DISK);

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->category = TokoContoh::category('Alat Tulis');
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_race_01_and_race_02_the_confirmation_sent_twice_runs_once(): void
    {
        foreach (['same key', 'another key'] as $index => $case) {
            $import = $this->stored($this->csv('DUA-'.$index.'-A;Satu', 'DUA-'.$index.'-B;Dua'));
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $first = $this->start($this->confirmation($import, $intent, $key), ['after_identity']);
            $second = $this->start($this->confirmation($import, $intent, $case === 'same key' ? $key : (string) Str::uuid()));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $original = $first->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null], $second->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $case);
            $this->assertSame(2, $this->watcher()->table('products')->where('sku', 'like', 'DUA-'.$index.'-%')->count(), $case);
            $this->assertSame(1, $this->watcher()->table('csv_product_imports')->where('command_id', $original['commandId'] ?? null)->count(), $case);
            $this->assertSame([], Storage::disk(CsvImportFiles::DISK)->allFiles(), $case);
        }
    }

    public function test_race_22_a_creation_with_the_same_sku_first_refuses_the_whole_import(): void
    {
        // The creation holds its insert open on the index's entry; the confirmation has
        // checked, has written its first row, and waits on the index with its second.
        $import = $this->stored($this->csv('AMAN-1;Aman', 'race-1;Sama'));
        $refused = $this->confirmation($import);
        $creation = $this->start($this->command('product.create', $this->productPayload('RACE-1'), []), ['after_write:1']);
        $confirmation = $this->start($refused);
        Worker::ready($creation, $confirmation);

        $creation->run()->awaitBarrier('after_write:1');
        $confirmation->run()->awaitBlocked($this->watcher());
        $creation->release();

        $this->assertSame('COMMITTED', ($creation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'DUPLICATE_SKU'], $confirmation->finish()->result);
        $this->assertNothingOf($refused, ['AMAN-1']);
        $this->assertSame(1, $this->watcher()->table('products')->whereRaw('lower(sku) = lower(?)', ['RACE-1'])->count());
        $this->assertSame([], Storage::disk(CsvImportFiles::DISK)->allFiles());
    }

    public function test_race_22_the_confirmation_first_commits_and_the_creation_is_refused(): void
    {
        $import = $this->stored($this->csv('RACE-2;Sama', 'AMAN-2;Aman'));
        $confirmation = $this->start($this->confirmation($import), ['after_write:1']);
        $creation = $this->start($this->command('product.create', $this->productPayload('race-2'), []));
        Worker::ready($confirmation, $creation);

        $confirmation->run()->awaitBarrier('after_write:1');
        $creation->run()->awaitBlocked($this->watcher());
        $confirmation->release();

        $this->assertSame('COMMITTED', ($confirmation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'DUPLICATE_SKU'], $creation->finish()->result);
        $this->assertSame(2, $this->watcher()->table('products')->whereIn('sku', ['RACE-2', 'AMAN-2'])->count());
    }

    public function test_race_22_a_creation_with_the_same_barcode_first_refuses_the_whole_import(): void
    {
        // RC-17's writes: the product, the revision, the pointer, then the token.
        $import = $this->stored($this->csv('AMAN-3;Aman;Alat Tulis;;pcs;0;1000;;KODE-RACE'));
        $refused = $this->confirmation($import);
        $creation = $this->start($this->command('product.create', [...$this->productPayload('LAIN-3'), 'barcode' => ['token' => 'KODE-RACE']], []), ['after_write:4']);
        $confirmation = $this->start($refused);
        Worker::ready($creation, $confirmation);

        $creation->run()->awaitBarrier('after_write:4');
        $confirmation->run()->awaitBlocked($this->watcher());
        $creation->release();

        $this->assertSame('COMMITTED', ($creation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'DUPLICATE_BARCODE'], $confirmation->finish()->result);
        $this->assertNothingOf($refused, ['AMAN-3']);
    }

    public function test_race_22_two_confirmations_of_one_file_give_one_import(): void
    {
        $import = $this->stored($this->csv('SATU-4;Satu', 'DUA-4;Dua'));
        $refused = $this->confirmation($import);
        $first = $this->start($this->confirmation($import), ['after_write:1']);
        $second = $this->start($refused);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_write:1');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'DUPLICATE_SKU'], $second->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $refused['intent'])->count());
        $this->assertSame(1, $this->watcher()->table('csv_product_imports')->count());
        $this->assertSame(2, $this->watcher()->table('products')->whereIn('sku', ['SATU-4', 'DUA-4'])->count());
    }

    public function test_race_29_a_deactivation_against_the_confirmation_in_both_orders(): void
    {
        // The deactivation holds the category first: the confirmation waits on its share
        // lock and is refused, and nothing is written.
        $category = TokoContoh::category('Satu');
        $import = $this->stored($this->csv('DULU-5;Dulu;Satu'));
        $refused = $this->confirmation($import);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]), ['after_lock:6']);
        $confirmation = $this->start($refused);
        Worker::ready($deactivation, $confirmation);

        $deactivation->run()->awaitBarrier('after_lock:6');
        $confirmation->run()->awaitBlocked($this->watcher());
        $deactivation->release();

        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'CATEGORY_INACTIVE'], $confirmation->finish()->result);
        $this->assertNothingOf($refused, ['DULU-5']);
        $this->assertSame([], Storage::disk(CsvImportFiles::DISK)->allFiles());

        // The confirmation holds the category first: it commits, then the deactivation,
        // which changes no product.
        $category = TokoContoh::category('Dua');
        $import = $this->stored($this->csv('NANTI-5;Nanti;Dua'));
        $confirmation = $this->start($this->confirmation($import), ['after_lock:6']);
        $deactivation = $this->start($this->command('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]));
        Worker::ready($confirmation, $deactivation);

        $confirmation->run()->awaitBarrier('after_lock:6');
        $deactivation->run()->awaitBlocked($this->watcher());
        $confirmation->release();

        $this->assertSame('COMMITTED', ($confirmation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([$category, true], array_values((array) $this->watcher()->table('products')->where('sku', 'NANTI-5')->first(['category_id', 'active'])));
        $this->assertFalse($this->watcher()->table('categories')->where('id', $category)->value('active'));
    }

    /**
     * The file's rows, each `SKU;Nama` or `SKU;Nama;Kategori`, or a whole row.
     */
    private function csv(string ...$rows): string
    {
        $lines = ['SKU;Nama;Kategori;Merek;Satuan;Desimal;Harga jual;Stok minimum;Barcode'];

        foreach ($rows as $row) {
            $cells = substr_count($row, ';');
            $lines[] = match ($cells) {
                1 => $row.';Alat Tulis;;pcs;0;1000;;',
                2 => $row.';;pcs;0;1000;;',
                default => $row,
            };
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The Owner's preview of a file, as RR-01 leaves it in the temporary area.
     *
     * @return array{importId: string, fileSha256: string}
     */
    private function stored(string $content): array
    {
        return ['importId' => app(CsvImportFiles::class)->store($this->owner, $content), 'fileSha256' => hash('sha256', $content)];
    }

    /**
     * @param  array{importId: string, fileSha256: string}  $import
     * @return array<string, mixed>
     */
    private function confirmation(array $import, ?string $intent = null, ?string $key = null): array
    {
        return [...$this->command(ConfirmCsvImport::OPERATION, $import, [], $intent, $key), 'csvRoot' => $this->root];
    }

    /**
     * A refused confirmation left no identity, and nothing of its rows.
     *
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $skus
     */
    private function assertNothingOf(array $arguments, array $skus): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
        $this->assertSame(0, $this->watcher()->table('products')->whereIn('sku', $skus)->count());
        $this->assertSame(0, $this->watcher()->table('csv_product_imports')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $sku): array
    {
        return [
            'sku' => $sku,
            'name' => 'Pulpen Hitam',
            'categoryId' => $this->category,
            'brand' => null,
            'baseUnit' => 'pcs',
            'quantityPrecision' => 0,
            'sellingPrice' => '7500',
            'minimumStock' => '0',
        ];
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

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
