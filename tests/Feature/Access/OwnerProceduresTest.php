<?php

namespace Tests\Feature\Access;

use App\Actions\Access\ConsumeLink;
use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;
use Tests\Harness\Race\FailurePoints;
use Tests\Harness\Race\InjectedFailure;

/**
 * OP-02 `owner:recover` and OP-03 `owner:replace` (AC-011-02, AC-011-06;
 * SECURITY_MODEL SEC-OP-02, SEC-OP-03, section 14; OPERATIONS_RUNBOOK RB-10,
 * RB-11, OPR-02, OPR-06; G-08; THR-10; suite OPPROC01; TASK-011 scope item 3), as
 * L2 procedure tests: the typed environment, typed and validated options, the
 * effects in one transaction, the link on the terminal only, never in a log or the
 * audit, the audit events with the procedure as actor and the operator's note, the
 * security log entries, and a repeated OP-03 refused. RACE-21 and RACE-30 are in
 * tests/Concurrency/UserManagementRaceTest.php.
 */
final class OwnerProceduresTest extends DatabaseTestCase
{
    use Browsers;

    /** @var array{id: int, email: string, password: string} */
    private array $owner;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->useDatabaseSessions();
        $this->owner = TokoContoh::person('Hendra');
    }

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    public function test_op_02_issues_a_reset_link_replacing_any_earlier_one_and_revokes_the_owners_sessions(): void
    {
        $session = $this->signInBrowser($this->owner['email'], $this->owner['password']);
        $earlier = TokoContoh::tokenFor($this->owner['id']);
        $before = $this->watcher()->table('users')->where('id', $this->owner['id'])->first(['display_name', 'email', 'role_super_admin', 'status', 'version']);

        $exit = Artisan::call('owner:recover', ['--environment' => 'test', '--note' => 'Pemilik lupa kata sandi; identitas sudah diperiksa.']);
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);

        // Name, email, role and status are unchanged; the sessions are gone.
        $this->assertEquals($before, $this->watcher()->table('users')->where('id', $this->owner['id'])->first(['display_name', 'email', 'role_super_admin', 'status', 'version']));
        $this->assertSame(0, $this->watcher()->table('sessions')->where('user_id', $this->owner['id'])->count());
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status')->assertStatus(401);

        // The audit events name the procedure, with the note and no token.
        $events = $this->watcher()->table('audit_events')->whereIn('action', ['OWNER_RECOVERY', 'LINK_ISSUE'])->where('actor_process', 'owner_recovery')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(['OWNER_RECOVERY', 'LINK_ISSUE'], array_column($events, 'action'));
        $token = $this->tokenIn($output, $this->owner['id']);

        foreach ($events as $event) {
            $this->assertSame(['OPERATOR_PROCEDURE', null, 'Pemilik lupa kata sandi; identitas sudah diperiksa.', null, $this->owner['id']], [$event['actor_kind'], $event['actor_user_id'], $event['operator_note'], $event['command_id'], $event['subject_id']]);
            $this->assertStringNotContainsString($token, (string) json_encode($event));
        }

        // The security log: the run and the ended sessions, without a token.
        $entries = $this->watcher()->table('security_log_entries')->where('actor_process', 'owner_recovery')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'SESSION_REVOKED'], array_column($entries, 'event_type'));
        $this->assertSame(['SUCCEEDED', 'SUCCEEDED'], array_column($entries, 'outcome_code'));
        $this->assertSame([$this->owner['id'], $this->owner['id']], array_column($entries, 'subject_user_id'));
        $this->assertStringNotContainsString($token, (string) json_encode($entries));

        // The earlier link is gone; the new one sets a new password, once.
        $password = TokoContoh::newPassword();
        $this->browse(null);
        $this->post('/set-password', ['user' => (string) $this->owner['id'], 'token' => $earlier, 'password' => $password, 'password_confirmation' => $password]);
        $this->assertTrue(password_verify($this->owner['password'], $this->readText($this->watcher()->table('users')->where('id', $this->owner['id'])->value('password'))));
        app(ConsumeLink::class)($this->owner['id'], $token, $password);
        $this->assertTrue(password_verify($password, $this->readText($this->watcher()->table('users')->where('id', $this->owner['id'])->value('password'))));
    }

    public function test_op_02_follows_the_common_rules_and_a_failure_after_every_write_step_leaves_nothing(): void
    {
        $this->assertSame(1, Artisan::call('owner:recover', ['--environment' => 'production', '--note' => 'Catatan']));
        $this->assertStringContainsString('is not the environment', Artisan::output());
        $this->assertSame(1, Artisan::call('owner:recover', ['--environment' => 'test']));
        $this->assertStringContainsString('--note', Artisan::output());
        $this->assertSame(1, Artisan::call('owner:recover', ['--environment' => 'test', '--note' => "Catatan\x07"]));
        $this->assertSame(0, $this->watcher()->table('password_reset_tokens')->count());
        $this->assertSame(2, $this->watcher()->table('security_log_entries')->where('actor_process', 'owner_recovery')->where('outcome_code', 'REFUSED')->count());

        $session = $this->signInBrowser($this->owner['email'], $this->owner['password']);
        $this->browse(null);

        foreach (range(1, 4) as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $events = $this->watcher()->table('audit_events')->count();

            $this->assertThrows(static fn () => Artisan::call('owner:recover', ['--environment' => 'test', '--note' => 'Catatan']), InjectedFailure::class);
            $this->assertStringNotContainsString('set-password', Artisan::output(), 'after write step '.$step);
            $this->assertSame(0, $this->watcher()->table('password_reset_tokens')->count(), 'after write step '.$step);
            $this->assertSame(1, $this->watcher()->table('sessions')->where('user_id', $this->owner['id'])->count(), 'after write step '.$step);
            $this->assertSame($events, $this->watcher()->table('audit_events')->count(), 'after write step '.$step);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertNotSame('', $session);
        $this->assertSame(0, Artisan::call('owner:recover', ['--environment' => 'test', '--note' => 'Catatan']));
    }

    public function test_op_03_deactivates_the_named_owner_and_creates_the_new_one_with_its_link(): void
    {
        $session = $this->signInBrowser($this->owner['email'], $this->owner['password']);
        TokoContoh::tokenFor($this->owner['id']);

        $exit = Artisan::call('owner:replace', $this->options());
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);

        // The outgoing account: deactivated, never deleted, its sessions and link gone.
        $outgoing = (array) $this->watcher()->table('users')->where('id', $this->owner['id'])->first();
        $this->assertSame(['Hendra', $this->owner['email'], true, 'DEACTIVATED'], [$outgoing['display_name'], $outgoing['email'], $outgoing['role_super_admin'], $outgoing['status']]);
        $this->assertNotNull($outgoing['deactivated_at']);
        $this->assertSame(0, $this->watcher()->table('sessions')->where('user_id', $this->owner['id'])->count());
        $this->assertSame(0, $this->watcher()->table('password_reset_tokens')->where('email', $this->owner['email'])->count());
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status')->assertStatus(401);

        // Exactly one Owner is active: the new one, without a password until its link.
        $active = $this->watcher()->table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->get(['id', 'display_name', 'email', 'password'])->all();
        $this->assertCount(1, $active);
        $new = (array) $active[0];
        $this->assertSame(['Pemilik Baru', 'pemilik.baru@contoh.test', null], [$new['display_name'], $new['email'], $new['password']]);
        $newId = $new['id'];
        $this->assertIsInt($newId);

        // The events and the log.
        $events = $this->watcher()->table('audit_events')->where('actor_process', 'owner_replacement')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(['OWNER_REPLACEMENT', 'USER_DEACTIVATE', 'USER_CREATE', 'LINK_ISSUE'], array_column($events, 'action'));
        $this->assertSame([$newId, $this->owner['id'], $newId, $newId], array_column($events, 'subject_id'));
        $token = $this->tokenIn($output, $newId);

        foreach ($events as $event) {
            $this->assertSame(['OPERATOR_PROCEDURE', null, 'Pemilik baru sudah diperiksa.'], [$event['actor_kind'], $event['actor_user_id'], $event['operator_note']]);
            $this->assertStringNotContainsString($token, (string) json_encode($event));
        }

        $entries = $this->watcher()->table('security_log_entries')->where('actor_process', 'owner_replacement')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame(['OPERATOR_PROCEDURE_RUN', 'SESSION_REVOKED'], array_column($entries, 'event_type'));
        $this->assertSame([$newId, $this->owner['id']], array_column($entries, 'subject_user_id'));

        // The new Owner signs in through the link, and is the Owner.
        $password = TokoContoh::newPassword();
        app(ConsumeLink::class)($newId, $token, $password);
        Cache::store()->flush();
        $this->browse($this->signInBrowser('pemilik.baru@contoh.test', $password));
        $this->get('/settings')->assertOk();

        // The former Owner cannot sign in.
        Cache::store()->flush();
        $this->browse(null);
        $this->post('/login', ['email' => $this->owner['email'], 'password' => $this->owner['password']])->assertRedirect();
        $this->assertGuest();
    }

    public function test_a_repeated_op_03_is_refused_and_so_is_an_account_that_is_not_the_active_owner(): void
    {
        $this->assertSame(0, Artisan::call('owner:replace', $this->options()));
        $users = $this->watcher()->table('users')->count();

        $this->assertSame(1, Artisan::call('owner:replace', $this->options(['--email' => 'lain@contoh.test'])));
        $this->assertStringContainsString('not the active Owner', Artisan::output());

        // A staff account, or one that does not exist.
        $rina = TokoContoh::person('Rina');
        $users++;
        $this->assertSame(1, Artisan::call('owner:replace', $this->options(['--outgoing' => (string) $rina['id'], '--email' => 'lain@contoh.test'])));
        $this->assertSame(1, Artisan::call('owner:replace', $this->options(['--outgoing' => '999999', '--email' => 'lain@contoh.test'])));

        $this->assertSame($users, $this->watcher()->table('users')->count());
        $this->assertSame(1, $this->watcher()->table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->count());
    }

    public function test_op_03_accepts_only_typed_valid_options_and_refuses_an_email_that_is_held(): void
    {
        $invalid = [
            ['--outgoing' => 'abc'],
            ['--outgoing' => ''],
            ['--outgoing' => '-1'],
            ['--name' => ''],
            ['--name' => str_repeat('n', 101)],
            ['--name' => "Pemilik\nBaru"],
            ['--email' => 'bukan-email'],
            ['--note' => ''],
            ['--environment' => 'production'],
        ];

        foreach ($invalid as $options) {
            $this->assertSame(1, Artisan::call('owner:replace', $this->options($options)), (string) json_encode($options));
        }

        $this->assertSame(1, Artisan::call('owner:replace', $this->options(['--email' => $this->owner['email']])));
        $this->assertStringContainsString('held by an existing account', Artisan::output());
        $this->assertSame('ACTIVE', $this->watcher()->table('users')->where('id', $this->owner['id'])->value('status'));
        $this->assertSame(1, $this->watcher()->table('users')->count());
    }

    public function test_a_failure_after_every_write_step_of_op_03_leaves_nothing_and_prints_no_link(): void
    {
        foreach (range(1, 9) as $step) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));

            $this->assertThrows(fn () => Artisan::call('owner:replace', $this->options()), InjectedFailure::class);
            $this->assertStringNotContainsString('set-password', Artisan::output(), 'after write step '.$step);
            $this->assertSame(1, $this->watcher()->table('users')->count(), 'after write step '.$step);
            $this->assertSame('ACTIVE', $this->watcher()->table('users')->where('id', $this->owner['id'])->value('status'), 'after write step '.$step);
            $this->assertSame(0, $this->watcher()->table('audit_events')->where('actor_process', 'owner_replacement')->count(), 'after write step '.$step);
        }

        $this->app->instance(CommandPoints::class, new FailurePoints);
        $this->assertSame(0, Artisan::call('owner:replace', $this->options()));
    }

    /**
     * @param  array<string, string>  $changes
     * @return array<string, string|int>
     */
    private function options(array $changes = []): array
    {
        return [
            '--environment' => 'test',
            '--outgoing' => (string) $this->owner['id'],
            '--name' => 'Pemilik Baru',
            '--email' => 'pemilik.baru@contoh.test',
            '--note' => 'Pemilik baru sudah diperiksa.',
            ...$changes,
        ];
    }

    /**
     * The token of the link that a procedure printed, for this account.
     */
    private function tokenIn(string $output, int $account): string
    {
        if (preg_match('~/set-password#u=(\d+)&t=([^\s]+)~', $output, $link) !== 1) {
            $this->fail('The link is printed to the terminal: '.$output);
        }

        $this->assertSame((string) $account, $link[1]);

        return rawurldecode($link[2]);
    }
}
