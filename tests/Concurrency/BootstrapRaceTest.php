<?php

namespace Tests\Concurrency;

use Tests\Harness\Access\BootstrapScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;

/**
 * RACE-30, the bootstrap part (TECHNICAL_RULES section 8; SCHEMA section 7;
 * SECURITY_MODEL SEC-OP-01; AC-010-01; TASK-010 contributes): two bootstraps
 * at once, each in its own process with its own connection. The first inserts
 * the installation state — the recovery epoch and the onboarding state — and
 * holds; the second waits on the epoch's primary key, and when the first
 * commits it fails on it and is refused ALREADY_INSTALLED. Exactly one Owner,
 * one epoch, one onboarding state and one revision with its pointer remain.
 */
final class BootstrapRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_of_two_concurrent_bootstraps_exactly_one_succeeds(): void
    {
        $first = $this->start(BootstrapScenario::class, ['name' => 'Hendra', 'email' => 'hendra@contoh.test', 'shop' => 'Toko Contoh'], ['after_lock:2.3']);
        $second = $this->start(BootstrapScenario::class, ['name' => 'Dewi', 'email' => 'dewi@contoh.test', 'shop' => 'Toko Lain']);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:2.3');
        $second->run()->awaitBlocked($this->observer());
        $first->release();

        $won = $first->finish()->result;
        $this->assertSame('INSTALLED', $won['outcome'] ?? null);
        $this->assertSame(['outcome' => 'ALREADY_INSTALLED'], $second->finish()->result);

        $observer = $this->observer();
        $this->assertSame([$won['owner'] ?? null], $observer->table('users')->pluck('id')->all());
        $this->assertSame('hendra@contoh.test', $observer->table('users')->value('email'));
        $this->assertSame(1, $observer->table('recovery_epoch')->count());
        $this->assertSame(1, $observer->table('onboarding_state')->count());
        $this->assertSame(['Toko Contoh'], $observer->table('business_profile_revisions')->pluck('shop_name')->all());
        $this->assertSame(1, $observer->table('business_profile_current')->count());
        $this->assertSame(3, $observer->table('audit_events')->count());
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
