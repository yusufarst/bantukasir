<?php

namespace Tests\Concurrency;

use App\Actions\Commands\ResolveIntent;
use App\Actions\Sales\CheckoutSale;
use App\Domain\Commands\RequestReceipts;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Scenario;
use Tests\Harness\Race\Worker;
use Tests\Harness\Sales\SessionCloseScenario;

/**
 * The races of RC-03 (AC-030-02, AC-030-03, AC-030-04; TECHNICAL_RULES sections 7.2 and 8, family Close;
 * BR-CASH-14 to BR-CASH-17; G-27; D-088, D-089; EXECUTION_CONTEXT section 10, a command; TASK-030 section
 * 7, L3; suites SESSION05 and SESSION06), each with processes of their own connections under barrier
 * control:
 *
 * - RACE-08, the close's side: a Sale first commits and the close needs a recount; a close first makes
 *   the Sale wait for the session's lock and refuses it SESSION_NOT_OPEN; no successful Sale is missing
 *   from the report;
 * - RACE-09, the own part, with RACE-01 and RACE-02: the same key, or another key of the same intent,
 *   answers the one close; another close of the session, with another count, waits for the lock and is
 *   refused SESSION_NOT_OPEN, never overwriting;
 * - RACE-35 with the settlement of D-089: a checkout receipt PENDING when the close reads it refuses the
 *   close, and no other command; settled by a status check while a close holds the session's lock, before
 *   its read, it no longer counts; a checkout whose receipt is written after the close's read waits for
 *   the lock and is refused SESSION_NOT_OPEN before any fact.
 */
final class CloseRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $rina = 0;

    private string $epoch = '';

    private int $register = 0;

    /** Rp20.000 a unit, counted whole. */
    private int $product = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rina = TokoContoh::person('Rina')['id'];
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
        $this->product = TokoContoh::product('RACE-C1', ['name' => 'Produk Tutup', 'sellingPrice' => '20000']);
        TokoContoh::stock($this->product, '10', '100000');
        $this->session = TokoContoh::openSession('Rina', $this->register);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_08_in_both_orders_no_successful_sale_is_missing_from_the_report(): void
    {
        // AC-030-02; BR-CASH-14, BR-CASH-15. The Sale first, at its product's lock, holding the session's:
        // the close waits, then finds the activity version moved and is refused RECOUNT_REQUIRED, the
        // session OPEN and nothing of the close written.
        $sale = $this->start($this->sale($this->session), ['after_lock:7']);
        $close = $this->start($this->closing($this->session, 0), [], SessionCloseScenario::class);
        Worker::ready($sale, $close);

        $sale->run()->awaitBarrier('after_lock:7');
        $close->run()->awaitBlocked($this->watcher());
        $sale->release();

        $this->assertSame('COMMITTED', ($sale->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'RECOUNT_REQUIRED'], $close->finish()->result);
        $this->assertSame(['OPEN', 1, 0], [$this->statusOf($this->session), $this->versionOf($this->session), $this->watcher()->table('cash_session_closes')->count()]);

        // The close first, counted against the new version: the Sale waits for the session's lock, then
        // is refused SESSION_NOT_OPEN before any fact; the report holds exactly the Sale that committed.
        $closing = $this->start($this->closing($this->session, 1, '20000'), ['after_lock:4'], SessionCloseScenario::class);
        $late = $this->sale($this->session);
        $waiting = $this->start($late);
        Worker::ready($closing, $waiting);

        $closing->run()->awaitBarrier('after_lock:4');
        $waiting->run()->awaitBlocked($this->watcher());
        $closing->release();

        $this->assertSame(['outcome' => 'CLOSED'], $closing->finish()->result);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $waiting->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $late['intent'])->count());
        $this->assertSame([1, [1, 20000, 20000, 0]], [$this->watcher()->table('sales')->count(), $this->reportOf($this->session)]);
    }

    public function test_race_09_a_repeat_answers_the_one_close_and_another_close_never_overwrites(): void
    {
        // AC-030-04; BR-CASH-17; RACE-01 and RACE-02 for RC-03: the same key, or another key of the same
        // intent, waits on the first's identity, then answers its result.
        foreach (['same key' => true, 'another key' => false] as $case => $sameKey) {
            $session = $sameKey ? $this->session : TokoContoh::openSession('Rina', $this->register);
            $intent = (string) Str::uuid();
            $key = (string) Str::uuid();
            $original = $this->start($this->closing($session, 0, '0', $intent, $key), ['after_identity'], SessionCloseScenario::class);
            $repeat = $this->start($this->closing($session, 0, '0', $intent, $sameKey ? $key : (string) Str::uuid()), [], SessionCloseScenario::class);
            Worker::ready($original, $repeat);

            $original->run()->awaitBarrier('after_identity');
            $repeat->run()->awaitBlocked($this->watcher());
            $original->release();

            $this->assertSame(['outcome' => 'CLOSED'], $original->finish()->result, $case);
            $this->assertSame(['outcome' => 'REPEATED'], $repeat->finish()->result, $case);
            $this->assertSame([1, 1], [$this->watcher()->table('cash_session_closes')->where('session_id', $session)->count(), $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count()], $case);
        }

        // Another close of the session, with another count, waits for the session's lock, then finds it
        // CLOSED: the first count stands.
        $session = TokoContoh::openSession('Rina', $this->register);
        $first = $this->start($this->closing($session, 0, '0'), ['after_lock:4'], SessionCloseScenario::class);
        $other = $this->closing($session, 0, '5000');
        $second = $this->start($other, [], SessionCloseScenario::class);
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_lock:4');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $this->assertSame(['outcome' => 'CLOSED'], $first->finish()->result);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $second->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $other['intent'])->count());
        $this->assertSame([[0, 0]], $this->watcher()->table('cash_session_closes')->where('session_id', $session)->get(['physical_cash', 'variance'])->map(static fn (object $row): array => array_values((array) $row))->all());
    }

    public function test_race_35_an_unresolved_checkout_receipt_refuses_the_close_until_settled_and_a_later_checkout_waits_for_the_lock(): void
    {
        // AC-030-03; G-27, D-088: a checkout receipt PENDING when the close reads it, as its request wrote
        // it, refuses the close RECOUNT_REQUIRED with nothing written; it refuses no other command, so a
        // Sale still commits in the session.
        $receipts = app(RequestReceipts::class);
        $intent = (string) Str::uuid();
        $receipts->record($this->rina, CheckoutSale::OPERATION, $intent, (string) Str::uuid(), $this->epoch, CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'));

        $refused = $this->start($this->closing($this->session, 0), [], SessionCloseScenario::class);
        $sale = $this->start($this->sale($this->session));
        Worker::ready($refused, $sale);
        $this->assertSame(['refused' => 'RECOUNT_REQUIRED'], $refused->run()->finish()->result);
        $this->assertSame(['OPEN', 0], [$this->statusOf($this->session), $this->watcher()->table('cash_session_closes')->count()]);
        $this->assertSame('COMMITTED', ($sale->run()->finish()->result ?? [])['outcome'] ?? null);

        // D-089: settled by the user's status check while a close holds the session's lock, before the
        // close reads the receipts — the check takes no session lock — it no longer counts, and the close
        // succeeds.
        $close = $this->start($this->closing($this->session, 1, '20000'), ['after_lock:4'], SessionCloseScenario::class);
        Worker::ready($close);
        $close->run()->awaitBarrier('after_lock:4');
        $rina = User::query()->findOrFail($this->rina);
        $this->assertSame('NOT_COMMITTED', app(ResolveIntent::class)($rina, CheckoutSale::OPERATION, $intent, $this->epoch, CarbonImmutable::now('UTC'))->outcome);
        $close->release();
        $this->assertSame(['outcome' => 'CLOSED'], $close->finish()->result);
        $this->assertSame([1, 20000, 20000, 0], $this->reportOf($this->session));

        // A checkout whose receipt is written after the close's read — the close past its first write —
        // waits for the session's lock and is refused SESSION_NOT_OPEN before any fact: no committed Sale
        // is missing from the report.
        $session = TokoContoh::openSession('Rina', $this->register);
        $closing = $this->start($this->closing($session, 0), ['after_write:1'], SessionCloseScenario::class);
        $late = $this->sale($session);
        $waiting = $this->start($late);
        Worker::ready($closing, $waiting);

        $closing->run()->awaitBarrier('after_write:1');
        $receipts->record($this->rina, CheckoutSale::OPERATION, self::text($late['intent']), self::text($late['key']), $this->epoch, CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'));
        $waiting->run()->awaitBlocked($this->watcher());
        $closing->release();

        $this->assertSame(['outcome' => 'CLOSED'], $closing->finish()->result);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $waiting->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $late['intent'])->count());
        $this->assertSame([0, 0, 0, 0], $this->reportOf($session));
    }

    /**
     * RC-03 of a session by its responsible user, against this version, with this count; a new intent
     * and key unless given.
     *
     * @return array<string, mixed>
     */
    private function closing(int $session, int $version, string $physicalCash = '0', ?string $intent = null, ?string $key = null): array
    {
        return ['session' => $session, 'activityVersion' => $version, 'physicalCash' => $physicalCash, 'intent' => $intent ?? (string) Str::uuid(), 'key' => $key ?? (string) Str::uuid()];
    }

    /**
     * RC-01 of one unit of the product by Rina in this session, paid with the exact cash.
     *
     * @return array<string, mixed>
     */
    private function sale(int $session): array
    {
        $revision = $this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id');

        return [
            'operation' => CheckoutSale::OPERATION,
            'actor' => $this->rina,
            'intent' => (string) Str::uuid(),
            'key' => (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => ['lines' => [['priceRevisionId' => is_int($revision) ? $revision : 0]]],
            'payload' => ['sessionId' => $session, 'lines' => [['productId' => $this->product, 'quantity' => '1']], 'payment' => ['method' => 'CASH', 'tendered' => '20000']],
        ];
    }

    private function statusOf(int $session): mixed
    {
        return $this->watcher()->table('cash_sessions')->where('id', $session)->value('status');
    }

    private function versionOf(int $session): mixed
    {
        return $this->watcher()->table('cash_sessions')->where('id', $session)->value('activity_version');
    }

    /**
     * The report's number of Sales, their value, the applied CASH and the variance.
     *
     * @return list<mixed>
     */
    private function reportOf(int $session): array
    {
        return array_values((array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->first(['completed_sale_count', 'completed_sale_value', 'cash_applied', 'variance']));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
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
