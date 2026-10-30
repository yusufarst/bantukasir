<?php

namespace Tests\Feature\Access;

use App\Actions\Access\ConsumeLink;
use App\Actions\Access\ReplaceOwner;
use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-35 to RC-42, user management (AC-011-01 to AC-011-05; INTERACTION_CONTRACTS
 * "RC-35 to RC-42"; TECHNICAL_RULES section 7.2; SECURITY_MODEL section 3,
 * CAP-25 to CAP-29, CAP-33; BR-USR-01 to BR-USR-12; G-08, G-16; suites ACC01,
 * LINK01, AUTH03; TASK-011 scope items 1 and 2), as L4 tests through the routes.
 * The races are in tests/Concurrency/UserManagementRaceTest.php, the pages in
 * UserPagesTest, the procedures in OwnerProceduresTest.
 */
final class UserManagementTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private ?Connection $watcher = null;

    /** @var list<string> the statements that lock a row of `users` during RC-37 */
    private array $lockStatements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCommands();
    }

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    public function test_rc_35_creates_a_staff_account_without_a_password_and_returns_its_link_once(): void
    {
        $this->asOwner();
        $envelope = $this->envelopeOf('user.create', ['displayName' => 'Budi', 'email' => 'budi@contoh.test', 'roles' => ['cashier', 'operationsAdmin']]);

        $response = $this->postJson('/commands/user.create', $envelope)->assertStatus(201);
        $id = self::number($response->json('result.id'));
        $this->assertJsonChannel($response, 'commands.committed.link', 'owner');
        $this->assertSame('/settings/users/'.$id.'/link', $response->json('result.location'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', self::text($response->json('link.expiresAt')));

        $row = $this->userRow($id);
        $this->assertSame(['Budi', 'budi@contoh.test', null, false, true, true, 'ACTIVE', 1], [$row['display_name'], $row['email'], $row['password'], $row['role_super_admin'], $row['role_operations_admin'], $row['role_cashier'], $row['status'], $row['version']]);

        // The audit events name the Owner, with no token anywhere.
        $token = $this->tokenOf($response, $id);
        $events = $this->eventsOf(self::number($response->json('commandId')));
        $this->assertSame(['USER_CREATE', 'LINK_ISSUE'], array_column($events, 'action'));

        foreach ($events as $event) {
            $this->assertSame(['USER', $this->people['Hendra']['id'], 'Hendra'], [$event['actor_kind'], $event['actor_user_id'], $event['actor_display_name']]);
            $this->assertStringNotContainsString($token, (string) json_encode($event));
        }

        // Stored only as a hash.
        $this->assertNotSame($token, $this->watcher()->table('password_reset_tokens')->where('email', 'budi@contoh.test')->value('token'));

        // A repeat of the same request returns the original result, without the
        // link and leading to the account; a status check too (SEC-ACC-05).
        $repeat = $this->postJson('/commands/user.create', $envelope)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.location', '/settings/users/'.$id);
        $this->assertJsonChannel($repeat, 'commands.committed', 'owner', [$token]);
        $check = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/settings/users/'.$id);
        $this->assertJsonChannel($check, 'commands.resolve', 'owner', [$token]);
        $this->assertSame(1, $this->watcher()->table('users')->where('email', 'budi@contoh.test')->count());

        // The link sets the first password, once.
        $password = TokoContoh::newPassword();
        $this->browse(null);
        $this->post('/set-password', ['user' => (string) $id, 'token' => $token, 'password' => $password, 'password_confirmation' => $password])->assertRedirect('/set-password');
        $this->assertNotNull($this->watcher()->table('users')->where('id', $id)->value('password'));
    }

    public function test_rc_35_refuses_an_invalid_form_a_super_admin_role_and_an_email_that_is_held(): void
    {
        $this->asOwner();
        $before = $this->facts();
        $valid = ['displayName' => 'Budi', 'email' => 'budi@contoh.test', 'roles' => ['cashier']];
        $cases = [
            'payload.displayName' => [['displayName' => null], 'ux.validation.required'],
            'payload.email' => [['email' => 'bukan-email'], 'ux.validation.email'],
            'payload.roles' => [['roles' => []], 'ux.validation.required'],
        ];

        foreach ($cases as $path => [$change, $key]) {
            $response = $this->postJson('/commands/user.create', $this->envelopeOf('user.create', [...$valid, ...$change]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $this->assertSame($key, self::firstKey($response, $path), $path);
        }

        // Neither SUPER_ADMIN by any name, nor a field of the table, nor a reserved
        // address, nor a control character.
        foreach ([['roles' => ['superAdmin']], ['roles' => ['SUPER_ADMIN']], ['roles' => ['cashier', 'cashier']], ['role_super_admin' => true], ['email' => 'anonymized-1@users.invalid'], ['displayName' => "Bu\u{0007}di"], ['displayName' => str_repeat('b', 101)]] as $change) {
            $this->postJson('/commands/user.create', $this->envelopeOf('user.create', [...$valid, ...$change]))->assertStatus(422);
        }

        // Held by an active account, without regard to letter case or outer spaces.
        foreach (['rina@contoh.test', ' RINA@contoh.TEST '] as $email) {
            $refused = $this->postJson('/commands/user.create', $this->envelopeOf('user.create', [...$valid, 'email' => $email]))->assertStatus(422);
            $this->assertSame('access.user_form.email_taken', self::firstKey($refused, 'payload.email'));
        }

        $this->assertSame($before, $this->facts());

        // Held by a deactivated account, with the advice to reactivate or anonymize it.
        $rina = $this->people['Rina']['id'];
        $this->send('user.deactivate', ['userId' => $rina], $this->versionOf($rina))->assertStatus(201);
        $refused = $this->postJson('/commands/user.create', $this->envelopeOf('user.create', [...$valid, 'email' => 'rina@contoh.test']))->assertStatus(422);
        $this->assertSame('access.user_form.email_taken_deactivated', self::firstKey($refused, 'payload.email'));

        // Anonymized, its email is free for a new account (D-043).
        $this->send('user.anonymize', ['userId' => $rina, 'reason' => 'Permintaan orang yang bersangkutan.'], $this->versionOf($rina))->assertStatus(201);
        $this->postJson('/commands/user.create', $this->envelopeOf('user.create', [...$valid, 'email' => 'rina@contoh.test']))->assertStatus(201);
    }

    public function test_rc_36_changes_details_and_roles_with_the_revocations_of_each(): void
    {
        $this->asOwner();
        $budi = TokoContoh::staff('Budi');
        $session = $this->sessionOfPerson($budi['email'], self::text($budi['password']));

        // A display name alone: the link and the sessions stay.
        $link = TokoContoh::tokenFor($budi['id']);
        $this->asOwner();
        $response = $this->send('user.update', ['userId' => $budi['id'], 'displayName' => 'Budi Santoso', 'email' => $budi['email'], 'roles' => ['cashier']], 1)->assertStatus(201);
        $this->assertSame('/settings/users/'.$budi['id'], $response->json('result.location'));
        $this->assertSame(['DETAILS_CHANGE'], array_column($this->eventsOf(self::number($response->json('commandId'))), 'action'));
        $this->assertSame(1, $this->linksOf($budi['email']));
        $this->assertSame(1, $this->sessionsOf($budi['id']));
        $this->assertSame(2, $this->userRow($budi['id'])['version']);

        // An email change revokes the link, under the old address, and leaves the
        // sessions (SEC-ACC-04); the old token matches nothing afterwards.
        $response = $this->send('user.update', ['userId' => $budi['id'], 'displayName' => 'Budi Santoso', 'email' => 'budi.baru@contoh.test', 'roles' => ['cashier']], 2)->assertStatus(201);
        $this->assertSame(0, $this->linksOf($budi['email']));
        $this->assertSame(0, $this->linksOf('budi.baru@contoh.test'));
        $this->assertSame(1, $this->sessionsOf($budi['id']));
        $this->assertSame([['changed' => ['email']]], array_map(static fn (array $event): mixed => json_decode(self::text($event['change_data']), true), $this->eventsOf(self::number($response->json('commandId')))));
        $this->browse(null);
        $this->post('/set-password', ['user' => (string) $budi['id'], 'token' => $link, 'password' => 'Aa1#aaaa', 'password_confirmation' => 'Aa1#aaaa']);
        $this->assertTrue(password_verify(self::text($budi['password']), $this->readText($this->watcher()->table('users')->where('id', $budi['id'])->value('password'))));

        // A role change revokes the sessions and the link: no old permission
        // survives, and the security log says so.
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status')->assertOk();
        TokoContoh::tokenFor($budi['id']);
        $this->asOwner();
        $response = $this->send('user.update', ['userId' => $budi['id'], 'displayName' => 'Budi Santoso', 'email' => 'budi.baru@contoh.test', 'roles' => ['operationsAdmin']], 3)->assertStatus(201);
        $this->assertSame(['ROLE_CHANGE'], array_column($this->eventsOf(self::number($response->json('commandId'))), 'action'));
        $this->assertSame(0, $this->sessionsOf($budi['id']));
        $this->assertSame(0, $this->linksOf('budi.baru@contoh.test'));
        $row = $this->userRow($budi['id']);
        $this->assertSame([false, true], [$row['role_cashier'], $row['role_operations_admin']]);
        $this->assertSame(1, $this->watcher()->table('security_log_entries')->where('event_type', 'SESSION_REVOKED')->where('subject_user_id', $budi['id'])->count());
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status')->assertStatus(401);
    }

    public function test_rc_36_refuses_a_stale_version_nothing_to_change_a_held_email_and_every_super_admin_account(): void
    {
        $this->asOwner();
        $rina = $this->people['Rina']['id'];
        $hendra = $this->people['Hendra']['id'];
        $version = $this->versionOf($rina);
        $before = $this->facts();
        $same = ['userId' => $rina, 'displayName' => 'Rina', 'email' => 'rina@contoh.test', 'roles' => ['cashier']];

        $this->send('user.update', [...$same, 'displayName' => 'Rina Baru'], $version + 1)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send('user.update', $same, $version)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $held = $this->send('user.update', [...$same, 'email' => 'agus@contoh.test'], $version)->assertStatus(422);
        $this->assertSame('access.user_form.email_taken', self::firstKey($held, 'payload.email'));
        $this->send('user.update', [...$same, 'userId' => 999999], $version)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        // The active Owner's own account, through any route of a target (CAP-33).
        $refusals = $this->watcher()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();

        foreach ([
            ['user.update', [...$same, 'userId' => $hendra]],
            ['user.deactivate', ['userId' => $hendra]],
            ['user.anonymize', ['userId' => $hendra, 'reason' => 'Coba']],
            ['user.reactivate', ['userId' => $hendra]],
            ['user.issue_link', ['userId' => $hendra, 'kind' => 'RESET']],
            ['user.revoke_link', ['userId' => $hendra]],
        ] as [$operation, $payload]) {
            $this->send($operation, $payload, $this->versionOf($hendra))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        }

        $this->assertSame($refusals + 6, $this->watcher()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count());
        $this->assertSame($before, $this->facts());
    }

    public function test_rc_37_changes_the_owners_own_details_locked_once_and_revokes_the_link_on_an_email_change(): void
    {
        $this->asOwner();
        $hendra = $this->people['Hendra']['id'];
        $session = $this->watcher()->table('sessions')->where('user_id', $hendra)->count();
        TokoContoh::tokenFor($hendra);
        $version = $this->versionOf($hendra);

        // The account is locked once, with the update lock (TR-LOCK-05).
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, '"users"') && (str_contains($query->sql, 'for share') || str_contains($query->sql, 'for no key update'))) {
                $this->lockStatements[] = $query->sql;
            }
        });

        $response = $this->send('owner.update_details', ['displayName' => 'Hendra Baru', 'email' => 'hendra.baru@contoh.test'], $version)->assertStatus(201)->assertJsonPath('result.location', '/account');
        $this->assertCount(1, $this->lockStatements);
        $this->assertStringContainsString('for no key update', $this->lockStatements[0] ?? '');

        $row = $this->userRow($hendra);
        $this->assertSame(['Hendra Baru', 'hendra.baru@contoh.test', true, 'ACTIVE', $version + 1], [$row['display_name'], $row['email'], $row['role_super_admin'], $row['status'], $row['version']]);
        $this->assertSame(0, $this->linksOf('hendra@contoh.test'));
        $this->assertSame($session, $this->sessionsOf($hendra), 'The Owner stays signed in.');
        $events = $this->eventsOf(self::number($response->json('commandId')));
        $this->assertSame(['DETAILS_CHANGE'], array_column($events, 'action'));
        $this->assertSame('Hendra', $events[0]['actor_display_name'] ?? null, 'The frozen name is the one of the transaction (G-16).');

        // Refusals.
        $this->send('owner.update_details', ['displayName' => 'Hendra Lagi', 'email' => 'hendra.baru@contoh.test'], $version)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->send('owner.update_details', ['displayName' => 'Hendra Baru', 'email' => 'hendra.baru@contoh.test'], $version + 1)->assertStatus(422);
        $held = $this->send('owner.update_details', ['displayName' => 'Hendra Baru', 'email' => 'rina@contoh.test'], $version + 1)->assertStatus(422);
        $this->assertSame('access.user_form.email_taken', self::firstKey($held, 'payload.email'));
        $this->postJson('/commands/owner.update_details', $this->envelopeOf('owner.update_details', ['displayName' => 'X', 'email' => 'x@contoh.test', 'userId' => $this->people['Rina']['id']], ['userVersion' => 1]))->assertStatus(422);
    }

    public function test_rc_38_deactivates_at_once_and_rc_39_reactivates(): void
    {
        $this->asOwner();
        $budi = TokoContoh::staff('Budi');
        $session = $this->sessionOfPerson($budi['email'], self::text($budi['password']));
        TokoContoh::tokenFor($budi['id']);
        $this->asOwner();

        $response = $this->send('user.deactivate', ['userId' => $budi['id']], 1)->assertStatus(201);
        $this->assertSame('/settings/users/'.$budi['id'], $response->json('result.location'));
        $row = $this->userRow($budi['id']);
        $this->assertSame(['DEACTIVATED', 2], [$row['status'], $row['version']]);
        $this->assertNotNull($row['deactivated_at']);
        $this->assertSame(0, $this->sessionsOf($budi['id']));
        $this->assertSame(0, $this->linksOf($budi['email']));
        $this->assertSame(['USER_DEACTIVATE'], array_column($this->eventsOf(self::number($response->json('commandId'))), 'action'));
        $this->assertSame(1, $this->watcher()->table('security_log_entries')->where('event_type', 'SESSION_REVOKED')->where('subject_user_id', $budi['id'])->where('actor_user_id', $this->people['Hendra']['id'])->count());

        // Ended now: neither the old session nor a new sign-in works.
        $this->browse($session);
        $this->getJson('/user/confirmed-password-status')->assertStatus(401);
        $this->browse(null);
        $this->post('/login', ['email' => $budi['email'], 'password' => $budi['password']])->assertRedirect();
        $this->assertGuest();

        $this->asOwner();
        $this->send('user.deactivate', ['userId' => $budi['id']], 2)->assertStatus(422);
        $this->send('user.issue_link', ['userId' => $budi['id'], 'kind' => 'RESET'], 2)->assertStatus(422);

        // Reactivated, with its current roles and its password.
        $this->send('user.reactivate', ['userId' => $budi['id']], 1)->assertStatus(409);
        $response = $this->send('user.reactivate', ['userId' => $budi['id']], 2)->assertStatus(201);
        $this->assertSame(['USER_REACTIVATE'], array_column($this->eventsOf(self::number($response->json('commandId'))), 'action'));
        $row = $this->userRow($budi['id']);
        $this->assertSame(['ACTIVE', null, 3], [$row['status'], $row['deactivated_at'], $row['version']]);
        $this->send('user.reactivate', ['userId' => $budi['id']], 3)->assertStatus(422);
        $this->browse(null);
        $this->post('/login', ['email' => $budi['email'], 'password' => $budi['password']])->assertRedirect('/');
    }

    public function test_rc_40_anonymizes_a_deactivated_account_only_with_a_reason_and_keeps_every_frozen_name(): void
    {
        $this->asOwner();
        $rina = $this->people['Rina']['id'];
        // The name that the account's first link consumption froze (G-16).
        $frozen = $this->watcher()->table('audit_events')->where('actor_user_id', $rina)->where('action', 'LINK_CONSUME')->pluck('actor_display_name')->all();
        $this->assertSame(['Rina'], $frozen);

        // Only after it is deactivated, and only with a reason.
        $this->send('user.anonymize', ['userId' => $rina, 'reason' => 'Permintaan orang yang bersangkutan.'], $this->versionOf($rina))->assertStatus(422);
        $this->send('user.deactivate', ['userId' => $rina], $this->versionOf($rina))->assertStatus(201);
        $version = $this->versionOf($rina);
        $refused = $this->send('user.anonymize', ['userId' => $rina, 'reason' => null], $version)->assertStatus(422);
        $this->assertSame('ux.validation.required', self::firstKey($refused, 'payload.reason'));
        $this->send('user.anonymize', ['userId' => $rina, 'reason' => str_repeat('a', 1001)], $version)->assertStatus(422);
        $this->send('user.anonymize', ['userId' => $rina, 'reason' => 'Lama'], $version - 1)->assertStatus(409);

        $response = $this->send('user.anonymize', ['userId' => $rina, 'reason' => 'Permintaan orang yang bersangkutan.'], $version)->assertStatus(201);
        $row = $this->userRow($rina);
        $this->assertSame(['Pengguna dianonimkan '.$rina, 'anonymized-'.$rina.'@users.invalid', null, 'ANONYMIZED'], [$row['display_name'], $row['email'], $row['password'], $row['status']]);
        $this->assertNotNull($row['anonymized_at']);
        $this->assertNotNull($row['deactivated_at']);
        $event = $this->eventsOf(self::number($response->json('commandId')))[0] ?? [];
        $this->assertSame(['USER_ANONYMIZE', 'Permintaan orang yang bersangkutan.', 'Hendra'], [$event['action'] ?? null, $event['reason'] ?? null, $event['actor_display_name'] ?? null]);

        // Every frozen name stays; the account is never reactivated or changed again.
        $this->assertSame($frozen, $this->watcher()->table('audit_events')->where('actor_user_id', $rina)->where('action', 'LINK_CONSUME')->pluck('actor_display_name')->all());
        $version = $this->versionOf($rina);
        $this->send('user.reactivate', ['userId' => $rina], $version)->assertStatus(422);
        $this->send('user.anonymize', ['userId' => $rina, 'reason' => 'Lagi'], $version)->assertStatus(422);
        $this->send('user.update', ['userId' => $rina, 'displayName' => 'Rina', 'email' => 'rina2@contoh.test', 'roles' => ['cashier']], $version)->assertStatus(422);
        $this->browse(null);
        $this->post('/login', ['email' => 'rina@contoh.test', 'password' => $this->people['Rina']['password']])->assertRedirect();
        $this->assertGuest();
    }

    public function test_rc_41_issues_a_link_of_the_right_kind_replacing_the_earlier_one_at_most_once_in_60_seconds(): void
    {
        $this->asOwner();
        $rina = $this->people['Rina'];
        $earlier = TokoContoh::tokenFor($rina['id']);
        $this->age($rina['email']);

        // The wrong kind for the account is stale; a password is set. The route's
        // bucket counts every request of an account, so each case empties it.
        $this->send('user.issue_link', ['userId' => $rina['id'], 'kind' => 'FIRST_PASSWORD'], $this->versionOf($rina['id']))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        Cache::store()->flush();
        $this->send('user.issue_link', ['userId' => $rina['id'], 'kind' => 'OTHER'], $this->versionOf($rina['id']))->assertStatus(422);

        Cache::store()->flush();
        $response = $this->send('user.issue_link', ['userId' => $rina['id'], 'kind' => 'RESET'], $this->versionOf($rina['id']))->assertStatus(201);
        $this->assertJsonChannel($response, 'commands.committed.link', 'owner');
        $this->assertSame('/settings/users/'.$rina['id'].'/link', $response->json('result.location'));
        $token = $this->tokenOf($response, $rina['id']);
        $this->assertNotSame($earlier, $token);
        $this->assertSame(1, $this->linksOf($rina['email']));
        $event = $this->eventsOf(self::number($response->json('commandId')))[0] ?? [];
        $this->assertSame('LINK_ISSUE', $event['action'] ?? null);
        $this->assertSame(['kind' => 'RESET'], json_decode(self::text($event['change_data'] ?? null), true));

        // At most one per account per 60 seconds: the route's bucket runs nothing,
        // and with the bucket emptied the broker's own check refuses.
        $this->send('user.issue_link', ['userId' => $rina['id'], 'kind' => 'RESET'], $this->versionOf($rina['id']))->assertStatus(429)->assertHeader('Retry-After');
        Cache::store()->flush();
        $this->send('user.issue_link', ['userId' => $rina['id'], 'kind' => 'RESET'], $this->versionOf($rina['id']))->assertStatus(429)->assertJsonPath('code', 'THROTTLED')->assertHeader('Retry-After', '60');

        // The earlier link no longer works; the new one does.
        $password = TokoContoh::newPassword();
        $this->browse(null);
        $this->post('/set-password', ['user' => (string) $rina['id'], 'token' => $earlier, 'password' => $password, 'password_confirmation' => $password]);
        $this->assertTrue(password_verify($rina['password'], $this->readText($this->watcher()->table('users')->where('id', $rina['id'])->value('password'))));
        $this->post('/set-password', ['user' => (string) $rina['id'], 'token' => $token, 'password' => $password, 'password_confirmation' => $password]);
        $this->assertTrue(password_verify($password, $this->readText($this->watcher()->table('users')->where('id', $rina['id'])->value('password'))));

        // FIRST_PASSWORD for an account that has not set a password.
        $this->asOwner();
        $budi = TokoContoh::staff('Budi', ['cashier'], false);
        $this->age($budi['email']);
        Cache::store()->flush();
        $this->send('user.issue_link', ['userId' => $budi['id'], 'kind' => 'RESET'], 1)->assertStatus(409);
        Cache::store()->flush();
        $this->send('user.issue_link', ['userId' => $budi['id'], 'kind' => 'FIRST_PASSWORD'], 1)->assertStatus(201);
    }

    public function test_rc_42_revokes_a_link_and_finds_none_as_stale(): void
    {
        $this->asOwner();
        $rina = $this->people['Rina'];

        $this->send('user.revoke_link', ['userId' => $rina['id']], $this->versionOf($rina['id']))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');

        $token = TokoContoh::tokenFor($rina['id']);
        $response = $this->send('user.revoke_link', ['userId' => $rina['id']], $this->versionOf($rina['id']))->assertStatus(201);
        $this->assertSame(['LINK_REVOKE'], array_column($this->eventsOf(self::number($response->json('commandId'))), 'action'));
        $this->assertSame(0, $this->linksOf($rina['email']));
        $this->assertSame($this->people['Rina']['id'], $response->json('result.id'));

        // An expired link is no link.
        TokoContoh::tokenFor($rina['id']);
        $this->migration()->table('password_reset_tokens')->where('email', $rina['email'])->update(['created_at' => now()->subMinutes(61)]);
        $this->send('user.revoke_link', ['userId' => $rina['id']], $this->versionOf($rina['id']))->assertStatus(409);

        $this->browse(null);
        $password = TokoContoh::newPassword();
        $this->post('/set-password', ['user' => (string) $rina['id'], 'token' => $token, 'password' => $password, 'password_confirmation' => $password]);
        $this->assertTrue(password_verify($rina['password'], $this->readText($this->watcher()->table('users')->where('id', $rina['id'])->value('password'))));
    }

    public function test_a_former_owner_may_be_anonymized_but_never_reactivated_or_changed(): void
    {
        $hendra = $this->people['Hendra'];
        $new = app(ReplaceOwner::class)($hendra['id'], 'Pemilik Baru', 'pemilik.baru@contoh.test', 'Pergantian Pemilik untuk pengujian.');
        $password = TokoContoh::newPassword();
        app(ConsumeLink::class)($new->accountId, rawurldecode(self::tokenInLink($new->link)), $password);

        $this->browse($this->signInBrowser('pemilik.baru@contoh.test', $password));
        $this->postJson('/user/confirm-password', ['password' => $password])->assertStatus(201);
        $version = $this->versionOf($hendra['id']);

        // Reactivating a former Owner is refused (AC-011-02), as is any change.
        $this->send('user.reactivate', ['userId' => $hendra['id']], $version)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->send('user.update', ['userId' => $hendra['id'], 'displayName' => 'Hendra', 'email' => 'hendra@contoh.test', 'roles' => ['cashier']], $version)->assertStatus(403);
        $this->send('user.issue_link', ['userId' => $hendra['id'], 'kind' => 'RESET'], $version)->assertStatus(403);
        $this->assertSame(1, $this->watcher()->table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->count());

        $this->send('user.anonymize', ['userId' => $hendra['id'], 'reason' => 'Toko berganti pemilik.'], $version)->assertStatus(201);
        $row = $this->userRow($hendra['id']);
        $this->assertSame(['ANONYMIZED', true], [$row['status'], $row['role_super_admin']]);

        // The revision the former Owner froze keeps the name (G-16).
        $this->assertSame('Hendra', $this->watcher()->table('business_profile_revisions')->orderBy('id')->value('actor_display_name'));
    }

    public function test_every_other_role_is_refused_each_command_and_without_recent_authentication_nothing_runs(): void
    {
        // Without the sign-in sessions, which the test itself starts.
        $before = array_diff_key($this->facts(), ['sessions' => 0]);
        $rina = $this->people['Rina']['id'];
        $commands = [
            ['user.create', ['displayName' => 'Budi', 'email' => 'budi@contoh.test', 'roles' => ['cashier']], []],
            ['user.update', ['userId' => $rina, 'displayName' => 'Rina Baru', 'email' => 'rina@contoh.test', 'roles' => ['cashier']], ['userVersion' => 1]],
            ['owner.update_details', ['displayName' => 'Baru', 'email' => 'baru@contoh.test'], ['userVersion' => 1]],
            ['user.deactivate', ['userId' => $rina], ['userVersion' => 1]],
            ['user.reactivate', ['userId' => $rina], ['userVersion' => 1]],
            ['user.anonymize', ['userId' => $rina, 'reason' => 'Coba'], ['userVersion' => 1]],
            ['user.issue_link', ['userId' => $rina, 'kind' => 'RESET'], ['userVersion' => 1]],
            ['user.revoke_link', ['userId' => $rina], ['userVersion' => 1]],
        ];

        $this->as('Hendra');

        foreach ($commands as [$operation, $payload, $expected]) {
            $this->postJson('/commands/'.$operation, $this->envelopeOf($operation, $payload, $expected))->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        }

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);

            foreach ($commands as [$operation, $payload, $expected]) {
                $this->postJson('/commands/'.$operation, $this->envelopeOf($operation, $payload, $expected))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            }
        }

        $this->assertSame($before, array_diff_key($this->facts(), ['sessions' => 0]));
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', 'like', 'user.%')->count());
    }

    public function test_a_failure_after_every_write_step_leaves_no_fact_and_the_receipt_pending(): void
    {
        $this->asOwner();
        $rina = $this->people['Rina'];
        $agus = $this->people['Agus'];
        $hendra = $this->people['Hendra'];

        // TS-21: each operation, in its longest form, with its write steps.
        $runs = [
            ['user.create', 5, fn (): array => [['displayName' => 'Budi', 'email' => 'budi@contoh.test', 'roles' => ['cashier']], null]],
            ['user.update', 6, fn (): array => [['userId' => $rina['id'], 'displayName' => 'Rina Baru', 'email' => 'rina.baru@contoh.test', 'roles' => ['operationsAdmin']], $this->versionOf($rina['id'])]],
            ['owner.update_details', 4, fn (): array => [['displayName' => 'Hendra Baru', 'email' => 'hendra.baru@contoh.test'], $this->versionOf($hendra['id'])]],
            ['user.deactivate', 5, fn (): array => [['userId' => $agus['id']], $this->versionOf($agus['id'])]],
            ['user.issue_link', 3, fn (): array => [['userId' => $agus['id'], 'kind' => 'RESET'], $this->versionOf($agus['id'])]],
            ['user.revoke_link', 3, fn (): array => [['userId' => $agus['id']], $this->versionOf($agus['id'])]],
        ];

        foreach ($runs as [$operation, $steps, $make]) {
            if ($operation === 'user.revoke_link') {
                TokoContoh::tokenFor($agus['id']);
            }

            foreach (range(1, $steps) as $step) {
                $this->age($agus['email']);
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                Cache::store()->flush();
                $before = $this->facts();
                [$payload, $version] = $make();

                $this->send($operation, $payload, $version)->assertStatus(500);

                $this->assertSame($before, $this->facts(), sprintf('%s after write step %d', $operation, $step));
                $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', $operation)->orderByDesc('id')->value('outcome'));
            }

            $this->app->instance(CommandPoints::class, new FailurePoints);
        }

        // The other two, on an account made for them.
        $budi = TokoContoh::staff('Budi2');
        $this->send('user.deactivate', ['userId' => $budi['id']], 1)->assertStatus(201);

        foreach ([['user.reactivate', 3, ['userId' => $budi['id']]], ['user.anonymize', 5, ['userId' => $budi['id'], 'reason' => 'Permintaan.']]] as [$operation, $steps, $payload]) {
            foreach (range(1, $steps) as $step) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = $this->facts();

                $this->send($operation, $payload, 2)->assertStatus(500);

                $this->assertSame($before, $this->facts(), sprintf('%s after write step %d', $operation, $step));
            }

            $this->app->instance(CommandPoints::class, new FailurePoints);
        }

        $this->send('user.anonymize', ['userId' => $budi['id'], 'reason' => 'Permintaan.'], 2)->assertStatus(201);
    }

    /**
     * Signs the Owner in with a recent password confirmation (UXS-11).
     */
    private function asOwner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    private function sessionOfPerson(string $email, string $password): string
    {
        Cache::store()->flush();

        return $this->signInBrowser($email, $password);
    }

    /**
     * An envelope of a user-management command.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function envelopeOf(string $operation, array $payload, array $expected = []): array
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

    /**
     * A command at this expected version, or RC-35 without one.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function send(string $operation, array $payload, ?int $version): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelopeOf($operation, $payload, $version === null ? [] : ['userVersion' => $version]));
    }

    private function versionOf(int $id): int
    {
        return self::number($this->watcher()->table('users')->where('id', $id)->value('version'));
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(int $id): array
    {
        return (array) $this->watcher()->table('users')->where('id', $id)->first();
    }

    private function linksOf(string $email): int
    {
        return $this->watcher()->table('password_reset_tokens')->where('email', $email)->count();
    }

    private function sessionsOf(int $id): int
    {
        return $this->watcher()->table('sessions')->where('user_id', $id)->count();
    }

    /**
     * Makes the account's link older than the broker's 60 seconds.
     */
    private function age(string $email): void
    {
        $this->migration()->table('password_reset_tokens')->where('email', $email)->update(['created_at' => now()->subMinutes(5)]);
    }

    /**
     * The audit events of a command, in order.
     *
     * @return list<array<string, mixed>>
     */
    private function eventsOf(int $commandId): array
    {
        return array_values(array_map(static fn (object $row): array => (array) $row, $this->watcher()->table('audit_events')->where('command_id', $commandId)->orderBy('id')->get()->all()));
    }

    /**
     * What a refused command must leave as it was.
     *
     * @return array<string, mixed>
     */
    private function facts(): array
    {
        $observer = $this->watcher();

        return [
            // The same session for all: see watcher().
            'users' => $observer->table('users')->orderBy('id')->get(['id', 'display_name', 'email', 'status', 'version', 'role_cashier', 'role_operations_admin'])->map(static fn (object $row): array => (array) $row)->all(),
            'links' => $observer->table('password_reset_tokens')->orderBy('email')->pluck('email')->all(),
            'sessions' => $observer->table('sessions')->count(),
            'audit' => $observer->table('audit_events')->count(),
            'identities' => $observer->table('command_identities')->count(),
            'results' => $observer->table('command_results')->count(),
        ];
    }

    /**
     * The token in the fragment of an answer's link, for this account.
     *
     * @param  TestResponse<Response>  $response
     */
    private function tokenOf(TestResponse $response, int $id): string
    {
        $url = self::text($response->json('link.url'));
        $this->assertStringContainsString('/set-password#u='.$id.'&t=', $url);
        $this->assertStringNotContainsString('?', $url);

        return rawurldecode(self::tokenInLink($url));
    }

    private static function tokenInLink(string $url): string
    {
        return substr($url, (int) strrpos($url, '&t=') + 3);
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
