<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-02, RC-33 and RC-34 (AC-027-01, AC-027-04, AC-027-05; TECHNICAL_RULES section 8,
 * RACE-01, RACE-02, RACE-07 and RACE-26; SCHEMA sections 5.10 and 6, SCH-25; BR-CASH-02, BR-CASH-06;
 * G-19; TASK-027 section 7, L3; suites SESSION02, SESSION03, CONFIG01 and CMD01), each with
 * processes of their own connections under barrier control:
 *
 * - RACE-07, two opens of one register by two users: the second waits for the register's lock,
 *   which the first holds to its commit, then finds the session OPEN and is refused;
 * - RACE-07, two opens by one user on two registers: the second waits on the unique index of the
 *   user's OPEN session, then is refused; one session exists in each case;
 * - RACE-26, an open against a configuration change, in both orders: they never interleave, and the
 *   change that holds the lock first is in force for the other;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once;
 * - the numbering family: two opens on two registers do not wait on each other for a number, and
 *   the counter follows allocation order, not commit order.
 */
final class CashSessionRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private string $epoch = '';

    /** @var array<string, int> the account of each person */
    private array $people = [];

    private int $register = 0;

    private ?Connection $watcher = null;

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Hendra', 'Rina', 'Dewi'] as $person) {
            $this->people[$person] = TokoContoh::person($person)['id'];
        }

        $this->epoch = TokoContoh::recoveryEpoch();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_07_two_opens_of_one_register_give_one_session(): void
    {
        $first = $this->start($this->open('Rina', $this->register), ['after_lock:3']);
        $second = $this->start($this->open('Dewi', $this->register));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:3');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'VALIDATION_FAILED'], $second->finish()->result);
        $this->assertSame([$this->people['Rina']], $this->watcher()->table('cash_sessions')->pluck('responsible_user_id')->all());
    }

    public function test_race_07_two_opens_by_one_user_on_two_registers_give_one_session(): void
    {
        // The registers' locks are different rows; the user's OPEN session is held by the partial
        // unique index, on whose entry the second insert waits (BR-CASH-06).
        $other = TokoContoh::register('LACI-2', 'Laci 2');
        $first = $this->start($this->open('Rina', $this->register), ['after_write:1']);
        $second = $this->start($this->open('Rina', $other));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_write:1');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'VALIDATION_FAILED'], $second->finish()->result);
        $this->assertSame([$this->register], $this->watcher()->table('cash_sessions')->pluck('register_id')->all());
    }

    public function test_race_26_an_open_and_a_configuration_change_never_interleave(): void
    {
        // The open holds the register's lock to its commit; the change waits, then finds the session
        // OPEN and is refused (G-19).
        $open = $this->start($this->open('Rina', $this->register), ['after_lock:3']);
        $change = $this->start($this->change($this->register, 1, false));
        Worker::ready($open, $change);

        $open->run()->awaitBarrier('after_lock:3');
        $change->run()->awaitBlocked($this->watcher());
        $open->release();

        $this->assertSame('COMMITTED', ($open->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'VALIDATION_FAILED'], $change->finish()->result);
        $this->assertSame([true, 1], array_values((array) $this->watcher()->table('cash_registers')->where('id', $this->register)->first(['active', 'version'])));

        // The other order: the change deactivates the register under its lock; the open waits, then
        // finds it inactive and is refused.
        $other = TokoContoh::register('LACI-2', 'Laci 2');
        $change = $this->start($this->change($other, 1, false), ['after_lock:3']);
        $open = $this->start($this->open('Dewi', $other));
        Worker::ready($change, $open);

        $change->run()->awaitBarrier('after_lock:3');
        $open->run()->awaitBlocked($this->watcher());
        $change->release();

        $this->assertSame('COMMITTED', ($change->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'VALIDATION_FAILED'], $open->finish()->result);
        $this->assertSame(0, $this->watcher()->table('cash_sessions')->where('register_id', $other)->count());
    }

    public function test_race_01_and_race_02_an_open_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // EXECUTION_CONTEXT section 10, a command: the second request waits on the first's identity,
        // then answers its result; another payload under either is held, never run.
        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $register = TokoContoh::register('LACI-'.($sameKey ? 'K' : 'I'), 'Laci '.$case);
            $person = $sameKey ? 'Rina' : 'Dewi';
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->open($person, $register, '0', $intent, $key), ['after_identity']);
            $repeat = $this->start($this->open($person, $register, '0', $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('cash_sessions')->where('register_id', $register)->count(), $case);
        }

        $register = TokoContoh::register('LACI-P', 'Laci payload');
        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $original = $this->start($this->open('Hendra', $register, '0', $intent, $key), ['after_identity']);
        $other = $this->start($this->open('Hendra', $register, '5000', $intent, $key));
        Worker::ready($original, $other);

        $original->run()->awaitBarrier('after_identity');
        $other->run()->awaitBlocked($this->watcher());
        $original->release();

        $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result);
        $this->assertSame([0], $this->watcher()->table('cash_sessions')->where('register_id', $register)->pluck('opening_cash')->all());
    }

    public function test_opens_on_two_registers_do_not_wait_for_a_number_and_follow_allocation_order(): void
    {
        // SCH-25, the numbering family: the first takes its number and holds before its commit; the
        // second takes the next one and commits while the first still holds; numbers follow
        // allocation order, not commit order.
        $other = TokoContoh::register('LACI-2', 'Laci 2');
        $first = $this->start($this->open('Rina', $this->register), ['after_write:1']);
        $second = $this->start($this->open('Dewi', $other));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_write:1');
        $this->assertSame('COMMITTED', ($second->run()->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(0, $this->watcher()->table('cash_sessions')->where('register_id', $this->register)->count(), 'The first has not committed yet.');
        $first->release();
        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);

        $counters = $this->watcher()->table('cash_sessions')->orderBy('id')->get(['register_id', 'session_number_seq'])->map(static fn (object $row): array => array_values((array) $row))->all();
        $this->assertCount(2, $counters);
        $byRegister = array_column($counters, 1, 0);
        $earlier = $byRegister[$this->register] ?? null;
        $this->assertIsInt($earlier);
        $this->assertSame($earlier + 1, $byRegister[$other] ?? null,'The second open took the next number while the first held its own.');
    }

    /**
     * RC-02 by a person on a register, under a new intent and key unless given.
     *
     * @return array<string, mixed>
     */
    private function open(string $person, int $register, string $openingCash = '0', ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => 'cash_session.open',
            'actor' => $this->people[$person],
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
            'payload' => ['registerId' => $register, 'openingCash' => $openingCash],
        ];
    }

    /**
     * RC-34 by the Owner, at this version, with this active flag.
     *
     * @return array<string, mixed>
     */
    private function change(int $register, int $version, bool $active): array
    {
        $code = $this->watcher()->table('cash_registers')->where('id', $register)->value('code');

        return [
            'operation' => 'cash_register.update',
            'actor' => $this->people['Hendra'],
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['registerVersion' => $version],
            'payload' => ['registerId' => $register, 'code' => is_string($code) ? $code : '', 'label' => 'Laci diubah', 'active' => $active],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $barriers
     */
    private function start(array $arguments, array $barriers = []): Worker
    {
        $worker = Worker::start(UserCommandScenario::class, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
