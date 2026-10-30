<?php

namespace Tests\Feature\Configuration;

use App\Actions\Access\ConsumeLink;
use App\Domain\Access\Accounts;
use Illuminate\Support\Facades\Artisan;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * OP-01 `owner:bootstrap` and the framework of the operator procedures
 * (AC-010-01; SECURITY_MODEL SEC-OP-01, section 14; OPERATIONS_RUNBOOK
 * OPR-02, OPR-06; SCHEMA guard G-23; THR-10; suites OPPROC01 and IDENTITY01;
 * TASK-010 scope item 3), as L2 procedure tests: the typed environment must
 * equal the configured name and the database marker; only typed, validated
 * options; refused when any account exists; in one transaction the one
 * ACTIVE SUPER_ADMIN, the recovery epoch, the onboarding state NOT_STARTED and
 * the first BusinessProfile revision with its pointer; the FIRST_PASSWORD link
 * only on the terminal, never in a log or the audit; the audit events with the
 * procedure as actor and the operator's note; the security log entry
 * OPERATOR_PROCEDURE_RUN, also for a refusal. RACE-30 is in
 * tests/Concurrency/BootstrapRaceTest.php.
 */
final class OwnerBootstrapTest extends DatabaseTestCase
{
    private const array OPTIONS = [
        '--environment' => 'test',
        '--name' => 'Hendra',
        '--email' => 'hendra@contoh.test',
        '--shop' => 'Toko Contoh',
        '--note' => 'Instalasi baru untuk Toko Contoh.',
    ];

    public function test_it_installs_the_first_owner_the_epoch_the_onboarding_state_and_the_first_revision_in_one_run(): void
    {
        $exit = Artisan::call('owner:bootstrap', self::OPTIONS);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $observer = $this->observer();

        // One ACTIVE SUPER_ADMIN, without a password until the link is used.
        $users = $observer->table('users')->get(['id', 'display_name', 'email', 'status', 'password', 'role_super_admin', 'role_operations_admin', 'role_cashier'])->all();
        $this->assertCount(1, $users);
        $owner = (array) $users[0];
        $this->assertSame(['Hendra', 'hendra@contoh.test', 'ACTIVE', null, true, false, false], [$owner['display_name'], $owner['email'], $owner['status'], $owner['password'], $owner['role_super_admin'], $owner['role_operations_admin'], $owner['role_cashier']]);

        // The epoch of at least 128 random bits, the onboarding state, and the
        // revision with its pointer (G-23).
        $this->assertGreaterThanOrEqual(16, $observer->scalar('select octet_length(epoch) from recovery_epoch where id = 1'));
        $this->assertSame(['NOT_STARTED', 1, null], array_values((array) $observer->table('onboarding_state')->first(['state', 'version', 'release_presence'])));
        $revision = (array) $observer->table('business_profile_revisions')->sole();
        $this->assertSame(['Toko Contoh', null, $owner['id'], 'Hendra', null, null], [$revision['shop_name'], $revision['accent_token'], $revision['actor_user_id'], $revision['actor_display_name'], $revision['command_id'], $revision['logo_file_id']]);
        $this->assertSame($revision['id'], $observer->table('business_profile_current')->where('id', 1)->value('revision_id'));

        // The audit events, the procedure their actor, with the note; no token.
        $events = $observer->table('audit_events')->orderBy('id')->get(['action', 'actor_kind', 'actor_process', 'actor_user_id', 'operator_note', 'command_id', 'change_data'])->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(['USER_CREATE', 'LINK_ISSUE', 'PROFILE_CHANGE'], array_column($events, 'action'));

        foreach ($events as $event) {
            $this->assertSame(['OPERATOR_PROCEDURE', 'bootstrap', null, 'Instalasi baru untuk Toko Contoh.', null], [$event['actor_kind'], $event['actor_process'], $event['actor_user_id'], $event['operator_note'], $event['command_id']]);
        }

        // The security log entry, after the transaction.
        $entry = (array) $observer->table('security_log_entries')->sole();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'OPERATOR_PROCEDURE', 'bootstrap', 'SUCCEEDED', $owner['id']], [$entry['event_type'], $entry['actor_kind'], $entry['actor_process'], $entry['outcome_code'], $entry['subject_user_id']]);

        // The link, printed to the terminal only: it sets the Owner's password
        // once, and appears in no log, audit event or security log entry.
        if (preg_match('~/set-password#u=(\d+)&t=([^\s]+)~', $output, $link) !== 1) {
            $this->fail('The link is printed to the terminal: '.$output);
        }

        $token = rawurldecode($link[2]);
        $ownerId = $owner['id'];
        $this->assertIsInt($ownerId);
        $this->assertSame((string) $ownerId, $link[1]);
        $this->assertStringNotContainsString($token, (string) json_encode($events));
        $this->assertStringNotContainsString($token, (string) json_encode($entry));
        $this->assertSame(0, $this->observer()->table('audit_events')->whereRaw('change_data::text like ?', ['%'.$token.'%'])->count());

        $password = TokoContoh::newPassword();
        app(ConsumeLink::class)($ownerId, $token, $password);
        $this->assertNotNull($observer->table('users')->where('id', $ownerId)->value('password'));
    }

    public function test_it_refuses_when_any_account_exists_and_writes_nothing(): void
    {
        TokoContoh::person('Rina');
        $before = $this->state();

        $exit = Artisan::call('owner:bootstrap', self::OPTIONS);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('An account exists', Artisan::output());
        $this->assertSame($before, $this->state());
        $this->assertSame(['REFUSED'], $this->observer()->table('security_log_entries')->where('event_type', 'OPERATOR_PROCEDURE_RUN')->pluck('outcome_code')->all());

    }

    public function test_a_second_run_after_a_successful_one_is_refused(): void
    {
        $this->assertSame(0, Artisan::call('owner:bootstrap', self::OPTIONS));
        $before = $this->state();

        $this->assertSame(1, Artisan::call('owner:bootstrap', [...self::OPTIONS, '--email' => 'lain@contoh.test']));
        $this->assertSame($before, $this->state());
        $this->assertSame(1, $this->observer()->table('users')->count());
    }

    public function test_it_refuses_a_typed_environment_that_differs_from_the_configuration_or_the_marker(): void
    {
        // The typed name differs from the configured name and the marker.
        $this->assertSame(1, Artisan::call('owner:bootstrap', [...self::OPTIONS, '--environment' => 'production']));
        $this->assertStringContainsString('is not the environment', Artisan::output());
        $this->assertSame(1, Artisan::call('owner:bootstrap', [...self::OPTIONS, '--environment' => '']));

        // The configured name differs from the marker: nothing is read or written.
        config(['app.environment_name' => 'staging']);
        $this->assertSame(1, Artisan::call('owner:bootstrap', [...self::OPTIONS, '--environment' => 'staging']));
        config(['app.environment_name' => 'test']);

        $this->assertSame(['users' => 0, 'recovery_epoch' => 0, 'onboarding_state' => 0, 'business_profile_revisions' => 0, 'audit_events' => 0], $this->state());
    }

    public function test_it_accepts_only_typed_valid_options_and_records_the_refused_run(): void
    {
        $invalid = [
            ['--name' => ''],
            ['--name' => str_repeat('n', 101)],
            ['--name' => "Hen\ndra"],
            ['--email' => 'bukan-email'],
            ['--email' => str_repeat('e', 250).'@contoh.test'],
            ['--shop' => ''],
            ['--shop' => str_repeat('t', 101)],
            ['--shop' => "Toko\x07Contoh"],
            ['--note' => ''],
            ['--note' => str_repeat('c', 1001)],
        ];

        foreach ($invalid as $options) {
            $this->assertSame(1, Artisan::call('owner:bootstrap', [...self::OPTIONS, ...$options]), (string) json_encode($options));
            $this->assertStringContainsString('is missing or not valid', Artisan::output());
        }

        $this->assertSame(['users' => 0, 'recovery_epoch' => 0, 'onboarding_state' => 0, 'business_profile_revisions' => 0, 'audit_events' => 0], $this->state());
        $this->assertSame(count($invalid), $this->observer()->table('security_log_entries')->where('event_type', 'OPERATOR_PROCEDURE_RUN')->where('outcome_code', 'REFUSED')->count());

        // Outer white space is trimmed; the values are stored as typed.
        $this->assertSame(0, Artisan::call('owner:bootstrap', [...self::OPTIONS, '--name' => '  Hendra  ', '--shop' => ' Toko Contoh ']));
        $this->assertSame('Hendra', $this->observer()->table('users')->value('display_name'));
        $this->assertSame('Toko Contoh', $this->observer()->table('business_profile_revisions')->value('shop_name'));
        $this->assertTrue(app(Accounts::class)->findByEmail('hendra@contoh.test')?->isActive());
    }

    public function test_a_failure_inside_the_transaction_leaves_nothing(): void
    {
        // The second write, the onboarding state, cannot be inserted when the
        // row exists: the epoch written before it rolls back with it.
        $this->migration()->table('onboarding_state')->insert(['id' => 1, 'state' => 'NOT_STARTED', 'version' => 1, 'changed_at' => now()]);

        $this->assertSame(1, Artisan::call('owner:bootstrap', self::OPTIONS));
        $this->assertStringContainsString('bootstrapped already', Artisan::output());
        $this->assertSame(['users' => 0, 'recovery_epoch' => 0, 'onboarding_state' => 1, 'business_profile_revisions' => 0, 'audit_events' => 0], $this->state());
    }

    /**
     * @return array<string, int>
     */
    private function state(): array
    {
        $counts = [];

        foreach (['users', 'recovery_epoch', 'onboarding_state', 'business_profile_revisions', 'audit_events'] as $table) {
            $counts[$table] = $this->observer()->table($table)->count();
        }

        return $counts;
    }
}
