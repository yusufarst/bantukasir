<?php

namespace Tests\Concurrency;

use Illuminate\Support\Str;
use Tests\Harness\Configuration\ProfileChangeScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Media\TestImages;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-32 `business_profile.change` (TECHNICAL_RULES section 8:
 * RACE-01, RACE-02 and RC-32's side of RACE-25; EXECUTION_CONTEXT section
 * 10, a command; TASK-010 review finding R-01), each with two processes of
 * their own connections under barrier control:
 *
 * - RACE-01: a repeat with the same key waits for the original on the command
 *   identity and returns it, or IDEMPOTENCY_CONFLICT when its payload differs
 *   — for a multipart request too, whose new logo stands in the fingerprint by
 *   its SHA-256;
 * - RACE-02: another key of the same intent, without a logo, returns the
 *   original in the same way;
 * - RACE-25, RC-32's side: two changes against the same revision; the second
 *   waits on the pointer's update lock and is refused STALE_VERSION once the
 *   first moved it. The snapshot side, a command that freezes the identity,
 *   is TASK-028's and TASK-030's.
 */
final class ProfileChangeRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    /** @var list<string> */
    private array $files = [];

    private int $owner = 0;

    private int $revision = 0;

    private string $epoch = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->revision = TokoContoh::shop();
        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_race_01_the_same_key_twice_runs_once_with_and_without_a_logo(): void
    {
        $logo = $this->logoFile(TestImages::png(40, 20));
        $other = $this->logoFile(TestImages::png(20, 40));

        foreach ([[null, null, 'Toko Satu'], [null, null, 'Toko Lain'], [$logo, $logo, 'Toko Satu'], [$logo, $other, 'Toko Satu']] as $index => [$firstLogo, $secondLogo, $secondShop]) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $first = $this->start($this->change($intent, $key, 'Toko Satu', $firstLogo), ['after_identity']);
            $second = $this->start($this->change($intent, $key, $secondShop, $secondLogo));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->observer());
            $first->release();

            $original = $first->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], 'case '.$index);
            $same = $secondShop === 'Toko Satu' && $firstLogo === $secondLogo;
            $expected = $same ? ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null] : ['refused' => 'IDEMPOTENCY_CONFLICT'];
            $this->assertSame($expected, $second->finish()->result, 'case '.$index);
            $this->assertSame(1, $this->observer()->table('business_profile_revisions')->where('command_id', $original['commandId'] ?? 0)->count(), 'case '.$index);
            $this->revision = $this->currentRevision();
        }

        // Each original wrote one revision, and the logos one record each.
        $this->assertSame(5, $this->observer()->table('business_profile_revisions')->count());
        $this->assertSame(2, $this->observer()->table('stored_files')->count());
    }

    public function test_race_02_another_key_of_the_same_intent_runs_once(): void
    {
        foreach (['Toko Satu', 'Toko Lain'] as $index => $secondShop) {
            $intent = (string) Str::uuid();
            $first = $this->start($this->change($intent, (string) Str::uuid(), 'Toko Satu', null), ['after_identity']);
            $second = $this->start($this->change($intent, (string) Str::uuid(), $secondShop, null));
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_identity');
            $second->run()->awaitBlocked($this->observer());
            $first->release();

            $original = $first->finish()->result ?? [];
            $expected = $secondShop === 'Toko Satu' ? ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null] : ['refused' => 'IDEMPOTENCY_CONFLICT'];
            $this->assertSame($expected, $second->finish()->result, 'case '.$index);
            $this->assertSame(1, $this->observer()->table('command_identities')->where('source_intent_id', $intent)->count(), 'case '.$index);
            $this->revision = $this->currentRevision();
        }

        $this->assertSame(3, $this->observer()->table('business_profile_revisions')->count());
    }

    public function test_race_25_of_two_changes_against_the_same_revision_the_second_is_stale(): void
    {
        $first = $this->start($this->change((string) Str::uuid(), (string) Str::uuid(), 'Toko Satu', null), ['after_lock:2.4']);
        $second = $this->start($this->change((string) Str::uuid(), (string) Str::uuid(), 'Toko Dua', null));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:2.4');
        $second->run()->awaitBlocked($this->observer());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result);
        $this->assertSame(['Toko Contoh', 'Toko Satu'], $this->observer()->table('business_profile_revisions')->orderBy('id')->pluck('shop_name')->all());
        $this->assertSame('Toko Satu', $this->observer()->table('business_profile_revisions')->where('id', $this->currentRevision())->value('shop_name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function change(string $intent, string $key, string $shop, ?string $logo): array
    {
        return ['actor' => $this->owner, 'intent' => $intent, 'key' => $key, 'epoch' => $this->epoch, 'revision' => $this->revision, 'shop' => $shop, 'logo' => $logo];
    }

    /**
     * A logo in a file of its own, which the workers read as an upload.
     */
    private function logoFile(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bk-logo-');

        if ($path === false || file_put_contents($path, $bytes) === false) {
            $this->fail('The test logo is written to a temporary file.');
        }

        $this->files[] = $path;

        return $path;
    }

    private function currentRevision(): int
    {
        $revision = $this->observer()->table('business_profile_current')->where('id', 1)->value('revision_id');

        return is_int($revision) ? $revision : 0;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(array $arguments, array $barriers = []): Worker
    {
        $worker = Worker::start(ProfileChangeScenario::class, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
