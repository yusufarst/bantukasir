<?php

namespace Tests\Concurrency;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\ConsumeLinkScenario;
use Tests\Harness\Access\ReplaceOwnerScenario;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;

/**
 * The races of user management and of the Owner's replacement (TECHNICAL_RULES
 * section 8; AC-011-02, AC-011-03; EXECUTION_CONTEXT section 10; TASK-011 section
 * 7), each with processes of their own connections under barrier control:
 *
 * - RACE-01 and RACE-02 for each operation of RC-35 to RC-42: the same key, or
 *   another key of the same intent, runs once;
 * - RACE-04: a deactivation or a role change against a running command, in both
 *   orders: the one that holds the actor's row first is in force;
 * - RACE-16, the account side: an anonymization against a reactivation, in both
 *   orders: one commits, the other is stale;
 * - RACE-20: a consumption against a revocation, a re-issue or a deactivation, in
 *   both orders: whichever commits first wins, a link is consumed at most once;
 * - RACE-21: the Owner's replacement against a command of the outgoing Owner, in
 *   both orders; RACE-30: two replacements at once: one runs, the other is refused,
 *   and exactly one Owner is active afterwards.
 */
final class UserManagementRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    private ?Connection $watcher = null;

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Probe::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_01_and_race_02_each_operation_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        foreach ($this->everyOperation() as $name => [$operation, $make]) {
            foreach (['same key', 'another key'] as $case) {
                [$payload, $expected] = $make($name.Str::lower(str_replace(' ', '', $case)));
                $intent = (string) Str::uuid();
                $key = (string) Str::uuid();
                $first = $this->start(UserCommandScenario::class, $this->command($operation, $payload, $expected, $intent, $key), ['after_identity']);
                $second = $this->start(UserCommandScenario::class, $this->command($operation, $payload, $expected, $intent, $case === 'same key' ? $key : (string) Str::uuid()));
                Worker::ready($first, $second);

                $first->run()->awaitBarrier('after_identity');
                $second->run()->awaitBlocked($this->watcher());
                $first->release();

                $original = $first->finish()->result ?? [];
                $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], $operation.' '.$case);
                $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null], $second->finish()->result, $operation.' '.$case);
                $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $operation.' '.$case);
            }
        }
    }

    public function test_race_04_a_deactivation_or_a_role_change_against_a_running_command_in_both_orders(): void
    {
        foreach (['deactivation', 'role change'] as $change) {
            // The running command holds the actor's row first: it commits, then
            // the change does.
            $agus = TokoContoh::staff('Satu'.Str::studly(str_replace(' ', '', $change)), ['operationsAdmin']);
            $probe = $this->start(ProbeScenario::class, $this->probe($agus['id']), ['after_lock:2.1']);
            $writer = $this->start(UserCommandScenario::class, $this->changeOf($change, $agus['id']));
            Worker::ready($probe, $writer);

            $probe->run()->awaitBarrier('after_lock:2.1');
            $writer->run()->awaitBlocked($this->watcher());
            $probe->release();

            $this->assertSame('COMMITTED', ($probe->finish()->result ?? [])['outcome'] ?? null, $change);
            $this->assertSame('COMMITTED', ($writer->finish()->result ?? [])['outcome'] ?? null, $change);

            // The change holds the row first: the command is refused before any fact.
            $rina = TokoContoh::staff('Dua'.Str::studly(str_replace(' ', '', $change)), ['operationsAdmin']);
            $facts = $this->watcher()->table('harness_probe_facts')->count();
            $writer = $this->start(UserCommandScenario::class, $this->changeOf($change, $rina['id']), ['after_lock:2.2']);
            $probe = $this->start(ProbeScenario::class, $this->probe($rina['id']));
            Worker::ready($writer, $probe);

            $writer->run()->awaitBarrier('after_lock:2.2');
            $probe->run()->awaitBlocked($this->watcher());
            $writer->release();

            $this->assertSame('COMMITTED', ($writer->finish()->result ?? [])['outcome'] ?? null, $change);
            $this->assertSame(['refused' => 'FORBIDDEN'], $probe->finish()->result, $change);
            $this->assertSame($facts, $this->watcher()->table('harness_probe_facts')->count(), $change);
        }
    }

    public function test_race_16_of_an_anonymization_and_a_reactivation_one_commits_and_the_other_is_stale(): void
    {
        foreach (['anonymization first', 'reactivation first'] as $order) {
            $target = TokoContoh::staff(Str::studly(str_replace(' ', '', $order)));
            $version = $this->deactivate($target['id']);
            $anonymize = $this->command('user.anonymize', ['userId' => $target['id'], 'reason' => 'Permintaan orang yang bersangkutan.'], ['userVersion' => $version]);
            $reactivate = $this->command('user.reactivate', ['userId' => $target['id']], ['userVersion' => $version]);
            [$firstArguments, $secondArguments] = $order === 'anonymization first' ? [$anonymize, $reactivate] : [$reactivate, $anonymize];

            $first = $this->start(UserCommandScenario::class, $firstArguments, ['after_lock:2.2']);
            $second = $this->start(UserCommandScenario::class, $secondArguments);
            Worker::ready($first, $second);

            $first->run()->awaitBarrier('after_lock:2.2');
            $second->run()->awaitBlocked($this->watcher());
            $first->release();

            $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null, $order);
            $this->assertSame(['refused' => 'STALE_VERSION'], $second->finish()->result, $order);
            $this->assertSame($order === 'anonymization first' ? 'ANONYMIZED' : 'ACTIVE', $this->watcher()->table('users')->where('id', $target['id'])->value('status'), $order);
        }
    }

    public function test_race_20_a_consumption_against_a_revocation_a_reissue_or_a_deactivation_in_both_orders(): void
    {
        foreach (['user.revoke_link', 'user.issue_link', 'user.deactivate'] as $operation) {
            // The writer takes the lock first: the link is refused LINK_INVALID.
            $target = TokoContoh::staff(Str::studly(str_replace(['.', '_'], '', 'a'.$operation)));
            $token = $this->agedLink($target['id'], $target['email']);
            $writer = $this->start(UserCommandScenario::class, $this->linkWriter($operation, $target['id']), ['after_lock:2.2']);
            $consume = $this->start(ConsumeLinkScenario::class, ['user' => $target['id'], 'token' => $token, 'password' => TokoContoh::newPassword()]);
            Worker::ready($writer, $consume);

            $writer->run()->awaitBarrier('after_lock:2.2');
            $consume->run()->awaitBlocked($this->watcher());
            $writer->release();

            $this->assertSame('COMMITTED', ($writer->finish()->result ?? [])['outcome'] ?? null, $operation);
            $this->assertSame(['outcome' => 'LINK_INVALID'], $consume->finish()->result, $operation);

            // The consumption takes the lock first: it wins, and a revocation
            // finds no link; a re-issue and a deactivation still run.
            $target = TokoContoh::staff(Str::studly(str_replace(['.', '_'], '', 'b'.$operation)));
            $token = $this->agedLink($target['id'], $target['email']);
            $password = TokoContoh::newPassword();
            $consume = $this->start(ConsumeLinkScenario::class, ['user' => $target['id'], 'token' => $token, 'password' => $password], ['after_lock:2.2']);
            $writer = $this->start(UserCommandScenario::class, $this->linkWriter($operation, $target['id']));
            Worker::ready($consume, $writer);

            $consume->run()->awaitBarrier('after_lock:2.2');
            $writer->run()->awaitBlocked($this->watcher());
            $consume->release();

            $this->assertSame(['outcome' => 'CONSUMED'], $consume->finish()->result, $operation);

            if ($operation === 'user.revoke_link') {
                $this->assertSame(['refused' => 'STALE_VERSION'], $writer->finish()->result);
            } else {
                $this->assertSame('COMMITTED', ($writer->finish()->result ?? [])['outcome'] ?? null, $operation);
            }

            $this->assertTrue(password_verify($password, $this->readText($this->watcher()->table('users')->where('id', $target['id'])->value('password'))), 'The password that won is in force.');
        }
    }

    public function test_race_21_the_owners_replacement_against_a_command_of_the_outgoing_owner_in_both_orders(): void
    {
        // The command holds the Owner's row first: it commits, then the
        // replacement runs.
        $probe = $this->start(ProbeScenario::class, $this->probe($this->owner), ['after_lock:2.1']);
        $replace = $this->start(ReplaceOwnerScenario::class, ['outgoing' => $this->owner, 'name' => 'Pemilik Baru', 'email' => 'pemilik.baru@contoh.test']);
        Worker::ready($probe, $replace);

        $probe->run()->awaitBarrier('after_lock:2.1');
        $replace->run()->awaitBlocked($this->watcher());
        $probe->release();

        $this->assertSame('COMMITTED', ($probe->finish()->result ?? [])['outcome'] ?? null);
        $newOwner = ($replace->finish()->result ?? [])['owner'] ?? null;
        $this->assertIsInt($newOwner);
        $this->assertOneActiveOwner($newOwner);

        // The replacement holds the row first: the outgoing Owner's command is
        // refused before any fact.
        $facts = $this->watcher()->table('harness_probe_facts')->count();
        $replace = $this->start(ReplaceOwnerScenario::class, ['outgoing' => $newOwner, 'name' => 'Pemilik Ketiga', 'email' => 'pemilik.ketiga@contoh.test'], ['after_lock:2.2']);
        $probe = $this->start(ProbeScenario::class, $this->probe($newOwner));
        Worker::ready($replace, $probe);

        $replace->run()->awaitBarrier('after_lock:2.2');
        $probe->run()->awaitBlocked($this->watcher());
        $replace->release();

        $third = ($replace->finish()->result ?? [])['owner'] ?? null;
        $this->assertIsInt($third);
        $this->assertSame(['refused' => 'FORBIDDEN'], $probe->finish()->result);
        $this->assertSame($facts, $this->watcher()->table('harness_probe_facts')->count());
        $this->assertOneActiveOwner($third);
    }

    public function test_race_30_of_two_replacements_at_once_one_runs_and_the_other_is_refused(): void
    {
        $first = $this->start(ReplaceOwnerScenario::class, ['outgoing' => $this->owner, 'name' => 'Pemilik Satu', 'email' => 'pemilik.satu@contoh.test'], ['after_lock:2.2']);
        $second = $this->start(ReplaceOwnerScenario::class, ['outgoing' => $this->owner, 'name' => 'Pemilik Dua', 'email' => 'pemilik.dua@contoh.test']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:2.2');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $owner = ($first->finish()->result ?? [])['owner'] ?? null;
        $this->assertIsInt($owner);
        $this->assertSame(['refused' => 'NOT_THE_ACTIVE_OWNER'], $second->finish()->result);
        $this->assertOneActiveOwner($owner);
        $this->assertSame(0, $this->watcher()->table('users')->where('email', 'pemilik.dua@contoh.test')->count());
        $this->assertSame('DEACTIVATED', $this->watcher()->table('users')->where('id', $this->owner)->value('status'));
    }

    private function assertOneActiveOwner(int $owner): void
    {
        $this->assertSame([$owner], $this->watcher()->table('users')->where('role_super_admin', true)->where('status', 'ACTIVE')->pluck('id')->all(), 'Exactly one Owner is active (G-08).');
    }

    /**
     * Each operation, and how to make what a run of it needs: its payload and its
     * expected versions, for a new account named after the case.
     *
     * @return array<string, array{string, callable(string): array{array<string, mixed>, array<string, mixed>}}>
     */
    private function everyOperation(): array
    {
        $staff = fn (string $name): User => $this->account(TokoContoh::staff($name)['id']);

        return [
            'buat' => ['user.create', static fn (string $name): array => [['displayName' => $name, 'email' => $name.'@contoh.test', 'roles' => ['cashier']], []]],
            'ubah' => ['user.update', static function (string $name) use ($staff): array {
                $user = $staff($name);

                return [['userId' => $user->id, 'displayName' => 'Nama Baru', 'email' => $user->email, 'roles' => ['cashier']], ['userVersion' => $user->version]];
            }],
            'pemilik' => ['owner.update_details', function (string $name): array {
                $owner = $this->account($this->owner);

                return [['displayName' => 'Hendra '.$name, 'email' => $owner->email], ['userVersion' => $owner->version]];
            }],
            'nonaktif' => ['user.deactivate', static function (string $name) use ($staff): array {
                $user = $staff($name);

                return [['userId' => $user->id], ['userVersion' => $user->version]];
            }],
            'aktif' => ['user.reactivate', function (string $name) use ($staff): array {
                $user = $staff($name);

                return [['userId' => $user->id], ['userVersion' => $this->deactivate($user->id)]];
            }],
            'anonim' => ['user.anonymize', function (string $name) use ($staff): array {
                $user = $staff($name);

                return [['userId' => $user->id, 'reason' => 'Permintaan orang yang bersangkutan.'], ['userVersion' => $this->deactivate($user->id)]];
            }],
            'tautan' => ['user.issue_link', static function (string $name) use ($staff): array {
                $user = $staff($name);

                return [['userId' => $user->id, 'kind' => 'RESET'], ['userVersion' => $user->version]];
            }],
            'cabut' => ['user.revoke_link', static function (string $name) use ($staff): array {
                $user = $staff($name);
                TokoContoh::tokenFor($user->id);

                return [['userId' => $user->id], ['userVersion' => $user->version]];
            }],
        ];
    }

    private function account(int $id): User
    {
        return User::query()->whereKey($id)->firstOrFail();
    }

    /**
     * Deactivates the account through RC-38's Action in another process and
     * returns its new version.
     */
    private function deactivate(int $id): int
    {
        $worker = $this->start(UserCommandScenario::class, $this->command('user.deactivate', ['userId' => $id], ['userVersion' => $this->account($id)->version]));
        Worker::ready($worker);
        $worker->run();
        $this->assertSame('COMMITTED', ($worker->finish()->result ?? [])['outcome'] ?? null);

        return $this->account($id)->version;
    }

    /**
     * A link older than the broker's 60 seconds, so that a re-issue may replace it.
     */
    private function agedLink(int $id, string $email): string
    {
        $token = TokoContoh::tokenFor($id);
        $this->migration()->table('password_reset_tokens')->where('email', $email)->update(['created_at' => now()->subMinutes(5)]);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function linkWriter(string $operation, int $id): array
    {
        $payload = $operation === 'user.issue_link' ? ['userId' => $id, 'kind' => 'RESET'] : ['userId' => $id];

        return $this->command($operation, $payload, ['userVersion' => $this->account($id)->version]);
    }

    /**
     * @return array<string, mixed>
     */
    private function changeOf(string $change, int $id): array
    {
        $account = $this->account($id);

        return $change === 'deactivation'
            ? $this->command('user.deactivate', ['userId' => $id], ['userVersion' => $account->version])
            : $this->command('user.update', ['userId' => $id, 'displayName' => $account->display_name, 'email' => $account->email, 'roles' => ['cashier']], ['userVersion' => $account->version]);
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
     * @return array<string, mixed>
     */
    private function probe(int $actor): array
    {
        return ['actor' => $actor, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'note' => 'Perintah yang berjalan'];
    }

    /**
     * @param  class-string<Scenario>  $scenario
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(string $scenario, array $arguments, array $barriers = []): Worker
    {
        $worker = Worker::start($scenario, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
