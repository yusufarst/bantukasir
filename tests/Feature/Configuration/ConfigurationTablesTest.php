<?php

namespace Tests\Feature\Configuration;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The five tables of TASK-010 (AC-010-08; SCHEMA sections 5.2, 5.5, 5.7, 7, 9.4
 * and 10; guards G-20 and G-23; the migration
 * 2026_10_30_000001_create_profile_media_onboarding_tables; EXECUTION_CONTEXT
 * section 10, a table; suites DB01 and KEEP01): the constraint family,
 * written directly by the runtime role; the deferred family — the onboarding
 * state is RELEASED only with its release record; the privilege family of
 * SCHEMA section 10; and the retention family, in which a BusinessProfile
 * change only adds revisions and stored files and moves the one pointer.
 */
final class ConfigurationTablesTest extends DatabaseTestCase
{
    use CommandRequests;

    private const string SHA = "decode('abababababababababababababababababababababababababababababababab', 'hex')";

    private const string SHORT = "decode('00', 'hex')";

    private int $command = 0;

    private int $revision = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        TokoContoh::shop();
        $this->setUpCommands();

        // One committed change gives a command for the rows below.
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $response = $this->postJson('/commands/business_profile.change', $this->change($this->currentRevision()))->assertStatus(201);
        $this->command = self::number($response->json('commandId'));
        $this->revision = self::number($response->json('result.id'));
    }

    public function test_each_check_and_key_of_stored_files_refuses_its_violation(): void
    {
        $checks = [
            'stored_files_storage_key_format' => ['storage_key' => 'kunci/../rahasia'],
            'stored_files_purpose_allowed' => ['purpose' => 'AVATAR'],
            'stored_files_media_type_allowed' => ['media_type' => 'image/gif'],
            'stored_files_byte_size_positive' => ['byte_size' => 0],
            'stored_files_width_positive' => ['width' => 0],
            'stored_files_height_positive' => ['height' => 0],
            'stored_files_sha256_length' => ['sha256' => 'short'],
            'stored_files_thumbnail_derived' => ['purpose' => 'PRODUCT_THUMBNAIL'],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, $values), '23514', $constraint);
        }

        $file = $this->insertFile($this->runtime());
        $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, ['storage_key' => $this->readText($c->table('stored_files')->where('id', $file)->value('storage_key'))]), '23505', 'stored_files_storage_key_unique');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, ['actor_user_id' => 999999]), '23503', 'stored_files_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, ['command_id' => 999999]), '23503', 'stored_files_command_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, ['purpose' => 'PRODUCT_THUMBNAIL', 'derived_from_file_id' => 999999]), '23503', 'stored_files_derived_from_fk');

        // A thumbnail derives from a file; a photo or logo from none.
        $this->insertFile($this->runtime(), ['purpose' => 'PRODUCT_THUMBNAIL', 'derived_from_file_id' => $file]);
        $this->assertWriteRefused(fn (Connection $c) => $this->insertFile($c, ['derived_from_file_id' => $file]), '23514', 'stored_files_thumbnail_derived');
    }

    public function test_each_check_and_key_of_the_revisions_and_the_pointer_refuses_its_violation(): void
    {
        $checks = [
            'business_profile_revisions_shop_name_length' => ['shop_name' => ''],
            'business_profile_revisions_address_length' => ['address' => str_repeat('a', 501)],
            'business_profile_revisions_contact_length' => ['contact' => ''],
            'business_profile_revisions_email_length' => ['email' => str_repeat('e', 255)],
            'business_profile_revisions_document_header_length' => ['document_header' => str_repeat('h', 501)],
            'business_profile_revisions_document_footer_length' => ['document_footer' => ''],
            'business_profile_revisions_document_identity_length' => ['document_identity' => str_repeat('i', 101)],
            'business_profile_revisions_accent_token_allowed' => ['accent_token' => 'red'],
            'business_profile_revisions_actor_display_name_length' => ['actor_display_name' => ''],
        ];

        foreach ($checks as $constraint => $values) {
            $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, $values), '23514', $constraint);
        }

        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['logo_file_id' => 999999]), '23503', 'business_profile_revisions_logo_file_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['actor_user_id' => 999999]), '23503', 'business_profile_revisions_actor_user_fk');
        $this->assertWriteRefused(fn (Connection $c) => $this->insertRevision($c, ['command_id' => 999999]), '23503', 'business_profile_revisions_command_fk');

        // One pointer only (G-23), to a revision that exists.
        $this->assertWriteRefused(fn (Connection $c) => $c->table('business_profile_current')->insert(['id' => 2, 'revision_id' => $this->revision, 'changed_at' => now()]), '23514', 'business_profile_current_single');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('business_profile_current')->insert(['id' => 1, 'revision_id' => $this->revision, 'changed_at' => now()]), '23505', 'business_profile_current_pkey');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('business_profile_current')->where('id', 1)->update(['revision_id' => 999999]), '23503', 'business_profile_current_revision_fk');
    }

    public function test_the_onboarding_state_is_released_only_with_its_release_record(): void
    {
        $hendra = $this->people['Hendra']['id'];

        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_state')->insert(['id' => 2, 'state' => 'NOT_STARTED', 'version' => 1, 'changed_at' => now()]), '23514', 'onboarding_state_single');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_state')->where('id', 1)->update(['state' => 'DONE']), '23514', 'onboarding_state_state_allowed');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_state')->where('id', 1)->update(['version' => 0]), '23514', 'onboarding_state_version_positive');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_releases')->insert(['id' => 2, 'released_at' => now(), 'kind' => 'RESTORE_RECONCILIATION', 'actor_user_id' => $hendra]), '23514', 'onboarding_releases_single');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_releases')->insert(['id' => 1, 'released_at' => now(), 'kind' => 'OTHER', 'actor_user_id' => $hendra]), '23514', 'onboarding_releases_kind_allowed');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_releases')->insert(['id' => 1, 'released_at' => now(), 'kind' => 'RELEASE', 'actor_user_id' => $hendra]), '23514', 'onboarding_releases_command_kind');
        $this->assertWriteRefused(fn (Connection $c) => $c->table('onboarding_releases')->insert(['id' => 1, 'released_at' => now(), 'kind' => 'RESTORE_RECONCILIATION', 'actor_user_id' => $hendra, 'command_id' => $this->command]), '23514', 'onboarding_releases_command_kind');

        // RELEASED without its record cannot commit; nothing is left.
        $before = $this->snapshot('onboarding_state');
        $this->assertFailsAtCommit(
            fn (Connection $c) => $c->table('onboarding_state')->where('id', 1)->update(['state' => 'RELEASED', 'version' => 2, 'changed_at' => now(), 'changed_by_user_id' => $hendra]),
            'onboarding_state_release_presence_fk',
            ['onboarding_releases'],
        );
        $this->assertSame($before, $this->snapshot('onboarding_state'));

        // With its record in the same transaction it commits.
        $this->runtime()->transaction(function (Connection $c) use ($hendra): void {
            $c->table('onboarding_releases')->insert(['id' => 1, 'released_at' => now(), 'kind' => 'RELEASE', 'actor_user_id' => $hendra, 'command_id' => $this->command]);
            $c->table('onboarding_state')->where('id', 1)->update(['state' => 'RELEASED', 'version' => 2, 'changed_at' => now(), 'changed_by_user_id' => $hendra]);
        });
        $this->assertSame(['RELEASED', 1], array_values((array) $this->observer()->table('onboarding_state')->first(['state', 'release_presence'])));
    }

    public function test_the_runtime_and_backup_roles_hold_exactly_the_privileges_of_schema_section_10(): void
    {
        $runtime = $this->runtime();
        $file = $this->insertFile($runtime);

        // Class F: added, never changed or removed.
        foreach (['stored_files' => 'created_at', 'business_profile_revisions' => 'created_at', 'onboarding_releases' => 'released_at'] as $table => $column) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->update([$column => now()]));
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->delete());
        }

        $this->assertDenied($runtime, fn (Connection $c) => $c->table('stored_files')->where('id', $file)->update(['storage_key' => Str::random(43)]));

        // Class S: only the columns that move.
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('business_profile_current')->update(['id' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('business_profile_current')->delete());
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('onboarding_state')->update(['id' => 1]));
        $this->assertDenied($runtime, fn (Connection $c) => $c->table('onboarding_state')->delete());

        foreach (['stored_files', 'business_profile_revisions', 'business_profile_current', 'onboarding_releases', 'onboarding_state'] as $table) {
            $this->assertDenied($runtime, fn (Connection $c) => $c->table($table)->truncate());
            $this->assertDenied($this->backup(), fn (Connection $c) => $c->table($table)->delete());
            $this->assertSame($runtime->table($table)->count(), $this->backup()->table($table)->count(), $table);
        }
    }

    public function test_a_change_only_adds_revisions_and_stored_files_and_moves_the_one_pointer(): void
    {
        $this->insertFile($this->runtime());
        $files = $this->storedFiles();

        $this->assertRetained(['business_profile_revisions', 'onboarding_state'], function (): void {
            $this->postJson('/commands/business_profile.change', $this->change($this->revision, ['shopName' => 'Toko Lain']))->assertStatus(201);
            $this->postJson('/commands/business_profile.change', $this->change($this->currentRevision(), ['logoAction' => 'remove']))->assertStatus(201);
        });

        // The stored files, their checksums read as text.
        $this->assertSame($files, array_slice($this->storedFiles(), 0, count($files)));
        $this->assertSame(1, $this->observer()->table('business_profile_current')->count());
        $this->assertSame($this->observer()->table('business_profile_revisions')->max('id'), $this->currentRevision());
    }

    /**
     * Every stored file's record, the checksum as text, in key order.
     *
     * @return list<array<string, mixed>>
     */
    private function storedFiles(): array
    {
        return array_values(array_map(
            static fn (object $row): array => (array) $row,
            $this->observer()->table('stored_files')->orderBy('id')
                ->selectRaw("id, storage_key, purpose, media_type, byte_size, width, height, encode(sha256, 'hex') as sha256, derived_from_file_id, created_at, actor_user_id, command_id")
                ->get()->all(),
        ));
    }

    private function currentRevision(): int
    {
        $revision = $this->observer()->table('business_profile_current')->where('id', 1)->value('revision_id');

        return is_int($revision) ? $revision : 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function change(int $revision, array $payload = []): array
    {
        return [
            'operation' => 'business_profile.change',
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => $revision],
            'payload' => [
                'shopName' => TokoContoh::SHOP, 'address' => null, 'contact' => null, 'email' => null, 'documentHeader' => null,
                'documentFooter' => null, 'documentIdentity' => null, 'accentToken' => 'green', 'logoAction' => 'keep', ...$payload,
            ],
        ];
    }

    /**
     * A stored file's record written directly; `sha256` => 'short' writes a
     * checksum of one byte.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertFile(Connection $c, array $values = []): int
    {
        $short = ($values['sha256'] ?? null) === 'short';
        unset($values['sha256']);

        return $c->table('stored_files')->insertGetId([
            'storage_key' => Str::random(43), 'purpose' => 'LOGO', 'media_type' => 'image/png', 'byte_size' => 10, 'width' => 4, 'height' => 4,
            'sha256' => $short ? $c->raw(self::SHORT) : $c->raw(self::SHA), 'derived_from_file_id' => null, 'created_at' => now(),
            'actor_user_id' => $this->people['Hendra']['id'], 'command_id' => $this->command,
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertRevision(Connection $c, array $values = []): int
    {
        return $c->table('business_profile_revisions')->insertGetId([
            'shop_name' => 'Toko Contoh', 'created_at' => now(), 'actor_user_id' => $this->people['Hendra']['id'], 'actor_display_name' => 'Hendra',
            'command_id' => $this->command, ...$values,
        ]);
    }
}
