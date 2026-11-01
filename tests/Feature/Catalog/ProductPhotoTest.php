<?php

namespace Tests\Feature\Catalog;

use App\Database\CommandPoints;
use App\Domain\Media\MediaStore;
use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Media\TestImages;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-25, RC-26 and RD-07's photos through their routes (AC-018-01 to AC-018-04;
 * INTERACTION_CONTRACTS RC-25, RC-26, section 3.7; SECURITY_MODEL section 13; BR-CAT-44 to
 * BR-CAT-48; TR-TX-05; D-042; G-20; THR-09, THR-12; suites MEDIA01, SEC01, CMD01; TASK-018
 * scope items 1 and 2): an upload judged by its content, reduced to 2,048 pixels with its
 * thumbnail and without metadata; every refusal before any fact and any file; a replaced or
 * removed file kept and no longer referenced; a rolled-back command's file unreferenced and
 * never served; only CAP-09 changes a photo and every role with CAP-03 sees it, privately
 * cached, never by a storage key.
 */
final class ProductPhotoTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string SET = '/commands/product_photo.set';

    private const string REMOVE = '/commands/product_photo.remove';

    /** The tables that a refused command leaves as they were. */
    private const array FACTS = ['stored_files', 'products', 'command_identities', 'command_results', 'audit_events'];

    private int $product = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaStore::DISK);
        $this->withoutVite();
        TokoContoh::shop();
        $this->setUpCommands();
        $this->product = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category()]);
    }

    public function test_each_role_of_cap_09_sets_a_photo_judged_by_its_content_audited_with_its_thumbnail(): void
    {
        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $actor) {
            $this->as($person);
            $version = $this->versionOf($this->product);
            $previous = $this->photoOf($this->product);
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $bytes = TestImages::webp();

            // Named as a GIF: the content decides.
            $response = $this->postPhoto(UploadedFile::fake()->createWithContent('foto.gif', $bytes), $this->product, $version, $intent, $key)->assertStatus(201);
            $this->assertJsonChannel($response, 'commands.committed', $actor);
            $commandId = self::number($response->json('commandId'));
            $this->assertSame(['product', $this->product, '/products/'.$this->product], [$response->json('result.type'), $response->json('result.id'), $response->json('result.location')]);

            $photo = $this->photoOf($this->product);
            $this->assertIsInt($photo);
            $this->assertNotSame($previous, $photo);
            $this->assertSame($version + 1, $this->versionOf($this->product));

            // G-20: a product photo, whose thumbnail derives from it.
            $file = $this->fileOf($photo);
            $this->assertSame(['PRODUCT_PHOTO', 'image/webp', null, $this->people[$person]['id'], $commandId], [$file['purpose'], $file['media_type'], $file['derived_from_file_id'], $file['actor_user_id'], $file['command_id']]);
            $thumbnail = $this->thumbnailOf($photo);
            $this->assertSame(['PRODUCT_THUMBNAIL', 'image/webp', $photo, $commandId], [$thumbnail['purpose'], $thumbnail['media_type'], $thumbnail['derived_from_file_id'], $thumbnail['command_id']]);

            $event = (array) $this->watcher()->table('audit_events')->where('command_id', $commandId)->sole();
            $this->assertSame(['BUSINESS', 'PHOTO_CHANGE', $this->people[$person]['id'], 'product', $this->product, $this->product], [$event['category'], $event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id'], $event['product_id']]);
            $this->assertSame(['fileId' => $photo, 'previousFileId' => $previous, 'thumbnailFileId' => $thumbnail['id']], self::dataOf($event['change_data']));

            // A repeat answers the original and records nothing more.
            $before = $this->facts();
            $this->postPhoto(UploadedFile::fake()->createWithContent('foto.gif', $bytes), $this->product, $version, $intent, $key)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $commandId);
            $this->assertSame($before, $this->facts());
        }
    }

    public function test_a_large_photo_is_reduced_turned_upright_and_stripped_of_metadata(): void
    {
        // AC-018-01: 3,000 × 1,000 turned by its orientation 6 is 1,000 × 3,000.
        $this->as('Agus');
        $input = TestImages::jpegWithMetadata(3000, 1000, 6, 'Lokasi rahasia');

        $this->postPhoto(UploadedFile::fake()->createWithContent('foto.jpg', $input))->assertStatus(201);
        $photo = $this->photoOf($this->product);
        $this->assertIsInt($photo);
        $file = $this->fileOf($photo);
        $this->assertSame(['image/jpeg', 683, 2048], [$file['media_type'], $file['width'], $file['height']]);
        $thumbnail = $this->thumbnailOf($photo);
        $this->assertSame([64, 192], [$thumbnail['width'], $thumbnail['height']]);

        foreach ([$file, $thumbnail] as $record) {
            $key = $this->readText($record['storage_key']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $key);
            $stored = Storage::disk(MediaStore::DISK)->get($key);
            $this->assertIsString($stored);
            $this->assertSame(hash('sha256', $stored), $record['sha256']);
            $this->assertSame(strlen($stored), $record['byte_size']);
            $this->assertStringNotContainsString('Lokasi rahasia', $stored);
            $this->assertStringNotContainsString('Exif', $stored);
            $size = getimagesizefromstring($stored);
            $this->assertIsArray($size);
            $this->assertSame([$record['width'], $record['height']], [$size[0], $size[1]]);
        }
    }

    public function test_a_small_photo_is_never_enlarged_and_png_and_jpeg_keep_their_format(): void
    {
        $this->as('Agus');

        foreach (['image/png' => TestImages::png(), 'image/jpeg' => TestImages::jpeg()] as $type => $bytes) {
            $this->postPhoto(UploadedFile::fake()->createWithContent('foto.bin', $bytes), $this->product, $this->versionOf($this->product))->assertStatus(201);
            $photo = $this->photoOf($this->product);
            $this->assertIsInt($photo);
            $file = $this->fileOf($photo);
            $this->assertSame([$type, 40, 20], [$file['media_type'], $file['width'], $file['height']]);
            $thumbnail = $this->thumbnailOf($photo);
            $this->assertSame([$type, 40, 20], [$thumbnail['media_type'], $thumbnail['width'], $thumbnail['height']]);
        }
    }

    public function test_a_photo_at_its_limits_is_accepted_within_the_memory_of_a_request(): void
    {
        // 24 megapixels, under the default limit of 128 MiB that a request starts with.
        $this->as('Agus');
        $original = ini_get('memory_limit');
        $png = TestImages::flatPng(6000, 4000);

        try {
            ini_set('memory_limit', '128M');
            $this->postPhoto(UploadedFile::fake()->createWithContent('foto.png', $png))->assertStatus(201);
        } finally {
            ini_set('memory_limit', $original);
        }

        $photo = $this->photoOf($this->product);
        $this->assertIsInt($photo);
        $file = $this->fileOf($photo);
        $this->assertSame([2048, 1365], [$file['width'], $file['height']]);
        $thumbnail = $this->thumbnailOf($photo);
        $this->assertSame([192, 128], [$thumbnail['width'], $thumbnail['height']]);
    }

    public function test_every_refused_photo_is_refused_before_any_fact_and_any_stored_file(): void
    {
        // AC-018-01: each case of SECURITY_MODEL section 13.
        $this->as('Agus');
        $before = $this->facts();

        foreach ([
            'svg' => [UploadedFile::fake()->createWithContent('foto.svg', TestImages::svg()), 'ux.validation.upload.image_type', []],
            'svg named png' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::svg()), 'ux.validation.upload.image_type', []],
            'html' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::html()), 'ux.validation.upload.image_type', []],
            'gif' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::gif()), 'ux.validation.upload.image_type', []],
            'other' => [UploadedFile::fake()->createWithContent('foto.png', 'MZ'.str_repeat("\x00", 64)), 'ux.validation.upload.image_type', []],
            'animated png' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::animatedPng()), 'ux.validation.upload.animated', []],
            'animated webp' => [UploadedFile::fake()->createWithContent('foto.webp', TestImages::animatedWebp()), 'ux.validation.upload.animated', []],
            'too many pixels' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::pngClaiming(6000, 4001)), 'ux.validation.upload.too_many_pixels', ['jumlah' => 24]],
            'too large' => [UploadedFile::fake()->createWithContent('foto.png', TestImages::png().str_repeat("\x00", 8 * 1024 * 1024)), 'ux.validation.upload.too_large', ['ukuran' => '8 MB']],
            'truncated' => [UploadedFile::fake()->createWithContent('foto.png', substr(TestImages::png(), 0, 40)), 'ux.validation.upload.unreadable', []],
            'remote url' => ['https://contoh.test/foto.png', 'ux.validation.upload.unreadable', []],
        ] as $case => [$photo, $key, $params]) {
            $response = $this->postPhoto($photo)->assertStatus(422);
            $this->assertSame('VALIDATION_FAILED', $response->json('code'), $case);
            $this->assertSame($key, self::firstKey($response, 'payload.photo'), $case);
            $this->assertSame($params, self::firstParams($response, 'payload.photo'), $case);
        }

        // A JSON body has no file.
        $this->assertSame('ux.validation.upload.unreadable', self::firstKey($this->postJson(self::SET, $this->commandEnvelope('product_photo.set', ['productId' => $this->product], ['productVersion' => 1]))->assertStatus(422), 'payload.photo'));

        $this->assertSame($before, $this->facts());
        $this->assertSame([], Storage::disk(MediaStore::DISK)->allFiles(), 'No refused upload is stored.');
        $this->assertSame(1, $this->versionOf($this->product));
    }

    public function test_the_fields_of_a_multipart_envelope_are_read_only_as_canonical_whole_numbers(): void
    {
        // TR-VAL-06, review finding R-08: a form field stands for a whole number only in
        // its canonical form.
        $this->as('Agus');
        $before = $this->facts();

        foreach (['+1', '01', '1.0', '0x1', '1e0', str_repeat('9', 19), 'satu'] as $text) {
            $this->post(self::SET, $this->multipart(UploadedFile::fake()->createWithContent('a.png', TestImages::png()), ['payload' => ['productId' => $text]]), ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->post(self::SET, $this->multipart(UploadedFile::fake()->createWithContent('a.png', TestImages::png()), ['expected' => ['productVersion' => $text]]), ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_the_checksum_of_the_file_stands_for_it_in_the_fingerprint(): void
    {
        // RC-25; INTERACTION_CONTRACTS section 1.1, review finding R-08.
        $this->as('Agus');
        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $bytes = TestImages::png();
        $first = $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', $bytes), $this->product, 1, $intent, $key)->assertStatus(201);
        $before = $this->facts();

        // The same bytes under another name are the same request.
        $this->postPhoto(UploadedFile::fake()->createWithContent('lain.webp', $bytes), $this->product, 1, $intent, $key)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('commandId', $first->json('commandId'));

        // Other bytes under the same key are held, never run.
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png(41, 21)), $this->product, 1, $intent, $key)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $this->assertSame($before, $this->facts());
    }

    public function test_a_replaced_or_removed_photo_is_kept_and_no_page_shows_it(): void
    {
        // AC-018-02, D-042.
        $this->as('Agus');
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $first = $this->photoOf($this->product);
        $this->assertIsInt($first);
        $firstKey = $this->readText($this->fileOf($first)['storage_key']);
        $firstBytes = Storage::disk(MediaStore::DISK)->get($firstKey);

        $this->assertSame('/media/'.$first, $this->detailOf()['photo']);
        $this->assertSame('/media/'.$first.'?variant=thumb', $this->rowOf()['thumbnail']);

        // Replaced: a new file under a new key, and the old one untouched.
        $this->postPhoto(UploadedFile::fake()->createWithContent('b.jpg', TestImages::jpeg()), $this->product, 2)->assertStatus(201);
        $second = $this->photoOf($this->product);
        $this->assertIsInt($second);
        $this->assertNotSame($first, $second);
        $this->assertSame($firstBytes, Storage::disk(MediaStore::DISK)->get($firstKey));
        $this->assertSame('/media/'.$second, $this->detailOf()['photo']);
        $this->assertSame('/media/'.$second.'?variant=thumb', $this->rowOf()['thumbnail']);

        // No page names the replaced photo, and RD-07 still answers its identifier: its
        // file is kept and every role may see product photos (INTERACTION_CONTRACTS
        // section 3.7; evidence, reading 1).
        $this->get('/media/'.$first)->assertOk();

        // Removed: the placeholder, by no path at all; both files and records stay.
        $envelope = $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 3]);
        $response = $this->postJson(self::REMOVE, $envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'ops');
        $this->assertSame('/products/'.$this->product, $response->json('result.location'));
        $this->assertNull($this->photoOf($this->product));
        $this->assertSame(4, $this->versionOf($this->product));
        $this->assertNull($this->detailOf()['photo']);
        $this->assertNull($this->rowOf()['thumbnail']);
        $this->assertSame(4, $this->watcher()->table('stored_files')->count());
        $this->assertCount(4, Storage::disk(MediaStore::DISK)->allFiles());
        $this->assertSame($firstBytes, Storage::disk(MediaStore::DISK)->get($firstKey));

        $event = (array) $this->watcher()->table('audit_events')->where('command_id', $response->json('commandId'))->sole();
        $this->assertSame(['PHOTO_REMOVE', $this->product], [$event['action'], $event['product_id']]);
        $this->assertSame(['previousFileId' => $second], self::dataOf($event['change_data']));

        // A repeat answers the original; a product without a photo has nothing to remove.
        $this->postJson(self::REMOVE, $envelope)->assertStatus(200)->assertJsonPath('repeated', true);
        $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 4]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_a_stale_version_or_a_guessed_product_is_refused_and_leaves_the_product_as_it_was(): void
    {
        $this->as('Agus');
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $before = $this->facts();
        $photo = $this->photoOf($this->product);

        $this->postPhoto(UploadedFile::fake()->createWithContent('b.png', TestImages::png()), $this->product, 1)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 1]))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->postPhoto(UploadedFile::fake()->createWithContent('b.png', TestImages::png()), 999999, 1)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', ['productId' => 999999], ['productVersion' => 1]))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame($before, $this->facts());
        $this->assertSame($photo, $this->photoOf($this->product));

        // Fields that only look like numbers, an unknown field and a missing version.
        foreach ([
            [['productId' => (string) $this->product], ['productVersion' => 2]],
            [['productId' => $this->product, 'photoId' => 1], ['productVersion' => 2]],
            [['productId' => $this->product], []],
            [['productId' => $this->product], ['productVersion' => 'dua']],
        ] as [$payload, $expected]) {
            $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', $payload, $expected))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->assertSame($before, $this->facts());
    }

    public function test_a_cashier_cannot_change_a_photo_but_every_role_of_cap_03_sees_it_privately(): void
    {
        // AC-018-02 and AC-018-04, and RD-07's answers.
        $this->as('Agus');
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $photo = $this->photoOf($this->product);
        $this->assertIsInt($photo);
        $file = $this->fileOf($photo);
        $thumbnail = $this->thumbnailOf($photo);
        $photoKey = $this->readText($file['storage_key']);
        $photoBytes = Storage::disk(MediaStore::DISK)->get($photoKey);
        $thumbnailBytes = Storage::disk(MediaStore::DISK)->get($this->readText($thumbnail['storage_key']));
        $this->assertNotNull($photoBytes);
        $this->assertNotNull($thumbnailBytes);

        $this->as('Rina');
        $before = $this->facts();
        $this->postPhoto(UploadedFile::fake()->createWithContent('b.png', TestImages::png()), $this->product, 2)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 2]))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame($before, $this->facts());
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people['Rina']['id'])->count());

        foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);

            foreach (['/media/'.$photo => $photoBytes, '/media/'.$photo.'?variant=thumb' => $thumbnailBytes] as $path => $bytes) {
                $served = $this->get($path)->assertOk();
                $this->assertSame('image/png', $served->headers->get('Content-Type'), $path);
                $cache = (string) $served->headers->get('Cache-Control');

                foreach (['private', 'max-age=86400', 'immutable'] as $directive) {
                    $this->assertStringContainsString($directive, $cache, $person);
                }

                $this->assertSame($bytes, $served->getContent());
                $this->assertStringNotContainsString($photoKey, (string) json_encode($served->headers->all()));
            }
        }

        // A guessed identifier, a storage key, and a variant that does not apply.
        $this->as('Rina');
        $thumbnailId = self::number($thumbnail['id']);
        $this->getJson('/media/'.($thumbnailId + 1000))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/media/'.$photoKey)->assertStatus(404);
        $this->getJson('/media/'.$photo.'?variant=besar')->assertStatus(404);
        $this->getJson('/media/'.$thumbnailId.'?variant=thumb')->assertStatus(404);

        // No sign-in session, no image.
        $this->browse(null);
        $this->getJson('/media/'.$photo)->assertStatus(401);
        $this->getJson('/media/'.$photo.'?variant=thumb')->assertStatus(401);
    }

    public function test_a_file_of_a_rolled_back_command_stays_unreferenced_and_is_never_served(): void
    {
        // AC-018-03; TS-21: the two records, the product, the audit event and the result.
        $this->as('Agus');
        $disk = Storage::disk(MediaStore::DISK);

        for ($step = 1; $step <= 5; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->facts();
            $files = count($disk->allFiles());

            $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(500);

            $this->assertSame($before, $this->facts(), 'after write step '.$step);
            $this->assertNull($this->photoOf($this->product));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', 'product_photo.set')->orderByDesc('id')->value('outcome'));

            // Both files are there, and no record names them, so no request reaches them.
            $this->assertCount($files + 2, $disk->allFiles());
        }

        $this->assertSame(0, $this->watcher()->table('stored_files')->count());
        $this->app->instance(CommandPoints::class, new FailurePoints);

        foreach ($disk->allFiles() as $key) {
            $this->getJson('/media/'.$key)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        $this->getJson('/media/1')->assertStatus(404);

        // The same upload then commits.
        $this->app->instance(CommandPoints::class, new FailurePoints(6));
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(2, $this->watcher()->table('stored_files')->count());
    }

    public function test_a_failure_of_the_removal_after_every_write_step_leaves_the_photo_in_place(): void
    {
        $this->as('Agus');
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $photo = $this->photoOf($this->product);

        for ($step = 1; $step <= 3; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->facts();

            $this->postJson(self::REMOVE, $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 2]))->assertStatus(500);

            $this->assertSame($before, $this->facts(), 'after write step '.$step);
            $this->assertSame($photo, $this->photoOf($this->product));
            $this->assertSame(2, $this->versionOf($this->product));
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
    }

    public function test_a_status_check_recovers_the_original_result(): void
    {
        // EXECUTION_CONTEXT section 10; RC-50.
        $this->as('Agus');
        $envelope = $this->commandEnvelope('product_photo.remove', ['productId' => $this->product], ['productVersion' => 1]);
        $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'NOT_COMMITTED');

        $intent = (string) Str::uuid();
        $this->postPhoto(UploadedFile::fake()->createWithContent('a.png', TestImages::png()), $this->product, 1, $intent)->assertStatus(201);
        $this->check(['operation' => 'product_photo.set', 'sourceIntentId' => $intent, 'recoveryEpoch' => $this->epoch])->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.id', $this->product);
    }

    /**
     * RC-25 as multipart, its envelope as form fields: texts only (INTERACTION_CONTRACTS
     * section 1.1).
     *
     * @return TestResponse<Response>
     */
    private function postPhoto(UploadedFile|string $photo, ?int $product = null, int $version = 1, ?string $intent = null, ?string $key = null): TestResponse
    {
        return $this->post(self::SET, $this->multipart($photo, ['expected' => ['productVersion' => (string) $version], 'payload' => ['productId' => (string) ($product ?? $this->product)]], $intent, $key), ['Accept' => 'application/json']);
    }

    /**
     * The form fields of RC-25, with the expected version or the product of $texts.
     *
     * @param  array{expected?: array{productVersion: string}, payload?: array{productId: string}}  $texts
     * @return array<string, mixed>
     */
    private function multipart(UploadedFile|string $photo, array $texts, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => 'product_photo.set',
            'version' => '1',
            'sourceIntentId' => $intent ?? (string) Str::uuid(),
            'idempotencyKey' => $key ?? (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => $texts['expected'] ?? ['productVersion' => '1'],
            'payload' => [...($texts['payload'] ?? ['productId' => (string) $this->product]), 'photo' => $photo],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function commandEnvelope(string $operation, array $payload, array $expected): array
    {
        return [
            'operation' => $operation,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => $expected === [] ? (object) [] : $expected,
            'payload' => $payload,
        ];
    }

    private function photoOf(int $product): ?int
    {
        $photo = $this->watcher()->table('products')->where('id', $product)->value('photo_file_id');

        return is_int($photo) ? $photo : null;
    }

    private function versionOf(int $product): int
    {
        return self::number($this->watcher()->table('products')->where('id', $product)->value('version'));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function fileOf(int $id): array
    {
        return (array) $this->watcher()->table('stored_files')->selectRaw("id, storage_key, purpose, media_type, byte_size, width, height, encode(sha256, 'hex') as sha256, derived_from_file_id, actor_user_id, command_id")->where('id', $id)->sole();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function thumbnailOf(int $photo): array
    {
        return $this->fileOf(self::number($this->watcher()->table('stored_files')->where('derived_from_file_id', $photo)->value('id')));
    }

    /**
     * The product as RP-302 gives it.
     *
     * @return array<array-key, mixed>
     */
    private function detailOf(): array
    {
        $props = (array) ($this->inertiaPage($this->get('/products/'.$this->product)->assertOk())['props'] ?? []);

        return (array) ($props['product'] ?? []);
    }

    /**
     * The product as RD-01 finds it.
     *
     * @return array<array-key, mixed>
     */
    private function rowOf(): array
    {
        $rows = $this->getJson('/data/products/search?context=catalog&q=PEN-01')->assertOk()->json('rows');

        return is_array($rows) && is_array($rows[0] ?? null) ? $rows[0] : [];
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

    /**
     * An event's data with its keys sorted.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    /**
     * The first message key of a field of a refusal.
     *
     * @param  TestResponse<Response>  $response
     */
    private static function firstKey(TestResponse $response, string $path): ?string
    {
        $fields = $response->json('fields');
        $entries = is_array($fields) ? ($fields[$path] ?? null) : null;
        $first = is_array($entries) ? ($entries[0] ?? null) : null;
        $key = is_array($first) ? ($first['key'] ?? null) : null;

        return is_string($key) ? $key : null;
    }

    /**
     * The parameters of the first message of a field of a refusal.
     *
     * @param  TestResponse<Response>  $response
     */
    private static function firstParams(TestResponse $response, string $path): mixed
    {
        $fields = $response->json('fields');
        $entries = is_array($fields) ? ($fields[$path] ?? null) : null;
        $first = is_array($entries) ? ($entries[0] ?? null) : null;

        return is_array($first) ? ($first['params'] ?? null) : null;
    }
}
