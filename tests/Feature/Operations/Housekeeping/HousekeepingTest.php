<?php

namespace Tests\Feature\Operations\Housekeeping;

use App\Database\Identifier;
use App\Database\PrivilegeMatrix;
use App\Domain\Access\OneTimeLinks;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Commands\ErrorCode;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Media\MediaStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Media\TestImages;
use Throwable;

/**
 * SC-02 `housekeeping:run` (AC-038-01, AC-038-02, AC-038-04; ROUTE_CONTRACTS SC-02; ENVIRONMENTS sections 13
 * and 14; SCHEMA section 4, SCH-11 and sections 5.1, 5.3 to 5.5; SEC-ACC-07; SEC-LOG-05; TR-TX-05; ARC-41;
 * ARC-43; TR-RUN-04; THR-10; EXECUTION_CONTEXT section 10, a scheduled command; TASK-038 section 7, the L2
 * retention family and each cleanup with the test clock at its boundaries; suites KEEP01, LINK01, CMD01,
 * AUDIT01, MEDIA01 and IMPORT01). The clock stands still at the database's own instant, so that the
 * application's bound and the database's policy on the security log start from the same time:
 *
 * - a link is removed one second after it expired and never at its expiry or earlier;
 * - a COMMITTED, REJECTED or NOT_COMMITTED receipt one second past 30 days after its outcome, never at 30
 *   days, and never a PENDING or HELD receipt, whatever its age;
 * - a security log entry one second past 8,760 hours, never at it; and an entry younger by the database's
 *   clock stays whatever the application's clock says;
 * - an unreferenced stored file one second past its grace period, never at it, never a referenced file,
 *   never a name that is no storage key, never a file below the disk's root;
 * - a temporary CSV file one second past 24 hours, whatever its import's state, and nothing else there;
 * - bounded batches; no overlap; no option or argument; a refusal before anything on another database or a
 *   setting out of range; the run's security log entry; and no row of a class F, FS, M or S table changes,
 *   even with the clock years ahead.
 */
final class HousekeepingTest extends DatabaseTestCase
{
    use CommandRequests;

    private CarbonImmutable $now;

    private int $owner = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(MediaStore::DISK);
        Storage::fake(CsvImportFiles::DISK);
        $now = $this->observer()->scalar("select to_char(date_trunc('second', now()) at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"')");
        $this->now = CarbonImmutable::parse($this->readText($now));
        $this->travelTo($this->now);
        TokoContoh::shop();
        $this->owner = TokoContoh::person('Hendra')['id'];
    }

    public function test_an_expired_link_is_removed_and_never_one_in_force(): void
    {
        // AC-038-01; SEC-ACC-07; LINK01: the broker's 60 minutes, judged as DatabaseTokenRepository judges them.
        $expiry = OneTimeLinks::minutes() * 60;
        $emails = [];

        foreach (['Ani' => $expiry + 1, 'Bayu' => $expiry, 'Citra' => $expiry - 60] as $name => $age) {
            $account = TokoContoh::staff($name, ['cashier'], false);
            $this->travelTo($this->now->subSeconds($age));
            TokoContoh::tokenFor($account['id']);
            $this->travelTo($this->now);
            $emails[$name] = $account['email'];
        }

        $this->assertRemovedOnly('password_reset_tokens', fn (array $row): bool => $row['email'] === $emails['Ani'], fn () => $this->housekeep(), 'email');
        $this->assertStringContainsString('expired_links=0', $this->housekeep());
    }

    public function test_a_resolved_receipt_is_removed_after_30_days_and_never_an_unresolved_one(): void
    {
        // AC-038-01; SCHEMA section 5.3; ADR-009 section 1; D-088; CMD01.
        $receipts = app(RequestReceipts::class);
        $epoch = TokoContoh::recoveryEpoch();
        $retention = RequestReceipts::RETENTION_DAYS * 86400;
        $removable = [];

        foreach ([RequestReceipts::COMMITTED, RequestReceipts::REJECTED, RequestReceipts::NOT_COMMITTED, RequestReceipts::HELD] as $outcome) {
            foreach (['older' => $retention + 1, 'at' => $retention, 'younger' => $retention - 3600, 'ancient' => 400 * 86400] as $age => $seconds) {
                $at = $this->now->subSeconds($seconds);
                $this->travelTo($at);
                $id = $receipts->record($this->owner, 'sale.checkout', (string) Str::uuid(), (string) Str::uuid(), $epoch, $at->format('Y-m-d H:i:s.uP'));
                $receipts->mark($id, $outcome, $outcome === RequestReceipts::HELD ? ErrorCode::IdempotencyConflict->value : null);
                $removable[$id] = $outcome !== RequestReceipts::HELD && in_array($age, ['older', 'ancient'], true);
            }
        }

        // PENDING has no outcome_at; received 400 days ago, it stays.
        $at = $this->now->subDays(400);
        $this->travelTo($at);
        $removable[$receipts->record($this->owner, 'sale.checkout', (string) Str::uuid(), (string) Str::uuid(), $epoch, $at->format('Y-m-d H:i:s.uP'))] = false;
        $this->travelTo($this->now);

        $this->assertRemovedOnly('command_request_receipts', fn (array $row): bool => is_int($row['id']) && ($removable[$row['id']] ?? false), fn () => $this->housekeep());
        $this->assertSame(6, count(array_filter($removable)));
    }

    public function test_a_security_log_entry_is_removed_after_365_days_and_never_a_younger_one(): void
    {
        // AC-038-01; SEC-LOG-05; D-088; AUDIT01. The younger entry has ten minutes' margin for the
        // database's own clock, which runs on while the application's stands still.
        $hours = SecurityLog::RETENTION_HOURS;
        $this->logAt('ancient', $this->now->subDays(400));
        $this->logAt('older', $this->now->subHours($hours)->subSecond());
        $this->logAt('at', $this->now->subHours($hours));
        $this->logAt('younger', $this->now->subHours($hours)->addMinutes(10));
        $this->logAt('recent', $this->now->subDays(30));

        $output = $this->housekeep();

        $this->assertStringContainsString('security_log_entries=2', $output);
        $this->assertSame(['at', 'recent', 'younger'], $this->logged(['ancient', 'older', 'at', 'younger', 'recent']));

        // The run's own entry names the scheduled command, with no address and no route.
        $entry = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first(['event_type', 'actor_kind', 'actor_user_id', 'actor_process', 'outcome_code', 'route_name', 'client_address']);
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'SCHEDULED_COMMAND', null, 'housekeeping', 'SUCCEEDED', null, null], array_values($entry));
    }

    public function test_the_database_keeps_a_younger_entry_whatever_the_application_clock_says(): void
    {
        // SEC-LOG-05; OPEN_ITEMS T-23: two days ahead, the application selects an entry that the database's
        // policy keeps; two days behind, it never selects one that the policy would admit.
        $hours = SecurityLog::RETENTION_HOURS;
        $this->logAt('younger', $this->now->subHours($hours)->addDay());
        $this->logAt('older', $this->now->subHours($hours)->subDay());

        $this->travelTo($this->now->addDays(2));
        $this->assertStringContainsString('security_log_entries=1', $this->housekeep());
        $this->assertSame(['younger'], $this->logged(['younger', 'older']));

        $this->logAt('older', $this->now->subHours($hours)->subDay());
        $this->travelTo($this->now->subDays(2));
        $this->assertStringContainsString('security_log_entries=0', $this->housekeep());
        $this->assertSame(['older', 'younger'], $this->logged(['younger', 'older']));

        $this->travelTo($this->now);
        $this->housekeep();
        $this->assertSame(['younger'], $this->logged(['younger', 'older']));
    }

    public function test_an_unreferenced_file_is_removed_after_its_grace_period_and_never_earlier_nor_a_referenced_one(): void
    {
        // AC-038-01, AC-038-02; TR-TX-05; ARC-41; MEDIA01: the logo's file is referenced; the others are what a
        // rolled-back command leaves, at the boundary of the grace period.
        $referenced = $this->logo();
        $grace = config()->integer('housekeeping.file_grace_seconds');
        $this->assertSame(MediaStore::MIN_GRACE_SECONDS, $grace);
        $disk = Storage::disk(MediaStore::DISK);
        $files = [
            'referenced' => [$referenced, 400 * 86400],
            'older' => [MediaStore::newKey(), $grace + 1],
            'at' => [MediaStore::newKey(), $grace],
            'younger' => [MediaStore::newKey(), $grace - 60],
            'no key' => ['catatan.txt', 400 * 86400],
            'below the root' => ['lain/'.MediaStore::newKey(), 400 * 86400],
        ];

        foreach ($files as [$name, $age]) {
            if ($name !== $referenced) {
                $disk->put($name, 'isi');
            }

            $this->assertTrue(touch($disk->path($name), $this->now->getTimestamp() - $age));
        }

        $this->assertStringContainsString('unreferenced_files=1', $this->housekeep());

        foreach ($files as $case => [$name]) {
            $this->assertSame($case !== 'older', $disk->exists($name), $case);
        }

        $this->assertSame(1, $this->observer()->table('stored_files')->where('storage_key', $referenced)->count());
    }

    public function test_a_temporary_csv_file_is_removed_after_24_hours_whatever_its_state_and_nothing_else_there(): void
    {
        // AC-038-02; ARC-43; D-054; IMPORT01.
        $imports = app(CsvImportFiles::class);
        $disk = Storage::disk(CsvImportFiles::DISK);
        $paths = [];

        foreach (['older' => CsvImportFiles::MAX_AGE_SECONDS + 1, 'at' => CsvImportFiles::MAX_AGE_SECONDS, 'younger' => CsvImportFiles::MAX_AGE_SECONDS - 60] as $case => $age) {
            $paths[$case] = [$this->owner.'/'.$imports->store($this->owner, "sku;nama\nA-1;Pulpen\n"), $age];
        }

        $paths['no key'] = [$this->owner.'/catatan.txt', 400 * 86400];
        $paths['at the root'] = [str_repeat('a', 64), 400 * 86400];

        foreach ($paths as [$path, $age]) {
            if (! $disk->exists($path)) {
                $disk->put($path, 'isi');
            }

            $this->assertTrue(touch($disk->path($path), $this->now->getTimestamp() - $age));
        }

        $this->assertStringContainsString('temporary_csv_files=1', $this->housekeep());

        foreach ($paths as $case => [$path]) {
            $this->assertSame($case !== 'older', $disk->exists($path), $case);
        }
    }

    public function test_each_cleanup_runs_in_bounded_batches_and_the_next_run_takes_the_rest(): void
    {
        // TR-RUN-04; ENVIRONMENTS SC-02: at most batch_size x max_batches of each kind per run.
        config(['housekeeping.batch_size' => 2, 'housekeeping.max_batches' => 2]);
        $disk = Storage::disk(MediaStore::DISK);

        for ($entry = 1; $entry <= 5; $entry++) {
            $this->logAt('old-'.$entry, $this->now->subDays(400));
            $key = MediaStore::newKey();
            $disk->put($key, 'isi');
            $this->assertTrue(touch($disk->path($key), $this->now->getTimestamp() - 86400));
        }

        $first = $this->housekeep();
        $this->assertStringContainsString('security_log_entries=4', $first);
        $this->assertStringContainsString('unreferenced_files=4', $first);
        $this->assertCount(1, $this->logged(['old-1', 'old-2', 'old-3', 'old-4', 'old-5']));
        $this->assertCount(1, $disk->files());

        $second = $this->housekeep();
        $this->assertStringContainsString('security_log_entries=1', $second);
        $this->assertStringContainsString('unreferenced_files=1', $second);
        $this->assertSame([], $this->logged(['old-1', 'old-2', 'old-3', 'old-4', 'old-5']));
        $this->assertSame([], $disk->files());
    }

    public function test_referenced_files_never_use_up_the_limit_of_a_run(): void
    {
        // Review R-01; AC-038-02: more old referenced files than a run may remove, and one unreferenced file
        // whose key sorts after all of them, the disk being walked in the order of its names.
        config(['housekeeping.batch_size' => 2, 'housekeeping.max_batches' => 2]);
        $disk = Storage::disk(MediaStore::DISK);
        $referenced = [];

        for ($logo = 1; $logo <= 5; $logo++) {
            $referenced[] = $this->logo();
        }

        $last = str_repeat('z', 43);
        $disk->put($last, 'isi');

        foreach ([...$referenced, $last] as $name) {
            $this->assertTrue(touch($disk->path($name), $this->now->getTimestamp() - 86400));
        }

        $this->assertStringContainsString('unreferenced_files=1', $this->housekeep());
        $this->assertFalse($disk->exists($last));

        foreach ($referenced as $key) {
            $this->assertTrue($disk->exists($key), $key);
        }

        $this->assertSame(5, $this->observer()->table('stored_files')->count());
    }

    public function test_a_failed_run_keeps_what_it_removed_records_its_entry_and_frees_the_next_run(): void
    {
        // Review R-02; EXECUTION_CONTEXT section 10, a scheduled command: the table batches commit, then the
        // media disk cannot be opened; the run fails with its entry, and the next run takes the rest.
        $this->logAt('ancient', $this->now->subDays(400));
        config(['filesystems.disks.'.MediaStore::DISK.'.driver' => 'unavailable']);
        Storage::forgetDisk(MediaStore::DISK);

        $this->assertSame(1, Artisan::call('housekeeping:run'));
        $this->assertStringContainsString('HOUSEKEEPING_FAILED', Artisan::output());
        $this->assertSame([], $this->logged(['ancient']));
        $entry = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first(['actor_process', 'outcome_code']);
        $this->assertSame(['housekeeping', 'REFUSED'], array_values($entry));

        Storage::fake(MediaStore::DISK);
        $this->housekeep();
    }

    public function test_the_command_never_overlaps_itself_and_takes_no_option_or_argument(): void
    {
        // AC-038-04; TR-RUN-04; THR-10.
        $this->logAt('ancient', $this->now->subDays(400));
        $lock = Cache::lock('housekeeping:run', 60);
        $this->assertTrue($lock->get());

        $this->assertSame(1, Artisan::call('housekeeping:run'));
        $this->assertStringContainsString('HOUSEKEEPING_ALREADY_RUNNING', Artisan::output());
        $this->assertSame(['ancient'], $this->logged(['ancient']));
        $lock->release();

        $command = Artisan::all()['housekeeping:run'] ?? $this->fail('housekeeping:run is not registered.');
        $this->assertSame([], $command->getNativeDefinition()->getArguments());
        $this->assertSame([], $command->getNativeDefinition()->getOptions());

        $refusal = null;

        try {
            Artisan::call('housekeeping:run', ['--batch-size' => '1000000']);
        } catch (Throwable $thrown) {
            $refusal = $thrown;
        }

        $this->assertInstanceOf(InvalidOptionException::class, $refusal);
        $this->assertSame(['ancient'], $this->logged(['ancient']));

        $this->housekeep();
        $this->assertSame([], $this->logged(['ancient']));
    }

    public function test_a_run_is_refused_before_anything_on_another_database_or_with_a_setting_out_of_range(): void
    {
        // ENVIRONMENTS ENV-04: a file is removed when the database names it nowhere, so never against another
        // environment's database; TR-TX-05: never a grace period below one hour.
        $this->logAt('ancient', $this->now->subDays(400));
        $disk = Storage::disk(MediaStore::DISK);
        $key = MediaStore::newKey();
        $disk->put($key, 'isi');
        $this->assertTrue(touch($disk->path($key), $this->now->getTimestamp() - 86400));
        $entries = $this->observer()->table('security_log_entries')->count();

        config(['app.environment_name' => 'staging']);
        $this->assertSame(1, Artisan::call('housekeeping:run'));
        $this->assertStringContainsString('HOUSEKEEPING_REFUSED', Artisan::output());
        // The harness's observer checks the marker too, so the name is set back first.
        config(['app.environment_name' => 'test']);
        $this->assertSame($entries, $this->observer()->table('security_log_entries')->count());

        foreach ([
            ['housekeeping.file_grace_seconds' => MediaStore::MIN_GRACE_SECONDS - 1],
            ['housekeeping.file_grace_seconds' => '3600'],
            ['housekeeping.batch_size' => 0],
            ['housekeeping.batch_size' => 1001],
            ['housekeeping.max_batches' => 101],
            ['housekeeping.max_batches' => null],
        ] as $setting) {
            $saved = config()->all()['housekeeping'];
            config($setting);
            $this->assertSame(1, Artisan::call('housekeeping:run'), (string) json_encode($setting));
            $this->assertStringContainsString('HOUSEKEEPING_REFUSED', Artisan::output());
            config(['housekeeping' => $saved]);
        }

        $this->assertSame(['ancient'], $this->logged(['ancient']));
        $this->assertTrue($disk->exists($key));
        $this->assertSame(6, $this->observer()->table('security_log_entries')->where('actor_process', 'housekeeping')->where('outcome_code', 'REFUSED')->count());
    }

    public function test_a_run_changes_no_row_of_a_retained_class_even_with_the_clock_years_ahead(): void
    {
        // AC-038-01, AC-038-03; SCHEMA section 4 and SCH-11; KEEP01: every table of class F, FS, M or S is
        // read whole before and after; with the clock three years ahead every age-based rule would select
        // everything, and only the ephemeral kinds go.
        $this->logo();
        $product = TokoContoh::product('SKU-1');
        TokoContoh::barcode($product, 'KODE-SIMPAN-1');
        TokoContoh::stock($product, '5');
        TokoContoh::staff('Dimas', ['cashier'], false);
        $retained = array_keys(array_filter(PrivilegeMatrix::load()->tables(), static fn (array $table): bool => in_array($table['class'], ['F', 'FS', 'M', 'S'], true)));
        $this->assertContains('audit_events', $retained);
        $before = $this->fingerprints($retained);
        $this->assertGreaterThan(0, $before['audit_events'][0]);

        $this->travelTo($this->now->addYears(3));
        $output = $this->housekeep();

        $this->assertSame($before, $this->fingerprints($retained));
        $this->assertStringContainsString('unreferenced_files=0', $output);
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
        $this->assertSame(0, $this->observer()->table('command_request_receipts')->whereIn('outcome', ['COMMITTED', 'REJECTED', 'NOT_COMMITTED'])->count());
    }

    private function housekeep(): string
    {
        $exit = Artisan::call('housekeeping:run');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('HOUSEKEEPING_COMPLETED', $output);

        return $output;
    }

    /**
     * An entry of the security log written at that instant through the Audit module's writer, named by
     * its run identifier.
     */
    private function logAt(string $tag, CarbonImmutable $at): void
    {
        $this->travelTo($at);
        app(SecurityLog::class)->recordRun(SecurityEvent::OperatorProcedureRun, SecurityActor::scheduledCommand('housekeeping'), SecurityOutcome::Succeeded, $tag);
        $this->travelTo($this->now);
    }

    /**
     * Which of these entries remain, in alphabetical order.
     *
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function logged(array $tags): array
    {
        $remaining = [];

        foreach ($this->observer()->table('security_log_entries')->whereIn('request_id', $tags)->orderBy('request_id')->pluck('request_id') as $tag) {
            $remaining[] = $this->readText($tag);
        }

        return $remaining;
    }

    /**
     * A new logo of the Owner's through RC-32 (EXC-45), replacing the one in force, whose file and row are
     * kept (D-042): a stored file that a row names. Its storage key.
     */
    private function logo(): string
    {
        if ($this->people === []) {
            $this->setUpCommands();
            $this->as('Hendra');
            $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        }

        $known = $this->observer()->table('stored_files')->pluck('id')->all();
        $revision = TokoContoh::shop();
        $form = [
            'operation' => 'business_profile.change',
            'version' => '1',
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => ['revisionId' => (string) $revision],
            'payload' => ['shopName' => TokoContoh::SHOP, 'address' => '', 'contact' => '', 'email' => '', 'documentHeader' => '', 'documentFooter' => '', 'documentIdentity' => '', 'accentToken' => 'green', 'logoAction' => 'replace', 'logo' => UploadedFile::fake()->createWithContent('logo.png', TestImages::png())],
        ];
        $this->post('/commands/business_profile.change', $form, ['Accept' => 'application/json'])->assertStatus(201);

        return $this->readText($this->observer()->table('stored_files')->where('purpose', 'LOGO')->whereNotIn('id', $known)->value('storage_key'));
    }

    /**
     * Each table's row count and a digest of all its rows in a fixed order, read by an independent session.
     *
     * @param  list<string>  $tables
     * @return array<string, array{int, string}>
     */
    private function fingerprints(array $tables): array
    {
        $observer = $this->observer();
        $fingerprints = [];

        foreach ($tables as $table) {
            $row = (array) $observer->selectOne("select count(*) as n, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as digest from ".Identifier::quote($table).' t');
            $count = $row['n'] ?? null;
            $fingerprints[$table] = [is_int($count) ? $count : -1, $this->readText($row['digest'] ?? null)];
        }

        return $fingerprints;
    }
}
