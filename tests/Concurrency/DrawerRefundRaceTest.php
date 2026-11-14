<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Commands\ResolveScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-05 (AC-034-02 to AC-034-04; TECHNICAL_RULES sections 7.1, 7.2 and 8, RACE-13 to RACE-15;
 * SCHEMA G-04, G-05 and section 7; SALES_CASH BR-CASH-14, BR-RFD-08, BR-RFD-09, BR-RFD-15 to BR-RFD-17; D-045,
 * D-046; ADR-007; EXECUTION_CONTEXT section 10, a command; TASK-034 section 7, L3; suites RET02, SESSION05),
 * each with processes of their own connections under barrier control:
 *
 * - RACE-13: a drawer-funded refund against the close of its session — refund first, its activity version
 *   refuses the close counted before it RECOUNT_REQUIRED and the session stays OPEN; close first, the refund
 *   naming the session is refused SESSION_NOT_OPEN before any fact;
 * - RACE-14: two drawer-funded refunds against one session's expected cash — the second, checked under the
 *   session's lock after the first, is refused DRAWER_CASH_INSUFFICIENT and never switched or split, and
 *   expected cash never goes below zero; and a Sale that takes the session's lock first adds its cash before
 *   a refund is checked against it;
 * - RACE-15: the Owner's status check of a refund whose answer is lost, against the refund itself — check
 *   first, its fence refuses the refund that arrives after it before any fact, so no cash may leave the
 *   drawer, and a new refund of a new intent commits once; refund first, the check waits for it and reports
 *   it COMMITTED, and a repeat of its intent answers that refund, never a second one;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once.
 */
final class DrawerRefundRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'sale_correction.drawer_funded';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private int $rina = 0;

    private string $epoch = '';

    /** Rp30.000 a unit, counted whole. */
    private int $product = 0;

    /** Rina's session, the drawer that the refunds name, opened with Rp50.000. */
    private int $drawer = 0;

    /** Dewi's session, where the refunded Sales were made. */
    private int $other = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->rina = TokoContoh::person('Rina')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->product = TokoContoh::product('RACE-D1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Laci', 'sellingPrice' => '30000']);
        TokoContoh::stock($this->product, '20', '100000');
        $this->drawer = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'), '50000');
        $this->other = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_13_a_refund_first_makes_the_close_counted_before_it_recount_and_the_session_stays_open(): void
    {
        // AC-034-04, AC-034-06; BR-CASH-14, BR-RFD-08; RACE-13, refund first: a CASH Sale of Rp30.000 in her
        // drawer gives expected cash Rp80.000; she counts it against version 1 while the Owner's refund of
        // Rp20.000 holds the session's lock; her close waits for it, then finds the version moved:
        // RECOUNT_REQUIRED, the session OPEN and nothing of her close written. Counted again, Rp60.000.
        $sale = TokoContoh::sell('Rina', $this->drawer, [['productId' => $this->product, 'quantity' => '1']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $version = $this->versionOf($this->drawer);

        $refund = $this->start($this->refund($sale, $line, '20000', $this->drawer), ['after_lock:4']);
        $close = $this->close($this->drawer, $version, '80000');
        $closing = $this->start($close);
        Worker::ready($refund, $closing);
        $refund->run()->awaitBarrier('after_lock:4');
        $closing->run()->awaitBlocked($this->watcher());
        $refund->release();

        $this->assertSame('COMMITTED', ($refund->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'RECOUNT_REQUIRED'], $closing->finish()->result);
        $this->assertLeftNothing($close);
        $this->assertSame(['OPEN', $version + 1], $this->sessionOf($this->drawer));
        $this->assertSame(0, $this->watcher()->table('cash_session_closes')->where('session_id', $this->drawer)->count());

        // The refund's cash is taken after its commit and before the count; the close counted again holds it.
        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->close($this->drawer, $version + 1, '60000'))['outcome'] ?? null);
        $this->assertSame([60000, 60000, 0, 20000], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $this->drawer)->first(['expected_cash', 'physical_cash', 'variance', 'drawer_refund_total'])));
    }

    public function test_race_13_a_close_first_refuses_the_refund_naming_its_session_before_any_fact(): void
    {
        // BR-RFD-09; RACE-13, close first: her close holds the session's lock; the refund naming her drawer
        // waits for it, then finds the session CLOSED: SESSION_NOT_OPEN with the refund's message, nothing
        // written and no cash taken. The Owner may choose another OPEN session or his own funds.
        $sale = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '1']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $version = $this->versionOf($this->drawer);

        $closing = $this->start($this->close($this->drawer, $version, '50000'), ['after_lock:4']);
        $late = $this->refund($sale, $line, '20000', $this->drawer);
        $refund = $this->start($late);
        Worker::ready($closing, $refund);
        $closing->run()->awaitBarrier('after_lock:4');
        $refund->run()->awaitBlocked($this->watcher());
        $closing->release();

        $this->assertSame('COMMITTED', ($closing->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $refund->finish()->result);
        $this->assertLeftNothing($late);
        $this->assertSame([0, 0, ['CLOSED', $version]], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('refunds')->count(), $this->sessionOf($this->drawer)]);

        // Another OPEN session's drawer that holds enough pays it: Dewi's, with her Sale's Rp30.000.
        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->refund($sale, $line, '20000', $this->other))['outcome'] ?? null);
        $this->assertSame([$this->other], $this->watcher()->table('refunds')->pluck('cash_session_id')->all());
    }

    public function test_race_14_two_refunds_from_one_drawer_never_take_its_expected_cash_below_zero(): void
    {
        // AC-034-03; BR-RFD-15, BR-RFD-16, D-045; RACE-14: her drawer holds Rp50.000 expected (no Sale yet).
        // Two refunds of Rp30.000 of two of Dewi's Sales race on its lock: the first commits, leaving
        // Rp20.000; the second, checked after it, is refused DRAWER_CASH_INSUFFICIENT, never paid from
        // another drawer or split, and nothing of it is written.
        $first = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '1']]);
        $second = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '1']]);
        $firstLine = TokoContoh::saleLinesOf($first)[0] ?? 0;
        $secondLine = TokoContoh::saleLinesOf($second)[0] ?? 0;
        $version = $this->versionOf($this->drawer);

        $paid = $this->start($this->refund($first, $firstLine, '30000', $this->drawer), ['after_lock:4']);
        $late = $this->refund($second, $secondLine, '30000', $this->drawer);
        $refused = $this->start($late);
        Worker::ready($paid, $refused);
        $paid->run()->awaitBarrier('after_lock:4');
        $refused->run()->awaitBlocked($this->watcher());
        $paid->release();

        $this->assertSame('COMMITTED', ($paid->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'DRAWER_CASH_INSUFFICIENT'], $refused->finish()->result);
        $this->assertLeftNothing($late);
        $this->assertSame([[30000, $this->drawer]], array_map(static fn (object $row): array => array_values((array) $row), $this->watcher()->table('refunds')->get(['amount', 'cash_session_id'])->all()));
        $this->assertSame(['OPEN', $version + 1], $this->sessionOf($this->drawer));

        // Exactly the Rp20.000 that remains is still allowed, leaving Rp0, never below (BR-RFD-15).
        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->refund($second, $secondLine, '20000', $this->drawer))['outcome'] ?? null);
        $this->assertSame(['refused' => 'DRAWER_CASH_INSUFFICIENT'], (new UserCommandScenario)->run($this->refund($second, $secondLine, '1', $this->drawer)));
        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->close($this->drawer, $version + 2, '0'))['outcome'] ?? null);
        $this->assertSame([0, 0, 0, 50000], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $this->drawer)->first(['expected_cash', 'physical_cash', 'variance', 'drawer_refund_total'])));
    }

    public function test_race_14_a_sale_that_takes_the_session_first_adds_its_cash_before_a_refund_is_checked(): void
    {
        // RACE-14, a refund and a Sale: her drawer holds Rp50.000 expected; her Sale of Rp30.000 in CASH holds
        // the session's lock while the Owner's refund of Rp70.000 from it waits. The refund, checked against
        // the session's committed facts at that moment, finds Rp80.000 and commits; each moved the activity
        // version once.
        $refunded = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '3']]);
        $line = TokoContoh::saleLinesOf($refunded)[0] ?? 0;
        $version = $this->versionOf($this->drawer);

        $sale = $this->start($this->checkout(), ['after_lock:4']);
        $refund = $this->start($this->refund($refunded, $line, '70000', $this->drawer));
        Worker::ready($sale, $refund);
        $sale->run()->awaitBarrier('after_lock:4');
        $refund->run()->awaitBlocked($this->watcher());
        $sale->release();

        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($refund->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['OPEN', $version + 2], $this->sessionOf($this->drawer));
        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->close($this->drawer, $version + 2, '10000'))['outcome'] ?? null);
        $this->assertSame([10000, 0, 70000, 30000], array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $this->drawer)->first(['expected_cash', 'variance', 'drawer_refund_total', 'cash_applied'])));
    }

    public function test_race_15_a_status_check_fences_a_refund_not_yet_run_and_reports_one_that_ran_never_a_second(): void
    {
        // AC-034-04; BR-RFD-17, D-046; RACE-15: the Owner checks the status of a drawer-funded refund whose
        // answer was lost. Check first: its fence holds the intent while the refund arrives and waits on it;
        // then the refund is refused INTENT_ABANDONED before any fact — no refund, no payout, the session's
        // activity version unmoved — so no cash leaves the drawer.
        $sale = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '1']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $version = $this->versionOf($this->drawer);

        $intent = (string) Str::uuid();
        $check = $this->resolve($intent, ['after_identity']);
        $refund = $this->start($this->refund($sale, $line, '10000', $this->drawer, $intent));
        Worker::ready($check, $refund);
        $check->run()->awaitBarrier('after_identity');
        $refund->run()->awaitBlocked($this->watcher());
        $check->release();

        $this->assertSame(['outcome' => 'NOT_COMMITTED', 'commandId' => null], $check->finish()->result);
        $this->assertSame(['refused' => 'INTENT_ABANDONED'], $refund->finish()->result);
        $this->assertSame([0, 0, ['OPEN', $version]], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('refunds')->count(), $this->sessionOf($this->drawer)]);

        // Then the Owner may make a new refund, of a new intent. Refund first: the check of its intent waits
        // for it and reports it COMMITTED, the one refund whose cash now leaves the drawer; a repeat of the
        // intent answers that refund, never a second one.
        $intent = (string) Str::uuid();
        $original = $this->start($this->refund($sale, $line, '10000', $this->drawer, $intent), ['after_identity']);
        $check = $this->resolve($intent);
        Worker::ready($original, $check);
        $original->run()->awaitBarrier('after_identity');
        $check->run()->awaitBlocked($this->watcher());
        $original->release();

        $committed = $original->finish()->result ?? [];
        $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null]);
        $this->assertSame(['outcome' => 'COMMITTED', 'commandId' => $committed['commandId'] ?? null], $check->finish()->result);
        $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], (new UserCommandScenario)->run($this->refund($sale, $line, '10000', $this->drawer, $intent)));
        $this->assertSame([1, 1, ['OPEN', $version + 1]], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('refunds')->count(), $this->sessionOf($this->drawer)]);
    }

    public function test_race_01_and_race_02_a_drawer_funded_refund_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // BR-RFD-12, BR-RFD-17; ADR-007: the second request waits on the first's identity, then answers its
        // result: one refund, one payout and one move of the activity version; another payload under either
        // is held, never run.
        $sale = TokoContoh::sell('Dewi', $this->other, [['productId' => $this->product, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $version = $this->versionOf($this->drawer);
            $original = $this->start($this->refund($sale, $line, '10000', $this->drawer, $intent, $key), ['after_identity']);
            $repeat = $this->start($this->refund($sale, $line, '10000', $this->drawer, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);
            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame([1, ['OPEN', $version + 1]], [$this->watcher()->table('sale_corrections')->where('command_id', $committed['commandId'] ?? null)->count(), $this->sessionOf($this->drawer)], $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->refund($sale, $line, '5000', $this->drawer, $intent, $key), ['after_identity']);
            $other = $this->start($this->refund($sale, $line, '6000', $this->drawer, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);
            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        // Each committed refund named its ordinal on the Sale (D-087).
        $read = $this->watcher()->table('sales')->where('id', $sale)->value('sale_number');
        $number = is_string($read) ? $read : '';
        $this->assertSame([$number.'/1', $number.'/2', $number.'/3', $number.'/4'], $this->watcher()->table('refunds')->orderBy('id')->pluck('payout_reference')->all());
    }

    /**
     * RC-05 by the Owner: the money refunded on one line, paid from a session's drawer.
     *
     * @return array<string, mixed>
     */
    private function refund(int $sale, int $line, string $amount, int $session, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => self::OPERATION,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
            'payload' => [
                'saleId' => $sale,
                'lines' => [['saleLineId' => $line, 'refundAmount' => $amount, 'returnedSaleable' => '0', 'returnedNotSaleable' => '0']],
                'reason' => 'Uang dikembalikan dari laci',
                'payout' => ['sessionId' => $session],
            ],
        ];
    }

    /**
     * RC-03 by Rina of her session, counted against this activity version.
     *
     * @return array<string, mixed>
     */
    private function close(int $session, int $version, string $physical): array
    {
        return ['operation' => 'cash_session.close', 'actor' => $this->rina, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'expected' => ['activityVersion' => $version], 'payload' => ['sessionId' => $session, 'physicalCash' => $physical]];
    }

    /**
     * RC-01 by Rina in her session: one unit, paid in CASH with the exact total.
     *
     * @return array<string, mixed>
     */
    private function checkout(): array
    {
        $revision = $this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id');

        return [
            'operation' => 'sale.checkout',
            'actor' => $this->rina,
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['lines' => [['priceRevisionId' => $revision]]],
            'payload' => ['sessionId' => $this->drawer, 'lines' => [['productId' => $this->product, 'quantity' => '1']], 'payment' => ['method' => 'CASH', 'tendered' => '30000']],
        ];
    }

    private function versionOf(int $session): int
    {
        $version = $this->watcher()->table('cash_sessions')->where('id', $session)->value('activity_version');

        return is_int($version) ? $version : 0;
    }

    /**
     * A session's status and activity version.
     *
     * @return list<mixed>
     */
    private function sessionOf(int $session): array
    {
        return array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'activity_version']));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function assertLeftNothing(array $arguments): void
    {
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $arguments['intent'])->count());
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

    /**
     * RC-50's Action by the Owner for an intent of RC-05, in a process of its own.
     *
     * @param  list<string>  $barriers
     */
    private function resolve(string $intent, array $barriers = []): Worker
    {
        $worker = Worker::start(ResolveScenario::class, ['checker' => $this->owner, 'operation' => self::OPERATION, 'intent' => $intent, 'epoch' => $this->epoch], $barriers);
        $this->workers[] = $worker;

        return $worker;
    }

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
