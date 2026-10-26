<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\Hash;
use Tests\Harness\Access\ConsumeLinkScenario;
use Tests\Harness\Access\RevokeLinkScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;

/**
 * RACE-20 (TECHNICAL_RULES section 8; SCHEMA section 9.3; suite LINK01;
 * TASK-006 contributes, TASK-011 owns): a one-time link consumed twice at
 * once, and a consumption against a revocation of the same link, each in its
 * own process with its own connection, serialized by the target account's
 * update lock. Whichever commits first wins: a link is consumed at most once,
 * a revoked link is refused LINK_INVALID, and a revocation after the
 * consumption finds nothing left to revoke.
 */
final class LinkConsumptionRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    /** @var array{id: int, email: string, password: string} */
    private array $rina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rina = TokoContoh::person('Rina');
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_the_same_link_used_twice_at_once_is_consumed_once(): void
    {
        $link = TokoContoh::link('Rina');
        $firstPassword = TokoContoh::newPassword();
        $secondPassword = TokoContoh::newPassword();

        $first = $this->start(ConsumeLinkScenario::class, ['user' => $link['user'], 'token' => $link['token'], 'password' => $firstPassword], ['after_lock:2.2']);
        $second = $this->start(ConsumeLinkScenario::class, ['user' => $link['user'], 'token' => $link['token'], 'password' => $secondPassword]);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:2.2');
        $second->run()->awaitBlocked($this->observer());
        $first->release();

        $this->assertSame(['outcome' => 'CONSUMED'], $first->finish()->result);
        $this->assertSame(['outcome' => 'LINK_INVALID'], $second->finish()->result);
        $this->assertTrue(Hash::check($firstPassword, $this->readText($this->observer()->table('users')->where('id', $this->rina['id'])->value('password'))));
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
        $this->assertSame(2, $this->observer()->table('audit_events')->where('action', 'LINK_CONSUME')->count());
    }

    public function test_a_revocation_that_takes_the_lock_first_refuses_the_consumption(): void
    {
        $link = TokoContoh::link('Rina');
        $before = $this->observer()->table('users')->where('id', $this->rina['id'])->value('password');

        $revoke = $this->start(RevokeLinkScenario::class, ['user' => $link['user']], ['after_lock:2.2']);
        $consume = $this->start(ConsumeLinkScenario::class, ['user' => $link['user'], 'token' => $link['token'], 'password' => TokoContoh::newPassword()]);
        Worker::ready($revoke, $consume);

        $revoke->run()->awaitBarrier('after_lock:2.2');
        $consume->run()->awaitBlocked($this->observer());
        $revoke->release();

        $this->assertSame(['outcome' => 'REVOKED'], $revoke->finish()->result);
        $this->assertSame(['outcome' => 'LINK_INVALID'], $consume->finish()->result);
        $this->assertSame($before, $this->observer()->table('users')->where('id', $this->rina['id'])->value('password'));
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
    }

    public function test_a_consumption_that_takes_the_lock_first_wins_over_the_revocation(): void
    {
        $link = TokoContoh::link('Rina');
        $password = TokoContoh::newPassword();

        $consume = $this->start(ConsumeLinkScenario::class, ['user' => $link['user'], 'token' => $link['token'], 'password' => $password], ['after_lock:2.2']);
        $revoke = $this->start(RevokeLinkScenario::class, ['user' => $link['user']]);
        Worker::ready($consume, $revoke);

        $consume->run()->awaitBarrier('after_lock:2.2');
        $revoke->run()->awaitBlocked($this->observer());
        $consume->release();

        $this->assertSame(['outcome' => 'CONSUMED'], $consume->finish()->result);
        $this->assertSame(['outcome' => 'REVOKED'], $revoke->finish()->result);
        $this->assertTrue(Hash::check($password, $this->readText($this->observer()->table('users')->where('id', $this->rina['id'])->value('password'))));
        $this->assertSame(0, $this->observer()->table('password_reset_tokens')->count());
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
