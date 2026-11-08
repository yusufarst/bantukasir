<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\Configuration\ProfileChangeScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;
use Tests\Harness\Sales\SessionCloseScenario;

/**
 * The races of RC-01 (AC-028-01, AC-028-06, AC-028-07, AC-028-11; TECHNICAL_RULES sections 7.1 and 8;
 * EXECUTION_CONTEXT section 10, a command; TASK-028 section 7, L3; suites POS01, POS03, INV02, FIN03,
 * SESSION05, IDENTITY01 and BARCODE01), each with processes of their own connections under barrier
 * control:
 *
 * - RACE-03: two Sales, or a Sale and a manual issue, for the last unit — exactly one succeeds, the
 *   other is refused INSUFFICIENT_STOCK, stock is never negative, and the refused Sale takes no number;
 * - RACE-01 and RACE-02: the same key, or another key of the same intent, runs once, and another
 *   payload under either is held, never run;
 * - the number: Sales in different sessions never wait on each other for it; numbers are unique, follow
 *   the order they were taken, and one rolled back leaves its gap;
 * - RACE-08, the Sale side: against the close of its session, as RC-03 of TASK-030 will take its lock;
 * - RACE-11: a Sale against a running replay of its product, and a replay against a running Sale, in
 *   both orders and past the lock timeout;
 * - RACE-23: a price change or a deactivation against a Sale whose cart is open, in both orders;
 * - RACE-25: a BusinessProfile change against a Sale, in both orders;
 * - RACE-18, the Sale side: a barcode move against the Sale of a cart line taken before it.
 */
final class CheckoutRaceTest extends DatabaseTestCase
{
    private const string OPERATION = 'sale.checkout';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private int $rina = 0;

    private int $dewi = 0;

    private string $epoch = '';

    private int $category = 0;

    /** Rp20.000 a unit, counted whole. */
    private int $product = 0;

    private int $rinaSession = 0;

    private int $dewiSession = 0;

    private ?Connection $watcher = null;

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = TokoContoh::person('Hendra')['id'];
        $this->rina = TokoContoh::person('Rina')['id'];
        $this->dewi = TokoContoh::person('Dewi')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->category = TokoContoh::category();
        $this->product = TokoContoh::product('RACE-S1', ['categoryId' => $this->category, 'name' => 'Produk Jual', 'sellingPrice' => '20000']);
        $this->rinaSession = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->dewiSession = TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2'));
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_03_two_sales_or_a_sale_and_a_manual_issue_for_the_last_unit_give_one_success_and_one_insufficient_stock(): void
    {
        // AC-028-07; BR-INV-04, BR-SALE-09, G-02: the second waits on the product guard, then finds no
        // stock left. A refused Sale takes no number, which is taken after every guard (SCH-21).
        TokoContoh::stock($this->product, '1', '7000');
        $first = $this->start($this->sale('Rina', '1', '20000'), ['after_lock:7']);
        $refused = $this->sale('Dewi', '1', '20000');
        $second = $this->start($refused);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:7');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame('COMMITTED', ($first->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $second->finish()->result);
        $this->assertLeftNothing($refused);
        $this->assertSame(['0.000', 2], $this->balanceOf($this->product));
        $this->assertSame([['KNOWN', '7000.000000', 1, 'KNOWN', '7000.000000']], $this->allocationsOf($this->product));

        // A Sale before a manual issue.
        TokoContoh::stock($this->product, '1', '7000');
        $sale = $this->start($this->sale('Rina', '1', '20000'), ['before_commit']);
        $issue = $this->start($this->manualIssue('1'));
        Worker::ready($sale, $issue);
        $sale->run()->awaitBarrier('before_commit');
        $issue->run()->awaitBlocked($this->watcher());
        $sale->release();
        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $issue->finish()->result);

        // A manual issue before a Sale.
        TokoContoh::stock($this->product, '1', '7000');
        $issuing = $this->start($this->manualIssue('1'), ['after_lock:7']);
        $late = $this->sale('Dewi', '1', '20000');
        $selling = $this->start($late);
        Worker::ready($issuing, $selling);
        $issuing->run()->awaitBarrier('after_lock:7');
        $selling->run()->awaitBlocked($this->watcher());
        $issuing->release();
        $this->assertSame('COMMITTED', ($issuing->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'INSUFFICIENT_STOCK'], $selling->finish()->result);
        $this->assertLeftNothing($late);

        // Stock never negative; two Sales whose counters follow each other: no refusal took a number.
        $this->assertSame(0, $this->watcher()->table('stock_balances')->where('on_hand', '<', 0)->count());
        $this->assertSame(['0.000', 6], $this->balanceOf($this->product));
        $counters = $this->counters();
        $start = $counters[0] ?? null;
        $this->assertIsInt($start);
        $this->assertSame([$start, $start + 1], $counters);
    }

    public function test_race_01_and_race_02_a_sale_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        // AC-028-01; BR-SALE-18; ADR-007: the second request waits on the first's identity, then answers
        // its result; another payload under either is held, never run.
        TokoContoh::stock($this->product, '20', '20000');

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->sale('Rina', '3', '60000', null, null, $intent, $key), ['after_identity']);
            $repeat = $this->start($this->sale('Rina', '3', '60000', null, null, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $committed = $original->finish()->result ?? [];
            $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $case);
            $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $case);
            $this->assertSame(1, $this->watcher()->table('sales')->where('command_id', $committed['commandId'] ?? null)->count(), $case);
        }

        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->sale('Rina', '3', '60000', null, null, $intent, $key), ['after_identity']);
            $other = $this->start($this->sale('Rina', '4', '80000', null, null, $intent, $sameKey ? $key : (string) Str::uuid()));
            Worker::ready($original, $other);

            $original->run()->awaitBarrier('after_identity');
            $other->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame('COMMITTED', ($original->finish()->result ?? [])['outcome'] ?? null, $case);
            $this->assertSame(['refused' => 'IDEMPOTENCY_CONFLICT'], $other->finish()->result, $case);
        }

        // Four Sales of three, each with its own number; the other payload never ran.
        $this->assertSame(['8.000', 5], $this->balanceOf($this->product));
        $this->assertSame([4, 4], [count(array_unique($this->counters())), $this->watcher()->table('sales')->where('total_amount', 60000)->count()]);
    }

    public function test_sales_in_different_sessions_never_wait_for_a_number_which_follows_the_order_taken_and_leaves_a_gap_when_rolled_back(): void
    {
        // AC-028-06; SCH-21, SCH-22, TR-TX-10: the number is a sequence's, taken at step 8 next to the
        // clock, under no lock that a Sale of another session needs. Rina's Sale holds after its Sale row;
        // Dewi's, of another product, commits meanwhile with the next number; Rina's commits after it.
        // Then Rina's holds again and is rolled back: its number stays a gap, and Dewi's takes the next.
        $other = TokoContoh::product('RACE-S2', ['categoryId' => $this->category, 'name' => 'Produk Lain', 'sellingPrice' => '10000']);
        TokoContoh::stock($this->product, '10', '10000');
        TokoContoh::stock($other, '10', '10000');

        $rina = $this->start($this->sale('Rina', '1', '20000'), ['after_write:1']);
        $dewi = $this->start($this->sale('Dewi', '1', '10000', $other));
        Worker::ready($rina, $dewi);
        $rina->run()->awaitBarrier('after_write:1');
        $dewi->run();
        $this->assertSame('COMMITTED', ($dewi->finish()->result ?? [])['outcome'] ?? null);
        $rina->release();
        $this->assertSame('COMMITTED', ($rina->finish()->result ?? [])['outcome'] ?? null);

        $aborted = $this->start($this->sale('Rina', '1', '20000'), ['after_write:1']);
        $next = $this->start($this->sale('Dewi', '1', '10000', $other));
        Worker::ready($aborted, $next);
        $aborted->run()->awaitBarrier('after_write:1');
        $next->run();
        $this->assertSame('COMMITTED', ($next->finish()->result ?? [])['outcome'] ?? null);
        $aborted->abort();
        $this->assertFalse($aborted->finish()->succeeded());

        // By their keys: Rina's, then Dewi's two; Rina's took the first number though it committed last.
        $this->assertSame([$this->rina, $this->dewi, $this->dewi], array_values($this->watcher()->table('sales')->orderBy('id')->pluck('actor_user_id')->all()));
        $counters = $this->counters();
        $start = $counters[0] ?? null;
        $this->assertIsInt($start);
        $this->assertSame([$start, $start + 1, $start + 3], $counters);
        $this->assertSame([['9.000', 2], ['8.000', 3]], [$this->balanceOf($this->product), $this->balanceOf($other)]);
    }

    public function test_race_08_a_sale_against_the_close_of_its_session(): void
    {
        // AC-028-02, AC-028-04; BR-CASH-14, BR-CASH-15; RACE-08, the Sale side (the close is RC-03 of
        // TASK-030, whose session lock SessionCloseScenario takes as it will). Sale first: it commits once,
        // and the close finds the activity version moved, RECOUNT_REQUIRED. Close first: the Sale waits for
        // the session's lock and is refused SESSION_NOT_OPEN before any fact and before its number.
        TokoContoh::stock($this->product, '10', '10000');
        $sale = $this->start($this->sale('Rina', '1', '20000'), ['after_lock:7']);
        $close = $this->start(['session' => $this->rinaSession, 'activityVersion' => 0], [], SessionCloseScenario::class);
        Worker::ready($sale, $close);
        $sale->run()->awaitBarrier('after_lock:7');
        $close->run()->awaitBlocked($this->watcher());
        $sale->release();
        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'RECOUNT_REQUIRED'], $close->finish()->result);
        $this->assertSame(['OPEN', 1], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $this->rinaSession)->first(['status', 'activity_version'])));

        $closing = $this->start(['session' => $this->rinaSession, 'activityVersion' => 1], ['after_lock:4'], SessionCloseScenario::class);
        $late = $this->sale('Rina', '1', '20000');
        $waiting = $this->start($late);
        Worker::ready($closing, $waiting);
        $closing->run()->awaitBarrier('after_lock:4');
        $waiting->run()->awaitBlocked($this->watcher());
        $closing->release();
        $this->assertSame(['outcome' => 'CLOSED'], $closing->finish()->result);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $waiting->finish()->result);
        $this->assertLeftNothing($late);
        $this->assertSame([1, ['9.000', 2]], [$this->watcher()->table('sales')->count(), $this->balanceOf($this->product)]);

        // The refused Sale took no number: Dewi's next takes the one after Rina's.
        $next = $this->start($this->sale('Dewi', '1', '20000'));
        Worker::ready($next);
        $next->run();
        $this->assertSame('COMMITTED', ($next->finish()->result ?? [])['outcome'] ?? null);
        $counters = $this->counters();
        $start = $counters[0] ?? null;
        $this->assertIsInt($start);
        $this->assertSame([$start, $start + 1], $counters);
    }

    public function test_race_11_a_sale_waits_for_a_running_replay_and_takes_its_allocation_from_the_corrected_valuation(): void
    {
        // AC-028-11; ADR-008 sections 4.2 and 7: the correction holds the product's guard from step 6 to
        // its commit; the Sale of 5 waits, then finds 20 worth Rp260.000 published, and takes Rp65.000 —
        // never an allocation from the valuation that the replay was replacing.
        $evidence = $this->twoReceipts();
        $correction = $this->start($this->correction($evidence, '160000'), ['after_lock:7']);
        $sale = $this->start($this->sale('Rina', '5', '100000'));
        Worker::ready($correction, $sale);

        $correction->run()->awaitBarrier('after_lock:7');
        $sale->run()->awaitBlocked($this->watcher());
        $correction->release();

        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '65000.000000', 3, 'KNOWN', '65000.000000']], $this->allocationsOf($this->product));
    }

    public function test_race_11_a_replay_waits_for_a_running_sale_and_replays_its_issue_without_touching_its_fact(): void
    {
        // AC-028-11, D-060: the Sale holds the guard first and takes Rp60.000 of 20 worth Rp240.000; the
        // correction waits, then replays it: its projection becomes Rp65.000, its fact and the Sale stay.
        $evidence = $this->twoReceipts();
        $sale = $this->start($this->sale('Rina', '5', '100000'), ['after_lock:7']);
        $correction = $this->start($this->correction($evidence, '160000'));
        Worker::ready($sale, $correction);

        $sale->run()->awaitBarrier('after_lock:7');
        $correction->run()->awaitBlocked($this->watcher());
        $sale->release();

        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['KNOWN', '60000.000000', 2, 'KNOWN', '65000.000000']], $this->allocationsOf($this->product));
        $this->assertSame([100000, 100000], [$this->watcher()->table('sales')->value('total_amount'), $this->watcher()->table('payments')->value('applied_amount')]);
    }

    public function test_race_11_past_the_lock_timeout_the_later_is_refused_service_busy_with_nothing_written(): void
    {
        // AC-028-11; D-063, TR-TIME-01: a replay holds the product's guard; a Sale that cannot take it
        // within the lock timeout is refused SERVICE_BUSY, and so is a correction against a Sale that
        // holds it; neither writes anything, and no mark.
        $evidence = $this->twoReceipts();
        $correction = $this->start($this->correction($evidence, '160000'), ['after_lock:7']);
        $waiting = $this->sale('Rina', '5', '100000');
        $sale = $this->start($waiting);
        Worker::ready($correction, $sale);

        $correction->run()->awaitBarrier('after_lock:7');
        $sale->run();
        $this->assertSame(['refused' => 'SERVICE_BUSY'], $sale->finish()->result);
        $correction->release();
        $this->assertSame('COMMITTED', ($correction->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLeftNothing($waiting);
        $this->assertSame(0, $this->watcher()->table('sales')->count());

        $holding = $this->start($this->sale('Rina', '5', '100000'), ['after_lock:7']);
        $late = $this->correction($evidence, '170000', 1);
        $refused = $this->start($late);
        Worker::ready($holding, $refused);

        $holding->run()->awaitBarrier('after_lock:7');
        $refused->run();
        $this->assertSame(['refused' => 'SERVICE_BUSY'], $refused->finish()->result);
        $holding->release();
        $this->assertSame('COMMITTED', ($holding->finish()->result ?? [])['outcome'] ?? null);
        $this->assertLeftNothing($late);

        // The Sale took its allocation from the valuation the first correction published.
        $this->assertSame([['KNOWN', '65000.000000', 3, 'KNOWN', '65000.000000']], $this->allocationsOf($this->product));
        $this->assertSame([1, 0], [$this->watcher()->table('cost_corrections')->count(), $this->watcher()->table('cost_incomplete_marks')->count()]);
    }

    public function test_race_23_a_price_change_or_a_deactivation_against_a_sale_whose_cart_is_open(): void
    {
        // AC-028-07; D-036, BR-SALE-05, BR-CAT-08: the change first, the Sale waits on the product's guard
        // and is refused PRICE_CHANGED; the Sale first, the change waits and applies to later Sales, the
        // line keeping the price it applied; a deactivation first refuses the Sale PRODUCT_INACTIVE.
        TokoContoh::stock($this->product, '10', '10000');
        $change = $this->start($this->priceChange('25000'), ['after_lock:6']);
        $cart = $this->sale('Rina', '1', '20000');
        $sale = $this->start($cart);
        Worker::ready($change, $sale);
        $change->run()->awaitBarrier('after_lock:6');
        $sale->run()->awaitBlocked($this->watcher());
        $change->release();
        $this->assertSame('COMMITTED', ($change->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'PRICE_CHANGED'], $sale->finish()->result);
        $this->assertLeftNothing($cart);

        $current = $this->revisionOf($this->product);
        $selling = $this->start($this->sale('Rina', '1', '25000'), ['after_lock:7']);
        $later = $this->start($this->priceChange('30000'));
        Worker::ready($selling, $later);
        $selling->run()->awaitBarrier('after_lock:7');
        $later->run()->awaitBlocked($this->watcher());
        $selling->release();
        $this->assertSame('COMMITTED', ($selling->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($later->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([[$current, 25000]], $this->watcher()->table('sale_lines')->get(['selling_price_revision_id', 'unit_price'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertNotSame($current, $this->revisionOf($this->product));

        $deactivation = $this->start($this->command('product.deactivate', ['productId' => $this->product], ['productVersion' => $this->versionOf($this->product)]), ['after_lock:6']);
        $inactive = $this->sale('Dewi', '1', '30000');
        $refused = $this->start($inactive);
        Worker::ready($deactivation, $refused);
        $deactivation->run()->awaitBarrier('after_lock:6');
        $refused->run()->awaitBlocked($this->watcher());
        $deactivation->release();
        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'PRODUCT_INACTIVE'], $refused->finish()->result);
        $this->assertLeftNothing($inactive);
    }

    public function test_race_25_a_business_profile_change_against_a_sale(): void
    {
        // AC-028-07; D-038, BR-CFG-03, G-17: the Sale first holds the pointer's share lock and freezes
        // the revision it read; the change waits, then makes the next revision. The change first holds
        // the pointer; the Sale waits for it and freezes the new revision. Each receipt has one revision.
        TokoContoh::stock($this->product, '10', '10000');
        $initial = $this->currentRevision();
        $sale = $this->start($this->sale('Rina', '1', '20000'), ['after_lock:7']);
        $change = $this->start($this->profileChange($initial, 'Toko Contoh Dua'), [], ProfileChangeScenario::class);
        Worker::ready($sale, $change);
        $sale->run()->awaitBarrier('after_lock:7');
        $change->run()->awaitBlocked($this->watcher());
        $sale->release();
        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($change->finish()->result ?? [])['outcome'] ?? null);
        $second = $this->currentRevision();
        $this->assertNotSame($initial, $second);

        $changing = $this->start($this->profileChange($second, 'Toko Contoh Tiga'), ['after_lock:2.4'], ProfileChangeScenario::class);
        $waiting = $this->start($this->sale('Dewi', '1', '20000'));
        Worker::ready($changing, $waiting);
        $changing->run()->awaitBarrier('after_lock:2.4');
        $waiting->run()->awaitBlocked($this->watcher());
        $changing->release();
        $this->assertSame('COMMITTED', ($changing->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($waiting->finish()->result ?? [])['outcome'] ?? null);
        $third = $this->currentRevision();

        $this->assertSame([[$initial, 'Toko Contoh'], [$third, 'Toko Contoh Tiga']], $this->watcher()->table('receipt_snapshots as s')->join('business_profile_revisions as r', 'r.id', '=', 's.business_profile_revision_id')
            ->orderBy('s.sale_id')->get(['s.business_profile_revision_id', 'r.shop_name'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_race_18_a_sale_of_a_cart_line_taken_before_a_barcode_move_waits_and_keeps_its_product(): void
    {
        // AC-028-07; D-051, D-052, BR-CAT-30: the cart took the line of the product the code found; the
        // move holds both products' guards; the Sale of that line waits, then commits its own product,
        // never the code's new one, which the code now finds.
        $right = TokoContoh::product('RACE-S3', ['categoryId' => $this->category, 'name' => 'Produk Benar', 'sellingPrice' => '30000']);
        TokoContoh::stock($this->product, '5', '5000');
        TokoContoh::stock($right, '5', '5000');
        $token = 'T-'.Str::random(8);
        TokoContoh::barcode($this->product, $token);
        $move = $this->start($this->command('barcode.correct_misassignment', ['token' => $token, 'targetProductId' => $right, 'reason' => 'Salah pasang'], ['mapping' => ['productId' => $this->product, 'state' => 'ACTIVE']]), ['after_lock:6']);
        $sale = $this->start($this->sale('Rina', '1', '20000'));
        Worker::ready($move, $sale);

        $move->run()->awaitBarrier('after_lock:6');
        $sale->run()->awaitBlocked($this->watcher());
        $move->release();

        $this->assertSame('COMMITTED', ($move->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([[$this->product, 20000]], $this->watcher()->table('sale_lines')->get(['product_id', 'unit_price'])->map(static fn (object $row): array => array_values((array) $row))->all());
        $this->assertSame([['4.000', 2], ['5.000', 1]], [$this->balanceOf($this->product), $this->balanceOf($right)]);
        $this->assertSame($right, $this->watcher()->table('barcode_tokens as t')->join('barcode_active_mappings as am', 'am.token_id', '=', 't.id')->where('t.token', $token)->value('am.product_id'));
    }

    /**
     * Two receipts of the product, 10 worth Rp100.000 and 10 worth Rp140.000: the evidence of the second.
     */
    private function twoReceipts(): int
    {
        TokoContoh::stock($this->product, '10', '100000');
        TokoContoh::stock($this->product, '10', '140000');
        $movement = $this->watcher()->table('stock_movements')->where('product_id', $this->product)->where('product_sequence', 2)->value('id');

        return TokoContoh::evidenceOf(is_int($movement) ? $movement : 0);
    }

    /**
     * RC-15 of a cost source by the Owner, reviewing the correction of this number (0 for none).
     *
     * @return array<string, mixed>
     */
    private function correction(int $evidence, string $amount, int $seen = 0): array
    {
        return $this->command('cost_correction.confirm', ['evidenceId' => $evidence, 'newCost' => ['amount' => $amount], 'reason' => 'Nota pemasok'], ['correctionNo' => $seen]);
    }

    /**
     * RC-08 of one line of the product by the Owner, at the version it stands at.
     *
     * @return array<string, mixed>
     */
    private function manualIssue(string $quantity): array
    {
        return $this->command('manual_issue.confirm', ['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => [['productId' => $this->product, 'quantity' => $quantity]]], ['products' => [['productId' => $this->product, 'version' => $this->versionOf($this->product)]]]);
    }

    /**
     * RC-18 of the product by the Owner with a new price, at the version it stands at.
     *
     * @return array<string, mixed>
     */
    private function priceChange(string $price): array
    {
        return $this->command('product.update', ['productId' => $this->product, 'name' => 'Produk Jual', 'categoryId' => $this->category, 'brand' => null, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'sellingPrice' => $price, 'minimumStock' => '0'], ['productVersion' => $this->versionOf($this->product)]);
    }

    /**
     * RC-32 of the Owner, reviewing this revision, with a new shop name and the logo kept.
     *
     * @return array<string, mixed>
     */
    private function profileChange(int $revision, string $shop): array
    {
        return ['actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'revision' => $revision, 'shop' => $shop];
    }

    private function currentRevision(): int
    {
        $revision = $this->watcher()->table('business_profile_current')->where('id', 1)->value('revision_id');

        return is_int($revision) ? $revision : 0;
    }

    private function revisionOf(int $product): int
    {
        $revision = $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id');

        return is_int($revision) ? $revision : 0;
    }

    private function versionOf(int $product): int
    {
        $version = $this->watcher()->table('products')->where('id', $product)->value('version');

        return is_int($version) ? $version : 0;
    }

    /**
     * RC-01 of one line of a product, by a person in their own session, at the product's revision in
     * force unless one is given, paid with this tendered cash; a new intent and key unless given.
     *
     * @return array<string, mixed>
     */
    private function sale(string $person, string $quantity, string $tendered, ?int $product = null, ?int $revision = null, ?string $intent = null, ?string $key = null): array
    {
        $product ??= $this->product;
        $current = $this->watcher()->table('products')->where('id', $product)->value('current_price_revision_id');

        return [
            'operation' => self::OPERATION,
            'actor' => $person === 'Dewi' ? $this->dewi : $this->rina,
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['lines' => [['priceRevisionId' => $revision ?? (is_int($current) ? $current : 0)]]],
            'payload' => ['sessionId' => $person === 'Dewi' ? $this->dewiSession : $this->rinaSession, 'lines' => [['productId' => $product, 'quantity' => $quantity]], 'payment' => ['method' => 'CASH', 'tendered' => $tendered]],
        ];
    }

    /**
     * Another command of the Owner, with its expected state.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, array $expected = []): array
    {
        return ['operation' => $operation, 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'expected' => $expected, 'payload' => $payload];
    }

    /**
     * The counters of the Sales, in the order of their keys.
     *
     * @return list<int>
     */
    private function counters(): array
    {
        $counters = [];

        foreach ($this->watcher()->table('sales')->orderBy('id')->pluck('sale_number_seq') as $counter) {
            $counters[] = is_int($counter) ? $counter : 0;
        }

        return $counters;
    }

    /**
     * A product's balance: on hand and the last sequence.
     *
     * @return list<mixed>
     */
    private function balanceOf(int $product): array
    {
        return array_values((array) $this->watcher()->table('stock_balances')->where('product_id', $product)->first(['on_hand', 'last_sequence']));
    }

    /**
     * A product's allocations: the fact's status, amount and publication, the projection's status and amount.
     *
     * @return list<list<mixed>>
     */
    private function allocationsOf(int $product): array
    {
        return array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $product)->orderBy('a.id')
            ->get(['a.at_commit_status', 'a.at_commit_amount', 'a.based_on_publication_no', 'p.current_status', 'p.current_amount'])
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
     * @param  class-string<Scenario>  $scenario
     */
    private function start(array $arguments, array $barriers = [], string $scenario = UserCommandScenario::class): Worker
    {
        $worker = Worker::start($scenario, $arguments, $barriers);
        $this->workers[] = $worker;

        return $worker;
    }
}
