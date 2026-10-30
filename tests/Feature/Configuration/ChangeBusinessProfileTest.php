<?php

namespace Tests\Feature\Configuration;

use App\Domain\Media\MediaStore;
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

/**
 * RC-32 `business_profile.change`, the logo uploads and RD-07 (AC-010-02,
 * AC-010-03, AC-010-04; INTERACTION_CONTRACTS RC-32, section 3.7; SECURITY_MODEL
 * section 13, SEC-UP-01 to SEC-UP-06, SEC-CH-06; BR-CFG-01 to BR-CFG-04;
 * TECHNICAL_RULES TR-TX-05; THR-09, THR-10, THR-12; suites MEDIA01 and SEC01;
 * TASK-010 scope items 2 and 4), as L4 tests through the routes:
 *
 * - the Owner with recent authentication creates an immutable revision and
 *   moves the pointer, audited with the names of the changed fields; a
 *   repeat returns the original result; every other role is refused; without
 *   recent authentication nothing runs; a stale expected revision is
 *   STALE_VERSION;
 * - every refusal case of a logo is refused before any fact and before any
 *   file is stored; an accepted logo is judged by its content, turned
 *   upright, re-encoded without metadata, stored under a new random key with
 *   its SHA-256, and never overwritten or deleted — kept, replaced or removed
 *   from the profile only;
 * - RD-07 serves a logo to every signed-in role, privately cached, never by a
 *   path, and NOT_FOUND for a guessed or unknown identifier.
 */
final class ChangeBusinessProfileTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string PATH = '/commands/business_profile.change';

    /** The tables that a refused change must leave as they were. */
    private const array FACTS = ['stored_files', 'business_profile_revisions', 'business_profile_current', 'command_identities', 'command_results', 'audit_events'];

    private int $first = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaStore::DISK);
        $this->first = TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_the_owner_with_recent_authentication_creates_a_revision_and_moves_the_pointer_audited(): void
    {
        $this->asOwner();
        $envelope = $this->change(['shopName' => 'Toko Contoh Baru', 'address' => 'Jl. Contoh No. 1', 'accentToken' => 'teal']);

        $response = $this->postJson(self::PATH, $envelope)->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $revision = self::idOf($response);
        $this->assertSame(['business_profile_revision', '/settings/business-profile'], [$response->json('result.type'), $response->json('result.location')]);

        $observer = $this->observer();
        $row = (array) $observer->table('business_profile_revisions')->where('id', $revision)->sole();
        $this->assertSame(['Toko Contoh Baru', 'Jl. Contoh No. 1', 'teal', null, $this->people['Hendra']['id'], 'Hendra'], [$row['shop_name'], $row['address'], $row['accent_token'], $row['logo_file_id'], $row['actor_user_id'], $row['actor_display_name']]);
        $this->assertSame($response->json('commandId'), $row['command_id']);
        $this->assertSame($revision, $observer->table('business_profile_current')->where('id', 1)->value('revision_id'));

        // The first revision is unchanged (BR-CFG-02).
        $this->assertSame('Toko Contoh', $observer->table('business_profile_revisions')->where('id', $this->first)->value('shop_name'));

        $event = (array) $observer->table('audit_events')->where('action', 'PROFILE_CHANGE')->whereNotNull('command_id')->sole();
        $this->assertSame(['SECURITY', 'USER', $this->people['Hendra']['id'], $response->json('commandId')], [$event['category'], $event['actor_kind'], $event['actor_user_id'], $event['command_id']]);
        $change = json_decode($this->readText($event['change_data']), true);
        $this->assertIsArray($change);
        $this->assertSame([$revision, $this->first, ['shopName', 'address', 'accentToken']], [$change['revisionId'] ?? null, $change['previousRevisionId'] ?? null, $change['changed'] ?? null]);
        $this->assertCount(3, $change);

        // A repeat of the same request returns the original result.
        $this->postJson(self::PATH, $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $revision);
        $this->assertSame(2, $observer->table('business_profile_revisions')->count());
    }

    public function test_it_needs_recent_authentication_and_every_other_role_is_refused(): void
    {
        $before = $this->facts();

        // The Owner without a recent confirmation: 423, nothing run, no receipt.
        $this->as('Hendra');
        $this->postJson(self::PATH, $this->change())->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->postJson(self::PATH, $this->change())->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->assertSame($before, $this->facts());
        $this->assertSame(0, $this->observer()->table('command_request_receipts')->where('operation', 'business_profile.change')->count());
    }

    public function test_a_stale_expected_revision_is_refused_stale_version(): void
    {
        $this->asOwner();
        $this->postJson(self::PATH, $this->change(['shopName' => 'Toko Satu']))->assertStatus(201);
        $before = $this->facts();

        // The page still shows the first revision.
        $this->postJson(self::PATH, $this->change(['shopName' => 'Toko Dua']))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->postJson(self::PATH, $this->change(['shopName' => 'Toko Dua'], revision: 999999))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');

        $this->assertSame($before, $this->facts());
    }

    public function test_the_fields_are_validated_with_their_keys(): void
    {
        $this->asOwner();
        $before = $this->facts();
        $cases = [
            'payload.shopName' => [['shopName' => null], 'ux.validation.required'],
            'payload.address' => [['address' => str_repeat('a', 501)], 'ux.validation.text_max'],
            'payload.email' => [['email' => 'bukan-email'], 'ux.validation.email'],
            'payload.accentToken' => [['accentToken' => 'red'], 'ux.validation.required'],
        ];

        foreach ($cases as $path => [$payload, $key]) {
            $response = $this->postJson(self::PATH, $this->change($payload))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($key, self::firstKey($response, $path), $path);
        }

        // An unknown field is refused, not trimmed.
        $this->postJson(self::PATH, $this->change(['color' => '#ff0000']))->assertStatus(422);
        $this->assertSame($before, $this->facts());
    }

    public function test_an_accepted_logo_is_re_encoded_without_metadata_and_stored_under_a_new_random_key(): void
    {
        $this->asOwner();
        $input = TestImages::jpegWithMetadata(40, 20, 6, 'Lokasi rahasia');

        $response = $this->postLogo(UploadedFile::fake()->createWithContent('foto.gif', $input))->assertStatus(201);
        $revision = self::idOf($response);
        $observer = $this->observer();
        $logo = $observer->table('business_profile_revisions')->where('id', $revision)->value('logo_file_id');
        $this->assertIsInt($logo);
        $file = (array) $observer->table('stored_files')->selectRaw("id, storage_key, purpose, media_type, byte_size, width, height, encode(sha256, 'hex') as sha256, derived_from_file_id, command_id")->where('id', $logo)->sole();

        // Judged by its content, not its name: a JPEG, turned upright by its
        // orientation 6, under a random key of 256 bits.
        $this->assertSame(['LOGO', 'image/jpeg', 20, 40, null, $response->json('commandId')], [$file['purpose'], $file['media_type'], $file['width'], $file['height'], $file['derived_from_file_id'], $file['command_id']]);
        $key = $this->readText($file['storage_key']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $key);
        $this->assertStringNotContainsString('foto', $key);

        $stored = Storage::disk(MediaStore::DISK)->get($key);
        $this->assertIsString($stored);
        $this->assertSame(hash('sha256', $stored), $file['sha256']);
        $this->assertSame(strlen($stored), $file['byte_size']);
        $this->assertNotSame($input, $stored);
        $this->assertStringNotContainsString('Lokasi rahasia', $stored, 'No metadata survives the re-encoding.');
        $this->assertStringNotContainsString('Exif', $stored);
        $size = getimagesizefromstring($stored);
        $this->assertIsArray($size);
        $this->assertSame([20, 40], [$size[0], $size[1]]);

        $event = json_decode($this->readText($observer->table('audit_events')->where('command_id', $response->json('commandId'))->value('change_data')), true);
        $this->assertIsArray($event);
        $this->assertSame(['logo'], $event['changed'] ?? null);
    }

    public function test_a_logo_is_kept_replaced_or_removed_but_its_file_is_never_overwritten_or_deleted(): void
    {
        $this->asOwner();
        $first = $this->postLogo(UploadedFile::fake()->createWithContent('a.png', TestImages::png()))->assertStatus(201);
        $firstLogo = $this->logoOf(self::idOf($first));
        $firstKey = $this->keyOf($firstLogo);
        $firstBytes = Storage::disk(MediaStore::DISK)->get($firstKey);

        // Keep: the next revision references the same file.
        $kept = $this->postJson(self::PATH, $this->change(['shopName' => 'Toko Tetap'], revision: self::idOf($first)))->assertStatus(201);
        $this->assertSame($firstLogo, $this->logoOf(self::idOf($kept)));

        // Replace: a new file under a new key; the old one is untouched.
        $replaced = $this->postLogo(UploadedFile::fake()->createWithContent('b.webp', TestImages::webp()), self::idOf($kept))->assertStatus(201);
        $secondLogo = $this->logoOf(self::idOf($replaced));
        $this->assertNotSame($firstLogo, $secondLogo);
        $this->assertNotSame($firstKey, $this->keyOf($secondLogo));
        $this->assertSame('image/webp', $this->observer()->table('stored_files')->where('id', $secondLogo)->value('media_type'));

        // Remove: no logo in force; both files and records are kept.
        $removed = $this->postJson(self::PATH, $this->change(['logoAction' => 'remove'], revision: self::idOf($replaced)))->assertStatus(201);
        $this->assertNull($this->logoOf(self::idOf($removed)));
        $this->assertSame(2, $this->observer()->table('stored_files')->count());
        $this->assertSame($firstBytes, Storage::disk(MediaStore::DISK)->get($firstKey));
        $this->assertCount(2, Storage::disk(MediaStore::DISK)->allFiles());
    }

    public function test_every_refused_logo_is_refused_before_any_fact_and_any_stored_file(): void
    {
        $this->asOwner();
        $before = $this->facts();
        $cases = [
            'svg' => [UploadedFile::fake()->createWithContent('logo.svg', TestImages::svg()), 'ux.validation.upload.image_type'],
            'svg named png' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::svg()), 'ux.validation.upload.image_type'],
            'html' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::html()), 'ux.validation.upload.image_type'],
            'gif' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::gif()), 'ux.validation.upload.image_type'],
            'other' => [UploadedFile::fake()->createWithContent('logo.png', 'MZ'.str_repeat("\x00", 64)), 'ux.validation.upload.image_type'],
            'animated png' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::animatedPng()), 'ux.validation.upload.animated'],
            'animated webp' => [UploadedFile::fake()->createWithContent('logo.webp', TestImages::animatedWebp()), 'ux.validation.upload.animated'],
            'too many pixels' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::pngClaiming(5000, 4000)), 'ux.validation.upload.too_many_pixels'],
            'too large' => [UploadedFile::fake()->createWithContent('logo.png', TestImages::png().str_repeat("\x00", 5 * 1024 * 1024)), 'ux.validation.upload.too_large'],
            'truncated' => [UploadedFile::fake()->createWithContent('logo.png', substr(TestImages::png(), 0, 40)), 'ux.validation.upload.unreadable'],
            'remote url' => ['https://contoh.test/logo.png', 'ux.validation.upload.unreadable'],
        ];

        foreach ($cases as $case => [$logo, $key]) {
            $response = $this->postLogo($logo)->assertStatus(422);
            $this->assertSame('VALIDATION_FAILED', $response->json('code'), $case);
            $this->assertSame($key, self::firstKey($response, 'payload.logo'), $case);
        }

        $this->assertSame($before, $this->facts());
        $this->assertSame([], Storage::disk(MediaStore::DISK)->allFiles(), 'No refused upload is stored.');

        // A logo with the action keep, or none with replace, is refused too.
        $this->postLogo(UploadedFile::fake()->createWithContent('logo.png', TestImages::png()), action: 'keep')->assertStatus(422);
        $this->postJson(self::PATH, $this->change(['logoAction' => 'replace']))->assertStatus(422);
        $this->assertSame($before, $this->facts());
    }

    public function test_rd_07_serves_a_logo_to_every_signed_in_role_privately_and_never_by_a_path(): void
    {
        $this->asOwner();
        $response = $this->postLogo(UploadedFile::fake()->createWithContent('logo.png', TestImages::png()))->assertStatus(201);
        $logo = $this->logoOf(self::idOf($response));
        $this->assertIsInt($logo);
        $key = $this->keyOf($logo);
        $bytes = Storage::disk(MediaStore::DISK)->get($key);

        foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $served = $this->get('/media/'.$logo)->assertOk();
            $this->assertSame('image/png', $served->headers->get('Content-Type'));
            $cache = (string) $served->headers->get('Cache-Control');

            foreach (['private', 'max-age=86400', 'immutable'] as $directive) {
                $this->assertStringContainsString($directive, $cache, $person);
            }

            $this->assertStringNotContainsString('no-store', $cache);
            $this->assertSame($bytes, $served->getContent());
            $this->assertStringNotContainsString($key, (string) json_encode($served->headers->all()));
        }

        // A guessed identifier, one that is not a number, and a variant that
        // does not apply: NOT_FOUND.
        $this->as('Rina');
        $this->getJson('/media/'.($logo + 1000))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->getJson('/media/abc')->assertStatus(404);
        $this->getJson('/media/'.$logo.'?variant=thumb')->assertStatus(404);
        $this->getJson('/media/'.$logo.'?path=x')->assertStatus(422);

        // A storage key is never an identifier.
        $this->getJson('/media/'.$key)->assertStatus(404);

        // Without a sign-in session nothing is served.
        $this->browse(null);
        $this->getJson('/media/'.$logo)->assertStatus(401);
        $this->assertNotSame($bytes, $this->get('/media/'.$logo)->getContent());
    }

    /**
     * Signs the Owner in with a recent password confirmation (UXS-11).
     */
    private function asOwner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * An envelope of RC-32 against a revision (the first unless given): the
     * Toko Contoh fields, with $payload replacing some.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function change(array $payload = [], ?int $revision = null): array
    {
        return [
            'operation' => 'business_profile.change',
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => $revision ?? $this->first],
            'payload' => [
                'shopName' => TokoContoh::SHOP,
                'address' => null,
                'contact' => null,
                'email' => null,
                'documentHeader' => null,
                'documentFooter' => null,
                'documentIdentity' => null,
                'accentToken' => null,
                'logoAction' => 'keep',
                ...$payload,
            ],
        ];
    }

    /**
     * RC-32 as multipart with a logo, its envelope as form fields: texts
     * only, an absent value empty (INTERACTION_CONTRACTS section 1.1).
     *
     * @return TestResponse<Response>
     */
    private function postLogo(UploadedFile|string $logo, ?int $revision = null, string $action = 'replace'): TestResponse
    {
        $envelope = $this->change(['logoAction' => $action], $revision);
        $form = [
            'operation' => 'business_profile.change',
            'version' => '1',
            'sourceIntentId' => $envelope['sourceIntentId'],
            'idempotencyKey' => $envelope['idempotencyKey'],
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => (string) ($revision ?? $this->first)],
            'payload' => ['shopName' => TokoContoh::SHOP, 'address' => '', 'contact' => '', 'email' => '', 'documentHeader' => '', 'documentFooter' => '', 'documentIdentity' => '', 'accentToken' => '', 'logoAction' => $action, 'logo' => $logo],
        ];

        return $this->post(self::PATH, $form, ['Accept' => 'application/json']);
    }

    private function logoOf(int $revision): ?int
    {
        $logo = $this->observer()->table('business_profile_revisions')->where('id', $revision)->value('logo_file_id');

        return is_int($logo) ? $logo : null;
    }

    private function keyOf(?int $file): string
    {
        return $this->readText($this->observer()->table('stored_files')->where('id', $file)->value('storage_key'));
    }

    /**
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->observer()->table($table)->count();
        }

        return $counts;
    }

    /**
     * The internal key of a committed command's result.
     *
     * @param  TestResponse<Response>  $response
     */
    private static function idOf(TestResponse $response): int
    {
        return self::number($response->json('result.id'));
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
}
