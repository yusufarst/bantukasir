<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-06 (AC-033-02, AC-033-04, AC-033-05; TECHNICAL_RULES sections 7.1, 7.2 and 8; SCHEMA
 * sections 7 and 9.4, SCH-33; ADR-008 section 7; EXECUTION_CONTEXT section 10, a command; TASK-033 section
 * 7, L3; suites RET01 and FIN03), each with processes of their own connections under barrier control:
 *
 * - RACE-10: concurrent refunds or returns on the same Sale serialize on the original Sale's lock (step 5),
 *   so that the cumulative money and quantity bounds hold, and separately: a refund and a return of the
 *   same line both commit;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another payload
 *   under either is held, never run;
 * - RACE-11, the return side: a saleable return against a running cost-correction replay of its product,
 *   in both orders and past the lock timeout — the cost is restored exactly once, from the corrected
 *   issue, and the replay recomputes a committed return with its issue without touching its fact;
 * - SCH-33, the running totals: an increment that skips the Sale's lock is still refused its excess by the
 *   check, because it is relative.
 */
final class CorrectionRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'sale_correction.other';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

    /** Rp10.000 a unit, counted whole. */
    private int $product = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->product = TokoContoh::product('RACE-R1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Retur', 'sellingPrice' => '10000']);
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_10_concurrent_corrections_of_one_sale_keep_the_money_and_the_quantity_bounds_separately(): void
    {
        // AC-033-02; BR-RFD-03; RACE-10: two units sold for Rp20.000. A refund of Rp15.000 holds the Sale's
        // lock; one of Rp10.000 waits for it, then finds only Rp5.000 left: REFUND_BOUND_EXCEEDED, nothing
        // written. Then a return of both units holds the lock and a damaged return of one waits: refused
        // RETURN_BOUND_EXCEEDED. The money bound and the quantity bound are separate: the last Rp5.000
        // is still refundable with every unit back.
        TokoContoh::stock($this->product, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $this->product, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        $first = $this->start($this->correction($sale, [self::line($line, '15000')], self::transfer()), ['after_lock:5']);
        $late = $this->correction($sale, [self::line($line, '10000')], self::transfer());
        $second = $this->start($late);
        Worker::ready($first, $second);
        $first->run()->awaitBarrier('after_lock:5');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'REFUND_BOUND_EXCEEDED'], $second->finish()->result);
        $this->assertLeftNothing($late);

        $returning = $this->start($this->correction($sale, [self::line($line, '0', '2')]), ['after_lock:5']);
        $more = $this->correction($sale, [self::line($line, '0', '0', '1')]);
        $waiting = $this->start($more);
        Worker::ready($returning, $waiting);
        $returning->run()->awaitBarrier('after_lock:5');
        $waiting->run()->awaitBlocked($this->watcher());
        $returning->release();

        $this->assertSame('COMMITTED', ($returning->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'RETURN_BOUND_EXCEEDED'], $waiting->finish()->result);
        $this->assertLeftNothing($more);

        $this->assertSame('COMMITTED', (new UserCommandScenario)->run($this->correction($sale, [self::line($line, '5000')], self::transfer()))['outcome'] ?? null);
        $this->assertSame([20000, [$line, 20000, '2.000', 20000, '2.000']], [$this->watcher()->table('sale_correction_totals')->where('sale_id', $sale)->value('refunded_amount'), array_values((array) $this->watcher()->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['sale_line_id', 'line_total', 'issued_quantity', 'refunded_amount', 'returned_quantity']))]);
        $this->assertSame([3, 2, ['10.000', 3]], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('refunds')->count(), array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $this->product)->first(['on_hand', 'last_sequence']))]);
    }

    public function test_race_10_a_refund_and_a_return_of_the_same_line_both_commit(): void
    {
        // BR-RFD-03, BR-RFD-04: the whole money and the whole goods of one line, in two corrections racing
        // on the Sale's lock: the second waits, then commits, since each has its own bound.
        TokoContoh::stock($this->product, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $this->product, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        $refund = $this->start($this->correction($sale, [self::line($line, '20000')], self::transfer()), ['after_lock:5']);
        $return = $this->start($this->correction($sale, [self::line($line, '0', '2')]));
        Worker::ready($refund, $return);
        $refund->run()->awaitBarrier('after_lock:5');
        $return->run()->awaitBlocked($this->watcher());
        $refund->release();

        $this->assertSame('COMMITTED', ($refund->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($return->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([20000, '2.000'], array_values((array) $this->watcher()->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['refunded_amount', 'returned_quantity'])));
    }

    public function test_race_01_and_race_02_a_correction_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // BR-RFD-12; ADR-007: the second request waits on the first's identity, then answers its result;
        // another payload under either is held, never run.
        TokoContoh::stock($this->product, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $this->product, 'quantity' => '8']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->correction($sale, [self::line($line, '10000', '1')], self::transfer(), $intent, $key), ['after_identity']);
            $repeat = $this->start($this->correction($sale, [self::line($line, '10000', '1')], self::transfer(), $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);
            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('sale_corrections')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->correction($sale, [self::line($line, '10000', '1')], self::transfer(), $intent, $key), ['after_identity']);
            $other = $this->start($this->correction($sale, [self::line($line, '20000', '2')], self::transfer(), $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);
            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        // Four corrections of Rp10.000 and one unit each; the other payloads never ran.
        $this->assertSame([40000, '4.000'], array_values((array) $this->watcher()->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['refunded_amount', 'returned_quantity'])));
        $this->assertSame([4, 4], [$this->watcher()->table('sale_corrections')->count(), $this->watcher()->table('stock_movements')->where('movement_type', 'RETURN_RECEIPT')->count()]);
    }

    public function test_race_11_a_return_waits_for_a_running_replay_and_restores_the_corrected_cost(): void
    {
        // AC-033-04; ADR-008 section 7; D-061: the correction of the receipt to Rp1.200 holds the product's
        // guard from step 6 to its commit; the return of 2 waits, then restores Rp240 of the corrected issue
        // — never Rp200 of the projection that the replay was replacing.
        [$sale, $evidence] = $this->soldOut();
        $correction = $this->start($this->costCorrection($evidence, '1200'), ['after_lock:7']);
        $return = $this->start($this->correction($sale, [self::line(TokoContoh::saleLinesOf($sale)[0] ?? 0, '0', '2')]));
        Worker::ready($correction, $return);
        $correction->run()->awaitBarrier('after_lock:7');
        $return->run()->awaitBlocked($this->watcher());
        $correction->release();

        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($return->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '240.000000', 'KNOWN', '240.000000']], $this->returnsOf());
        $this->assertSame(['KNOWN', '240.000000'], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value'])));
    }

    public function test_race_11_a_replay_waits_for_a_running_return_and_recomputes_it_without_touching_its_fact(): void
    {
        // D-060, D-061: the return holds the guard first and restores Rp200; the correction waits, then
        // replays it with its issue: its projection becomes Rp240, its fact stays Rp200 — once, not twice.
        [$sale, $evidence] = $this->soldOut();
        $return = $this->start($this->correction($sale, [self::line(TokoContoh::saleLinesOf($sale)[0] ?? 0, '0', '2')]), ['after_lock:7']);
        $correction = $this->start($this->costCorrection($evidence, '1200'));
        Worker::ready($return, $correction);
        $return->run()->awaitBarrier('after_lock:7');
        $correction->run()->awaitBlocked($this->watcher());
        $return->release();

        $this->assertSame('COMMITTED', ($return->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '200.000000', 'KNOWN', '240.000000']], $this->returnsOf());
        $this->assertSame(['KNOWN', '240.000000'], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value'])));
    }

    public function test_race_11_past_the_lock_timeout_the_later_is_refused_service_busy_with_nothing_written(): void
    {
        // D-063, TR-RETRY-02: a replay holds the product's guard; a return that cannot take it within the
        // lock timeout is refused SERVICE_BUSY, and so is a correction against a return that holds it;
        // neither writes anything, and no mark.
        [$sale, $evidence] = $this->soldOut();
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        $correction = $this->start($this->costCorrection($evidence, '1200'), ['after_lock:7']);
        $waiting = $this->correction($sale, [self::line($line, '0', '2')]);
        $return = $this->start($waiting);
        Worker::ready($correction, $return);
        $correction->run()->awaitBarrier('after_lock:7');
        $return->run();
        $this->assertSame(['refused' => 'SERVICE_BUSY'], $return->finish()->result);
        $correction->release();
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLeftNothing($waiting);
        $this->assertSame(0, $this->watcher()->table('sale_corrections')->count());

        $holding = $this->start($this->correction($sale, [self::line($line, '0', '2')]), ['after_lock:7']);
        $late = $this->costCorrection($evidence, '1300', 1);
        $refused = $this->start($late);
        Worker::ready($holding, $refused);
        $holding->run()->awaitBarrier('after_lock:7');
        $refused->run();
        $this->assertSame(['refused' => 'SERVICE_BUSY'], $refused->finish()->result);
        $holding->release();
        $this->assertSame('COMMITTED', ($holding->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLeftNothing($late);

        // The return restored its share of the cost the first correction published.
        $this->assertSame([['KNOWN', '240.000000', 'KNOWN', '240.000000']], $this->returnsOf());
        $this->assertSame([1, 0], [$this->watcher()->table('cost_corrections')->count(), $this->watcher()->table('cost_incomplete_marks')->count()]);
    }

    public function test_an_increment_of_a_running_total_that_skips_the_sale_lock_is_still_refused_its_excess(): void
    {
        // SCHEMA section 9.4, the race family; SCH-33: each running total is changed by a relative increment
        // in one statement, so that its check judges the true cumulative value even if a lock were missing.
        // A writer that read the line before another committed, and skips the Sale's lock, adds to what is
        // there now: the excess is refused, whatever it read.
        TokoContoh::stock($this->product, '10', '50000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $this->product, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;
        TokoContoh::correct($sale, [['saleLineId' => $line, 'refundAmount' => '5000', 'returnedNotSaleable' => '1']], ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1']);

        $stale = $this->observer();
        $read = array_values((array) $stale->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['refunded_amount', 'returned_quantity']));
        $this->assertSame([5000, '1.000'], $read);

        $runtime = $this->runtime();
        $runtime->transaction(function (Connection $c) use ($sale, $line): void {
            $c->update('update sale_correction_totals set refunded_amount = refunded_amount + ? where sale_id = ?', [15000, $sale]);
            $c->update('update sale_line_correction_totals set refunded_amount = refunded_amount + ?, returned_quantity = returned_quantity + ? where sale_line_id = ?', [15000, '1', $line]);
        });

        // What the stale reader still believes remains, Rp15.000 and one unit, no longer does.
        $this->assertWriteRefused(fn (Connection $c) => $c->update('update sale_line_correction_totals set refunded_amount = refunded_amount + ? where sale_line_id = ?', [15000, $line]), '23514', 'sale_line_correction_totals_refunded_range', $stale);
        $this->assertWriteRefused(fn (Connection $c) => $c->update('update sale_line_correction_totals set returned_quantity = returned_quantity + ? where sale_line_id = ?', ['1', $line]), '23514', 'sale_line_correction_totals_returned_range', $stale);
        $this->assertWriteRefused(fn (Connection $c) => $c->update('update sale_correction_totals set refunded_amount = refunded_amount + ? where sale_id = ?', [15000, $sale]), '23514', 'sale_correction_totals_refunded_range', $stale);
        $this->assertSame([20000, '2.000'], array_values((array) $this->watcher()->table('sale_line_correction_totals')->where('sale_line_id', $line)->first(['refunded_amount', 'returned_quantity'])));
    }

    /**
     * Ten units received for Rp1.000 and all ten sold: the Sale and the receipt's cost source.
     *
     * @return array{0: int, 1: int}
     */
    private function soldOut(): array
    {
        $receipt = TokoContoh::stock($this->product, '10', '1000');
        $sale = TokoContoh::sell('Rina', $this->session, [['productId' => $this->product, 'quantity' => '10']]);

        return [$sale, TokoContoh::evidenceOf(TokoContoh::movementOf($receipt))];
    }

    /**
     * RC-06 of a Sale by the Owner, with this payout or none; a new intent and key unless given.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, string>|null  $payout
     * @return array<string, mixed>
     */
    private function correction(int $sale, array $lines, ?array $payout = null, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => self::OPERATION,
            'actor' => $this->owner,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
            'payload' => ['saleId' => $sale, 'lines' => $lines, 'reason' => 'Retur pembeli', ...($payout === null ? [] : ['payout' => $payout])],
        ];
    }

    /**
     * A line of RC-06.
     *
     * @return array{saleLineId: int, refundAmount: string, returnedSaleable: string, returnedNotSaleable: string}
     */
    private static function line(int $saleLine, string $refund = '0', string $saleable = '0', string $notSaleable = '0'): array
    {
        return ['saleLineId' => $saleLine, 'refundAmount' => $refund, 'returnedSaleable' => $saleable, 'returnedNotSaleable' => $notSaleable];
    }

    /**
     * A payout made by bank transfer.
     *
     * @return array<string, string>
     */
    private static function transfer(): array
    {
        return ['method' => 'BANK_TRANSFER', 'reference' => 'TRF-1'];
    }

    /**
     * RC-15 of a cost source by the Owner, reviewing the correction of this number (0 for none).
     *
     * @return array<string, mixed>
     */
    private function costCorrection(int $evidence, string $amount, int $seen = 0): array
    {
        return ['operation' => 'cost_correction.confirm', 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'expected' => ['correctionNo' => $seen], 'payload' => ['evidenceId' => $evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota pemasok']];
    }

    /**
     * The product's return allocations: the fact's status and amount, the projection's status and amount.
     *
     * @return list<list<mixed>>
     */
    private function returnsOf(): array
    {
        return array_values($this->watcher()->table('return_cost_allocations as a')->join('return_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $this->product)->orderBy('a.id')
            ->get(['a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount'])
            ->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    /**
     * A refused command left no identity, and so no fact of its own, audit event or result (TS-20).
     *
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

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
