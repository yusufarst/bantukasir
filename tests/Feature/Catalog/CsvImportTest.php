<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\ConfirmCsvImport;
use App\Actions\Commands\CommandContext;
use App\Database\CommandPoints;
use App\Domain\Catalog\Import\CsvDialect;
use App\Domain\Catalog\Import\CsvImportChecks;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Http\Requests\Catalog\ProductSearchRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RP-308, RR-01, RR-02 and RC-31 through their routes (AC-019-01 to AC-019-05;
 * DOCUMENT_CONTRACTS DC-04; TECHNICAL_RULES section 7.2 and TR-TIME-02; SECURITY_MODEL
 * section 13; BR-CAT-37 to BR-CAT-43; D-054; D-069 (h); suites IMPORT01, SEC01, CMD01;
 * TASK-019, L2 and L4): the dialect as stored; each refusal before any commit, at its
 * row, never creating a category; all rows or none; the checksum tying the confirmation
 * to the previewed file; a repeat answering the original; no stock or cost; a cashier
 * refused; the temporary file removed at every final outcome, never served and never
 * backed up; no text of a file reaching a process.
 */
final class CsvImportTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PREVIEW = '/products/import/preview';

    private const string CONFIRM = '/commands/csv_import.confirm';

    private const string HEADER = "SKU;Nama;Kategori;Merek;Satuan;Desimal;Harga jual;Stok minimum;Barcode\n";

    /** The tables that a refused import leaves as they were. */
    private const array FACTS = ['products', 'selling_price_revisions', 'barcode_tokens', 'barcode_mappings', 'barcode_active_mappings', 'categories', 'csv_product_imports', 'command_identities', 'command_results', 'audit_events'];

    private int $category = 0;

    private ?Connection $watcher = null;

    /** @var array<string, mixed> the envelope of the last confirmation */
    private array $lastEnvelope = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CsvImportFiles::DISK);
        $this->withoutVite();
        TokoContoh::shop();
        $this->setUpCommands();
        $this->category = TokoContoh::category('Alat Tulis');
    }

    public function test_each_role_of_cap_10_previews_and_confirms_all_rows_audited_with_the_checksum(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $before = $this->facts();
            $content = self::HEADER.$person."-1;Pulpen Hitam;Alat Tulis;Merek A;pcs;0;15.000;2;00{$this->people[$person]['id']}11\n".$person."-2;Beras;alat tulis;;kg;2;Rp1.250,00;\"1,5\";\n";
            $preview = $this->preview($content)->assertOk();

            $this->assertSame([hash('sha256', $content), 2, 2, 0], [$preview->json('fileSha256'), $preview->json('dataRowCount'), $preview->json('readyRows'), $preview->json('errorRows')]);
            $this->assertSame($before, $this->facts(), 'A preview writes no business fact.');
            $this->assertCount(1, $this->temporaryFiles());
            $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));

            $response = $this->confirm($preview)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $commandId = self::number($response->json('commandId'));
            $import = self::number($response->json('result.id'));
            $this->assertSame(['csv_import', '/products?import='.$import], [$response->json('result.type'), $response->json('result.location')]);

            // Both rows through the catalogue's services, active, with no photo.
            $rows = $this->watcher()->table('products')->where('created_command_id', $commandId)->orderBy('id')->get(['id', 'sku', 'name', 'category_id', 'brand', 'base_unit', 'quantity_precision', 'minimum_stock', 'active', 'photo_file_id', 'version', 'current_price_revision_id']);
            $this->assertCount(2, $rows);
            $first = (array) $rows[0];
            $second = (array) $rows[1];
            $this->assertSame([$person.'-1', 'Pulpen Hitam', $this->category, 'Merek A', 'pcs', 0, '2.000', true, null, 1], [$first['sku'], $first['name'], $first['category_id'], $first['brand'], $first['base_unit'], $first['quantity_precision'], $first['minimum_stock'], $first['active'], $first['photo_file_id'], $first['version']]);
            $this->assertSame([$person.'-2', 'Beras', $this->category, null, 'kg', 2, '1.500'], [$second['sku'], $second['name'], $second['category_id'], $second['brand'], $second['base_unit'], $second['quantity_precision'], $second['minimum_stock']]);

            // The prices in force, each the product's first revision, frozen with its actor.
            $this->assertSame([15000, $this->people[$person]['id'], $commandId], array_values((array) $this->watcher()->table('selling_price_revisions')->where('id', $first['current_price_revision_id'])->first(['amount', 'actor_user_id', 'command_id'])));
            $this->assertSame(1250, $this->watcher()->table('selling_price_revisions')->where('id', $second['current_price_revision_id'])->value('amount'));

            // The barcode as text, with its leading zeros and no symbology, active on its product.
            $token = (array) $this->watcher()->table('barcode_tokens')->where('token', '00'.$this->people[$person]['id'].'11')->sole();
            $this->assertSame(['MANUFACTURER', null], [$token['kind'], $token['symbology']]);
            $this->assertSame($first['id'], $this->watcher()->table('barcode_active_mappings')->where('token_id', $token['id'])->value('product_id'));

            // The record, and the audit with the result and the checksum (D-054).
            $record = (array) $this->watcher()->table('csv_product_imports')->selectRaw("id, command_id, encode(file_sha256, 'hex') as sha256, data_row_count, products_created, actor_user_id, actor_display_name")->where('id', $import)->sole();
            $this->assertSame([$commandId, hash('sha256', $content), 2, 2, $this->people[$person]['id']], [$record['command_id'], $record['sha256'], $record['data_row_count'], $record['products_created'], $record['actor_user_id']]);
            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BUSINESS', 'CSV_IMPORT', $this->people[$person]['id'], 'csv_import', $import], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id']]);
            $data = json_decode(is_string($event['change_data']) ? $event['change_data'] : '', true);
            $this->assertIsArray($data);
            ksort($data);
            $this->assertSame(['barcodesCreated' => 1, 'dataRowCount' => 2, 'fileSha256' => hash('sha256', $content), 'productIds' => [$first['id'], $second['id']], 'productsCreated' => 2, 'rowsByCategory' => [(string) $this->category => 2]], $data);

            // The temporary file is gone, and the receipt is settled.
            $this->assertSame([], $this->temporaryFiles());
            $this->assertSame('COMMITTED', $this->watcher()->table('command_request_receipts')->where('operation', 'csv_import.confirm')->orderByDesc('id')->value('outcome'));

            // A repeat answers the original and records nothing more, its file gone.
            $facts = $this->facts();
            $this->confirmEnvelope($this->lastEnvelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId)->assertJsonPath('result.location', '/products?import='.$import);
            $this->assertSame($facts, $this->facts());
        }
    }

    public function test_no_stock_or_cost_or_category_is_created_and_the_products_are_those_of_the_location(): void
    {
        // AC-019-03: a product of the import has nothing but what DC-04 lists. TASK-021 adds
        // the stock tables, whose families hold it from then on.
        $this->as('Agus');
        $categories = $this->watcher()->table('categories')->count();
        $other = TokoContoh::product('LAIN-01', ['categoryId' => $this->category]);
        $location = self::text($this->confirm($this->preview($this->csv('BARU-01;Baru Satu;Alat Tulis;;pcs;0;1000;;', 'BARU-02;Baru Dua;Alat Tulis;;pcs;0;2000;;'))->assertOk())->assertStatus(201)->json('result.location'));
        $import = (int) substr($location, strlen('/products?import='));

        $this->assertSame($categories, $this->watcher()->table('categories')->count());
        $this->assertSame(0, $this->watcher()->table('audit_events')->whereIn('action', ['PRODUCT_CREATE', 'PRICE_CHANGE'])->where('actor_user_id', $this->people['Agus']['id'])->count());

        // RP-301 keeps the filter, and RD-01 answers only the import's products.
        $page = $this->get($location)->assertOk();
        $this->assertPageChannel($page, 'ops');
        $props = (array) ($this->inertiaPage($page)['props'] ?? []);
        $filters = (array) ($props['filters'] ?? []);
        $this->assertSame($import, $filters['import'] ?? null);

        $found = $this->getJson('/data/products/search?context=catalog&import='.$import)->assertOk();
        $this->assertSame(['BARU-02', 'BARU-01'], array_column(self::rowsOf($found), 'sku'), 'By name, the order of a search.');
        $this->assertNotContains($other, array_column(self::rowsOf($found), 'id'));
        $this->assertSame([], self::rowsOf($this->getJson('/data/products/search?context=catalog&import=999999')->assertOk()));

        // A filter's own rules, on both routes, and `exact` takes none.
        foreach (['0', 'satu', '-1', '1.5', str_repeat('9', 20)] as $value) {
            $this->getJson('/data/products/search?context=catalog&import='.$value)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->getJson('/products?import='.$value)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->getJson('/data/products/search?context=catalog&mode=exact&q=BARU-01&import='.$import)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        // Only the catalogue's context takes it (review finding R-03), and a context that no
        // Task opened yet answers before it; the rule is read so that the first to open one
        // has it.
        $this->getJson('/data/products/search?context=till&import='.$import)->assertStatus(403);
        $this->as('Rina');
        $this->getJson('/data/products/search?context=till&import='.$import)->assertStatus(403);
        $fails = static function (string $context) use ($import): bool {
            $request = ProductSearchRequest::create('/data/products/search?context='.$context.'&import='.$import);
            $validator = Validator::make($request->query->all(), $request->rules());
            $validator->after($request->after());

            return $validator->fails();
        };
        $this->assertTrue($fails('till'));
        $this->assertFalse($fails('catalog'));
    }

    public function test_a_file_above_the_limit_of_php_is_the_refusal_of_its_size(): void
    {
        // Review finding R-01; SEC-UP-02: PHP takes the body, and the upload arrives failed.
        $this->as('Agus');
        $failed = new UploadedFile(__FILE__, 'besar.csv', null, UPLOAD_ERR_INI_SIZE, true);
        $response = $this->post(self::PREVIEW, ['file' => $failed], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame('CSV_INVALID', $response->json('code'));
        $this->assertSame([['key' => 'ux.validation.upload.too_large', 'params' => ['ukuran' => '2 MB']]], $response->json('fields.file'));

        // Any other failure cannot be read.
        $partial = new UploadedFile(__FILE__, 'sebagian.csv', null, UPLOAD_ERR_PARTIAL, true);
        $response = $this->post(self::PREVIEW, ['file' => $partial], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame('VALIDATION_FAILED', $response->json('code'));
        $this->assertSame('ux.validation.upload.unreadable', $response->json('fields.file.0.key'));
        $this->assertSame([], $this->temporaryFiles());
    }

    public function test_the_categories_are_locked_in_ascending_order_of_their_keys(): void
    {
        // Review finding R-07; TR section 7.1: whatever order the rows name them in.
        $this->as('Agus');
        $lower = TokoContoh::category('Kategori Satu');
        $higher = TokoContoh::category('Kategori Dua');
        $this->assertLessThan($higher, $lower);

        $checked = app(CsvImportChecks::class)->check(CsvDialect::parse($this->csv('A-1;Satu;Kategori Dua;;pcs;0;1000;;', 'A-2;Dua;Kategori Satu;;pcs;0;1000;;', 'A-3;Tiga;Kategori Dua;;pcs;0;1000;;')));
        $this->assertSame([$lower, $higher], $checked->categoryIds);
    }

    public function test_the_dialect_reaches_the_records_exactly(): void
    {
        // AC-019-01: a comma, the byte-order mark, CRLF, quotes, Indonesian numbers.
        $this->as('Agus');
        $content = "\xEF\xBB\xBFsku,NAMA,Kategori,Satuan,Desimal,Harga jual,Stok minimum,Barcode\r\n007,\"Pulpen, \"\"Hitam\"\"\",Alat Tulis,pcs,1,Rp 15.000,\"1.500,5\",00123456\r\n\r\n";
        $preview = $this->preview($content)->assertOk();
        $this->assertSame([hash('sha256', $content), 1, 0], [$preview->json('fileSha256'), $preview->json('readyRows'), $preview->json('errorRows')]);
        $this->confirm($preview)->assertStatus(201);

        $product = (array) $this->watcher()->table('products')->where('sku', '007')->sole();
        $this->assertSame(['Pulpen, "Hitam"', 'pcs', 1, '1500.500'], [$product['name'], $product['base_unit'], $product['quantity_precision'], $product['minimum_stock']]);
        $this->assertSame(15000, $this->watcher()->table('selling_price_revisions')->where('product_id', $product['id'])->value('amount'));
        $this->assertSame(1, $this->watcher()->table('barcode_tokens')->where('token', '00123456')->count());
    }

    public function test_a_whole_file_is_refused_before_anything_is_stored_or_written(): void
    {
        // AC-019-02, THR-09, with no file kept (D-054).
        $this->as('Agus');
        $before = $this->facts();
        $row = "PEN-01;Pulpen;Alat Tulis;;pcs;0;15000;;\n";

        foreach ([
            'a workbook named csv' => ["PK\x03\x04\x14\x00\x06\x00", 'ux.validation.upload.csv_type', []],
            'Latin-1' => [self::HEADER."PEN-01;Caf\xE9;Alat Tulis;;pcs;0;15000;;\n", 'ux.validation.upload.csv_type', []],
            'a control character' => [self::HEADER."PEN-01;Pul\x01pen;Alat Tulis;;pcs;0;15000;;\n", 'ux.validation.upload.csv_type', []],
            'an unknown column' => ["SKU;Nama;Kategori;Satuan;Desimal;Harga jual;Warna\n", 'catalog.csv_import.error.unknown_column', ['kolom' => 'Warna']],
            'a missing column' => ["SKU;Nama;Kategori;Satuan;Desimal\n", 'catalog.csv_import.error.missing_column', ['kolom' => 'Harga jual']],
            'no separator' => ["bukan csv\n", 'catalog.csv_import.error.missing_column', ['kolom' => 'SKU, Nama, Kategori, Satuan, Desimal, Harga jual']],
            'too many rows' => [self::HEADER.str_repeat($row, 5001), 'ux.validation.upload.csv_rows', []],
            'too large' => [self::HEADER.$row.str_repeat("\n", CsvDialect::MAX_BYTES), 'ux.validation.upload.too_large', ['ukuran' => '2 MB']],
        ] as $case => [$content, $key, $params]) {
            $response = $this->preview($content)->assertStatus(422);
            $this->assertSame(['CSV_INVALID', 'REJECTED', 'ux.state.csv_invalid'], [$response->json('code'), $response->json('outcome'), $response->json('messageKey')], $case);
            $this->assertSame([['key' => $key, 'params' => $params]], $response->json('fields.file'), $case);
        }

        $this->assertSame([], $this->temporaryFiles(), 'No refused file is stored.');
        $this->assertSame($before, $this->facts());

        // No file at all, or a text in its place.
        $this->post(self::PREVIEW, [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->post(self::PREVIEW, ['file' => 'SKU;Nama'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->post(self::PREVIEW, ['file' => UploadedFile::fake()->createWithContent('a.csv', self::HEADER), 'lain' => '1'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame([], $this->temporaryFiles());
    }

    public function test_every_row_error_is_shown_at_its_row_and_the_confirmation_refused_with_nothing_created(): void
    {
        // AC-019-02: the database's errors, and the four cases of D-069 (h).
        $this->as('Agus');
        TokoContoh::category('Dobel');
        TokoContoh::category('dobel');
        $inactive = TokoContoh::category('Lama');
        $this->ownerRuns('category.deactivate', ['categoryId' => $inactive], ['categoryVersion' => 1]);
        $mapping = TokoContoh::barcode(TokoContoh::product('ADA-01', ['categoryId' => $this->category]), 'SUDAH-AKTIF');
        TokoContoh::retireBarcode(TokoContoh::barcode(TokoContoh::product('ADA-02', ['categoryId' => $this->category]), 'SUDAH-PENSIUN'));
        $this->assertGreaterThan(0, $mapping);
        $this->as('Agus');
        $before = $this->facts();
        $categories = $this->watcher()->table('categories')->count();

        $preview = $this->preview($this->csv(
            'OK-01;Benar;Alat Tulis;;pcs;0;1000;;',                 // 2
            'K-01;Tanpa Kategori;Kategori Baru;;pcs;0;1000;;',      // 3: missing
            'K-02;Kategori Lama;LAMA;;pcs;0;1000;;',                // 4: only an inactive one
            'K-03;Kategori Dobel;DOBEL;;pcs;0;1000;;',              // 5: ambiguous
            'SAMA-01;Satu;Alat Tulis;;pcs;0;1000;;',                // 6: in the file
            'sama-01;Dua;Alat Tulis;;pcs;0;1000;;',                 // 7: in the file, in another case
            'ada-01;Tiga;Alat Tulis;;pcs;0;1000;;',                 // 8: among the products
            'B-01;Empat;Alat Tulis;;pcs;0;1000;;DOBEL-KODE',        // 9: in the file
            'B-02;Lima;Alat Tulis;;pcs;0;1000;;DOBEL-KODE',         // 10: in the file
            'B-03;Enam;Alat Tulis;;pcs;0;1000;;SUDAH-AKTIF',        // 11: active
            'B-04;Tujuh;Alat Tulis;;pcs;0;1000;;SUDAH-PENSIUN',     // 12: retired
            'B-05;Delapan;Alat Tulis;;pcs;0;1000;;BK-P-123',        // 13: reserved
            '=SUM(A1);Sembilan;Alat Tulis;;pcs;0;1000;;',           // 14: a formula
            'B-06;Sepuluh;Alat Tulis;;pcs;0;;;',                    // 15: required
            'B-07;Sebelas;Alat Tulis;;pcs;0;1.5;;',                 // 16: invalid
            'B-08;Dua Belas',                                       // 17: a wrong count
            'b-09;Bukan Kode;Alat Tulis;;pcs;0;1000;;dobel-kode',   // 18: a token is exact
        ))->assertOk();

        $errors = [];

        foreach (self::previewRows($preview) as $row) {
            $errors[self::number($row['row'] ?? null)] = array_column(is_array($row['errors'] ?? null) ? $row['errors'] : [], 'key');
        }

        $prefix = 'catalog.csv_import.error.';
        $this->assertSame([
            2 => [],
            3 => [$prefix.'category_missing'],
            4 => [$prefix.'category_missing'],
            5 => [$prefix.'category_ambiguous'],
            6 => [$prefix.'sku_duplicate'],
            7 => [$prefix.'sku_duplicate'],
            8 => [$prefix.'sku_taken'],
            9 => [$prefix.'barcode_duplicate'],
            10 => [$prefix.'barcode_duplicate'],
            11 => [$prefix.'barcode_taken'],
            12 => [$prefix.'barcode_taken'],
            13 => [$prefix.'barcode_reserved'],
            14 => [$prefix.'formula'],
            15 => [$prefix.'required'],
            16 => [$prefix.'invalid_value'],
            17 => [$prefix.'invalid_value'],
            18 => [],
        ], $errors);
        $this->assertSame([17, 2, 15], [$preview->json('dataRowCount'), $preview->json('readyRows'), $preview->json('errorRows')]);
        $this->assertJsonChannel($preview, 'products.import.preview', 'ops');
        $this->assertSame($before, $this->facts(), 'A preview writes no business fact.');
        $this->assertCount(1, $this->temporaryFiles());

        // Confirmed all the same: refused, nothing created, the file removed. Row 4's name
        // has only an inactive category.
        $refused = $this->confirm($preview)->assertStatus(422)->assertJsonPath('code', 'CATEGORY_INACTIVE')->assertJsonPath('params.kategori', 'LAMA');
        $this->assertArrayHasKey('3', (array) $refused->json('lines'));
        $this->assertSame($before, $this->facts());
        $this->assertSame([], $this->temporaryFiles());
        $this->assertSame($categories, $this->watcher()->table('categories')->count(), 'The import never creates a category.');

        // Without that row, the other errors are CSV_INVALID with every row's errors.
        $preview = $this->preview($this->csv('OK-01;Benar;Alat Tulis;;pcs;0;1000;;', 'K-01;Tanpa Kategori;Kategori Baru;;pcs;0;1000;;', 'ada-01;Tiga;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $refused = $this->confirm($preview)->assertStatus(422)->assertJsonPath('code', 'CSV_INVALID')->assertJsonPath('params.jumlah', 2);
        $this->assertSame($prefix.'category_missing', $refused->json('lines.3.errors.0.key'));
        $this->assertSame($prefix.'sku_taken', $refused->json('lines.4.errors.0.key'));
        $this->assertSame($before, $this->facts());
        $this->assertSame([], $this->temporaryFiles());
    }

    public function test_a_category_changed_between_preview_and_confirmation_refuses_the_import(): void
    {
        // D-069 (h), the fourth case; DC-04; RC-31.
        foreach ([
            'deactivated' => ['CATEGORY_INACTIVE', 'catalog.csv_import.error.category_missing'],
            'renamed' => ['CSV_INVALID', 'catalog.csv_import.error.category_missing'],
            'made ambiguous' => ['CSV_INVALID', 'catalog.csv_import.error.category_ambiguous'],
        ] as $case => [$code, $key]) {
            $name = 'Sementara '.$case;
            $category = TokoContoh::category($name);
            $this->as('Agus');
            $preview = $this->preview($this->csv('RUBAH-'.$category.';Pulpen;'.$name.';;pcs;0;1000;;'))->assertOk();
            $this->assertSame(1, $preview->json('readyRows'), $case);

            if ($case === 'deactivated') {
                $this->ownerRuns('category.deactivate', ['categoryId' => $category], ['categoryVersion' => 1]);
            } elseif ($case === 'renamed') {
                $this->ownerRuns('category.rename', ['categoryId' => $category, 'name' => 'Nama Lain '.$category], ['categoryVersion' => 1]);
            } else {
                TokoContoh::category($name);
            }

            $this->as('Agus');
            $before = $this->facts();
            $refused = $this->confirm($preview)->assertStatus(422);
            $this->assertSame($code, $refused->json('code'), $case);
            $this->assertSame($key, $refused->json('lines.2.errors.0.key'), $case);
            $this->assertSame($before, $this->facts(), $case);
            $this->assertSame([], $this->temporaryFiles(), $case);
        }
    }

    public function test_a_code_taken_between_preview_and_confirmation_refuses_the_whole_import(): void
    {
        // RACE-22 without a race: the second pass finds the new record.
        $this->as('Agus');

        foreach ([
            'sku' => [fn () => TokoContoh::product('RACE-1', ['categoryId' => $this->category]), 'DUPLICATE_SKU', 'catalog.csv_import.error.sku_taken', 3],
            'barcode' => [fn () => TokoContoh::barcode(TokoContoh::product('RACE-3', ['categoryId' => $this->category]), 'KODE-RACE'), 'DUPLICATE_BARCODE', 'catalog.csv_import.error.barcode_taken', 3],
        ] as $case => [$take, $code, $key, $row]) {
            $preview = $this->preview($this->csv('AMAN-'.$case.';Aman;Alat Tulis;;pcs;0;1000;;', ($case === 'sku' ? 'race-1' : 'AMAN2-'.$case).';Kedua;Alat Tulis;;pcs;0;1000;;'.($case === 'barcode' ? 'KODE-RACE' : '')))->assertOk();
            $this->assertSame(2, $preview->json('readyRows'), $case);
            $take();
            $before = $this->facts();
            $refused = $this->confirm($preview)->assertStatus(422);
            $this->assertSame($code, $refused->json('code'), $case);
            $this->assertSame($key, $refused->json('lines.'.$row.'.errors.0.key'), $case);
            $this->assertSame($before, $this->facts(), $case);
            $this->assertSame(0, $this->watcher()->table('products')->where('sku', 'AMAN-'.$case)->count(), $case);
            $this->assertSame([], $this->temporaryFiles(), $case);
        }
    }

    public function test_the_checksum_ties_the_confirmation_to_the_previewed_file(): void
    {
        // AC-019-03; SEC-UP-04.
        $this->as('Agus');
        $content = $this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;');
        $preview = $this->preview($content)->assertOk();
        $importId = self::text($preview->json('importId'));
        $before = $this->facts();

        // Another checksum: refused, and the import is over.
        $this->assertFileChanged($this->postConfirm($importId, hash('sha256', 'lain')));
        $this->assertSame($before, $this->facts());
        $this->assertSame([], $this->temporaryFiles());

        // A file replaced behind the preview's back.
        $preview = $this->preview($content)->assertOk();
        $importId = self::text($preview->json('importId'));
        $path = $this->temporaryFile();
        Storage::disk(CsvImportFiles::DISK)->put($path, $this->csv('LAIN-01;Lain;Alat Tulis;;pcs;0;1000;;'));
        $this->assertFileChanged($this->postConfirm($importId, hash('sha256', $content)));
        $this->assertSame($before, $this->facts());
        $this->assertSame(0, $this->watcher()->table('products')->where('sku', 'LAIN-01')->count());

        // An identifier that no preview gave.
        $this->assertFileChanged($this->postConfirm(str_repeat('a', 64), hash('sha256', $content)));

        // A file past its 24 hours counts as gone (ARC-43).
        $preview = $this->preview($content)->assertOk();
        $path = $this->temporaryFile();
        touch(Storage::disk(CsvImportFiles::DISK)->path($path), time() - CsvImportFiles::MAX_AGE_SECONDS);
        $this->assertFileChanged($this->confirm($preview));
        $this->assertSame($before, $this->facts());
    }

    public function test_an_import_belongs_to_the_actor_who_previewed_it(): void
    {
        $this->as('Hendra');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $importId = self::text($preview->json('importId'));
        $sha256 = self::text($preview->json('fileSha256'));

        $this->as('Agus');
        $this->assertFileChanged($this->postConfirm($importId, $sha256));
        $this->post('/products/import/'.$importId.'/cancel', [], ['Accept' => 'application/json'])->assertStatus(204);
        $this->assertCount(1, $this->temporaryFiles(), 'Another actor reaches no file.');
        $this->assertSame(0, $this->watcher()->table('products')->where('sku', 'PEN-01')->count());

        $this->as('Hendra');
        $this->postConfirm($importId, $sha256)->assertStatus(201);
    }

    public function test_a_cancel_removes_the_file_and_answers_the_same_for_a_missing_one(): void
    {
        // AC-019-04; RR-02.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $importId = self::text($preview->json('importId'));
        $before = $this->facts();
        $this->assertCount(1, $this->temporaryFiles());

        $this->postJson('/products/import/'.$importId.'/cancel')->assertStatus(204)->assertNoContent();
        $this->assertSame([], $this->temporaryFiles());
        $this->assertSame($before, $this->facts());

        // Again, an identifier that does not exist, and ones in no form.
        foreach ([$importId, str_repeat('b', 64), 'bukan', strtoupper($importId)] as $id) {
            $this->postJson('/products/import/'.$id.'/cancel')->assertStatus(204);
        }

        $this->assertFileChanged($this->confirm($preview));

        // It takes no input.
        $this->postJson('/products/import/'.$importId.'/cancel', ['a' => 1])->assertStatus(422);
    }

    public function test_the_file_is_never_served_and_never_in_the_file_volume(): void
    {
        // AC-019-04; SECURITY_MODEL section 13.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $importId = self::text($preview->json('importId'));

        foreach (['/products/import/'.$importId, '/products/import/'.$importId.'/cancel', '/media/'.$importId, '/storage/'.$this->temporaryFile()] as $path) {
            $this->assertNotSame(200, $this->getJson($path)->getStatusCode(), $path);
        }

        $temporary = config()->string('filesystems.disks.'.CsvImportFiles::DISK.'.root');
        $volume = config()->string('filesystems.disks.media.root');
        $this->assertFalse(str_starts_with($temporary, $volume), 'The temporary area is outside the file volume, so it is never backed up.');
        $this->assertStringNotContainsString($importId, (string) json_encode($this->watcher()->table('command_request_receipts')->get()));
    }

    public function test_a_file_name_or_a_cell_with_shell_metacharacters_never_reaches_a_process(): void
    {
        // AC-019-05; THR-10; SEC-UP-01.
        Process::fake();
        $this->as('Agus');
        $name = '$(touch /tmp/bk-a); `id` && rm -rf / | cat > x.csv';
        $texts = ['$(touch /tmp/bk-b)', '`id`', '| rm -rf /', '&& shutdown', '> /etc/passwd', "'; DROP TABLE products; --"];
        $rows = [];

        foreach ($texts as $index => $text) {
            $rows[] = 'META-'.$index.';"'.$text.'";Alat Tulis;;pcs;0;1000;;';
        }

        $preview = $this->preview($this->csv(...$rows), $name)->assertOk();
        $this->assertSame(count($texts), $preview->json('readyRows'));
        $path = $this->temporaryFile();
        $this->assertMatchesRegularExpression('/^[0-9]+\/[0-9a-f]{64}$/D', $path, 'The stored path holds nothing of the upload.');
        $this->confirm($preview)->assertStatus(201);

        Process::assertNothingRan();

        foreach ($texts as $index => $text) {
            $this->assertSame($text, $this->watcher()->table('products')->where('sku', 'META-'.$index)->value('name'));
        }
    }

    public function test_a_failure_after_every_write_step_leaves_nothing_and_keeps_the_file_for_a_repeat(): void
    {
        // AC-019-03; TS-21. Row 1: the product, the revision and the pointer. Row 2 too, then
        // the token, the mapping and the active row. Then the record, the audit, the result.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;', 'PEN-02;Pensil;Alat Tulis;;pcs;0;1000;;KODE-1'))->assertOk();
        $importId = self::text($preview->json('importId'));
        $sha256 = self::text($preview->json('fileSha256'));
        $before = $this->facts();

        for ($step = 1; $step <= 12; $step++) {
            $points = new FailurePoints($step);
            $this->app->instance(CommandPoints::class, $points);

            $this->postConfirm($importId, $sha256)->assertStatus(500);

            $this->assertContains('after_write:'.$step, $points->passed);
            $this->assertSame($before, $this->facts(), 'after write step '.$step);
            $this->assertCount(1, $this->temporaryFiles(), 'An uncertain outcome keeps the file.');
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', 'csv_import.confirm')->orderByDesc('id')->value('outcome'));
        }

        // Step 12 is the last: the same file then commits.
        $points = new FailurePoints(13);
        $this->app->instance(CommandPoints::class, $points);
        $this->postConfirm($importId, $sha256)->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:6'], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock'))));
        $this->assertNotContains('after_write:13', $points->passed);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(2, $this->watcher()->table('products')->whereIn('sku', ['PEN-01', 'PEN-02'])->count());
    }

    public function test_exceeding_the_budget_rejects_the_whole_import_with_nothing_created(): void
    {
        // TR-TIME-02: a bounded heavy command takes no budget of its own, so the one in force
        // when it begins bounds it; past it the server ends the session.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $before = $this->facts();
        $this->app->instance(CommandPoints::class, new class implements CommandPoints
        {
            public function afterIdentity(): void {}

            public function afterLock(string $step): void
            {
                if ($step === '6') {
                    DB::select('select pg_catalog.pg_sleep(1.5)');
                }
            }

            public function afterWrite(int $step): void {}

            public function beforeCommit(): void {}

            public function afterOwnWrite(string $write): void {}
        });
        $envelope = new Envelope(ConfirmCsvImport::OPERATION, 1, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, [], ['importId' => self::text($preview->json('importId')), 'fileSha256' => self::text($preview->json('fileSha256'))]);
        DB::statement("set transaction_timeout = '1s'");

        try {
            app(ConfirmCsvImport::class)->execute(new CommandContext($this->people['Agus']['id'], $envelope, CarbonImmutable::now('UTC')));
            $this->fail('The budget ends the command.');
        } catch (CommandRefused $refusal) {
            $this->assertSame('SERVICE_BUSY', $refusal->error->value);
        }

        $this->assertSame($before, $this->facts());
        $this->assertCount(1, $this->temporaryFiles(), 'An uncertain outcome keeps the file.');
    }

    public function test_the_most_rows_commit_within_the_budget(): void
    {
        // TR-TIME-02 at its limit, each row with a barcode, the most writes that a row makes.
        // The budget's measure on the benchmark's server is TASK-048's.
        $this->as('Agus');
        $rows = [];

        for ($row = 1; $row <= 5000; $row++) {
            $rows[] = 'M-'.$row.';Produk '.$row.';Alat Tulis;;pcs;0;'.$row.';;K-'.$row;
        }

        $this->confirm($this->preview($this->csv(...$rows))->assertOk()->assertJsonPath('readyRows', 5000))->assertStatus(201);

        $this->assertSame(5000, $this->watcher()->table('products')->where('sku', 'like', 'M-%')->count());
        $this->assertSame(5000, $this->watcher()->table('barcode_active_mappings')->count());
        $this->assertSame(5000, $this->watcher()->table('csv_product_imports')->value('products_created'));
    }

    public function test_a_cashier_is_refused_and_the_other_roles_open_the_page(): void
    {
        // AC-019-03; BR-CAT-41; CAP-10; the DTO allowlist of every channel.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $importId = self::text($preview->json('importId'));
        $sha256 = self::text($preview->json('fileSha256'));

        $this->as('Rina');
        $before = $this->facts();
        $this->get('/products/import')->assertStatus(403);
        $this->post(self::PREVIEW, ['file' => UploadedFile::fake()->createWithContent('a.csv', self::HEADER)], ['Accept' => 'application/json'])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->postJson('/products/import/'.$importId.'/cancel')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->postConfirm($importId, $sha256)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame($before, $this->facts());
        $this->assertCount(1, $this->temporaryFiles());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people['Rina']['id'])->count());

        // A guest.
        $this->browse(null);
        $this->getJson('/products/import')->assertStatus(401);
        $this->postJson('/products/import/'.$importId.'/cancel')->assertStatus(401);

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $page = $this->get('/products/import')->assertOk();
            $this->assertPageChannel($page, $actor);
            $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
            $props = (array) ($this->inertiaPage($page)['props'] ?? []);
            $this->assertSame(true, $props['canManageCategories'] ?? null);
            $this->assertSame(
                [['header' => 'SKU', 'required' => true], ['header' => 'Nama', 'required' => true], ['header' => 'Kategori', 'required' => true], ['header' => 'Merek', 'required' => false], ['header' => 'Satuan', 'required' => true], ['header' => 'Desimal', 'required' => true], ['header' => 'Harga jual', 'required' => true], ['header' => 'Stok minimum', 'required' => false], ['header' => 'Barcode', 'required' => false]],
                $props['columns'] ?? null,
            );
        }
    }

    public function test_the_envelope_and_its_refusals(): void
    {
        // EXECUTION_CONTEXT section 10, a command: RT-07; RACE-01's key; its receipt (G-25).
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $importId = self::text($preview->json('importId'));
        $sha256 = self::text($preview->json('fileSha256'));
        $before = $this->facts();

        foreach ([
            'an unknown field' => ['importId' => $importId, 'fileSha256' => $sha256, 'lain' => 1],
            'no checksum' => ['importId' => $importId],
            'no identifier' => ['fileSha256' => $sha256],
            'upper case' => ['importId' => strtoupper($importId), 'fileSha256' => $sha256],
            'too short' => ['importId' => substr($importId, 1), 'fileSha256' => $sha256],
            'a number' => ['importId' => 1, 'fileSha256' => $sha256],
        ] as $case => $payload) {
            $this->postJson(self::CONFIRM, $this->envelopeOf($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($before, $this->facts(), $case);
            $this->assertCount(1, $this->temporaryFiles(), $case);
        }

        $this->assertSame('REJECTED', $this->watcher()->table('command_request_receipts')->where('operation', 'csv_import.confirm')->orderByDesc('id')->value('outcome'));

        // The same key with another file is held, never run.
        $envelope = $this->envelopeOf(['importId' => $importId, 'fileSha256' => $sha256]);
        $this->confirmEnvelope($envelope)->assertStatus(201);
        $facts = $this->facts();
        $this->confirmEnvelope([...$envelope, 'payload' => ['importId' => $importId, 'fileSha256' => hash('sha256', 'lain')]])->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame($facts, $this->facts());
    }

    public function test_a_status_check_recovers_the_original_result(): void
    {
        // EXECUTION_CONTEXT section 10; RC-50.
        $this->as('Agus');
        $preview = $this->preview($this->csv('PEN-01;Pulpen;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $envelope = $this->envelopeOf(['importId' => self::text($preview->json('importId')), 'fileSha256' => self::text($preview->json('fileSha256'))]);

        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');

        $preview = $this->preview($this->csv('PEN-02;Pensil;Alat Tulis;;pcs;0;1000;;'))->assertOk();
        $envelope = $this->envelopeOf(['importId' => self::text($preview->json('importId')), 'fileSha256' => self::text($preview->json('fileSha256'))]);
        $location = $this->confirmEnvelope($envelope)->assertStatus(201)->json('result.location');
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', $location);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function envelopeOf(array $payload): array
    {
        return [
            'operation' => ConfirmCsvImport::OPERATION,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => (object) [],
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function confirmEnvelope(array $envelope): TestResponse
    {
        $this->lastEnvelope = $envelope;

        return $this->postJson(self::CONFIRM, $envelope);
    }

    /**
     * @return TestResponse<Response>
     */
    private function postConfirm(string $importId, string $sha256): TestResponse
    {
        return $this->confirmEnvelope($this->envelopeOf(['importId' => $importId, 'fileSha256' => $sha256]));
    }

    /**
     * RC-31 of what a preview answered.
     *
     * @param  TestResponse<Response>  $preview
     * @return TestResponse<Response>
     */
    private function confirm(TestResponse $preview): TestResponse
    {
        return $this->postConfirm(self::text($preview->json('importId')), self::text($preview->json('fileSha256')));
    }

    /**
     * @return TestResponse<Response>
     */
    private function preview(string $content, string $name = 'produk.csv'): TestResponse
    {
        return $this->post(self::PREVIEW, ['file' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json']);
    }

    private function csv(string ...$rows): string
    {
        return self::HEADER.implode("\n", $rows)."\n";
    }

    /**
     * A command that the Owner sends through its route; the next requests are his.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    private function ownerRuns(string $operation, array $payload, array $expected): void
    {
        $this->as('Hendra');
        $this->postJson('/commands/'.$operation, [
            'operation' => $operation,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => $expected,
            'payload' => $payload,
        ])->assertStatus(201);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertFileChanged(TestResponse $response): void
    {
        $response->assertStatus(422)->assertJsonPath('code', 'CSV_INVALID');
        $fields = $response->json('fields');
        $this->assertIsArray($fields);
        $this->assertSame([['key' => 'catalog.csv_import.error.file_changed', 'params' => []]], $fields['payload.fileSha256'] ?? null);
    }

    /**
     * The rows of a preview, in the order of the file.
     *
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    private static function previewRows(TestResponse $response): array
    {
        return self::rowsOf($response);
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(): array
    {
        return array_values(Storage::disk(CsvImportFiles::DISK)->allFiles());
    }

    /**
     * The path of the one temporary file there is.
     */
    private function temporaryFile(): string
    {
        $files = $this->temporaryFiles();
        $this->assertCount(1, $files);

        return $files[0];
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }
}
