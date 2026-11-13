<?php

namespace Tests\Concurrency;

use App\Actions\Cash\AddReviewNote;
use App\Actions\Cash\CloseCashSessionAssisted;
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
 * The races of RC-04 (AC-032-01, AC-032-03; TECHNICAL_RULES sections 7.2 and 8, family Close; BR-CASH-11,
 * BR-CASH-17, BR-CASH-18; D-069 (d), D-087; EXECUTION_CONTEXT section 10, a command; TASK-032 section 7, L3;
 * suites SESSION10 and ACC01), each with processes of their own connections under barrier control:
 *
 * - RACE-09, the Owner-assisted close against the user's own close, in both orders: the one that takes the
 *   session's lock first closes it; the other waits for the lock, then is refused SESSION_NOT_OPEN, never
 *   overwriting; one CLOSED session, one close, one report;
 * - RACE-28: the deactivation of a cashier who holds an OPEN session, holding her account's lock, against a
 *   checkout of hers, which waits for that lock and is refused before any fact; the session stays OPEN and
 *   unchanged, and the Owner-assisted close then succeeds, she staying its responsible user;
 * - RACE-01 and RACE-02 of RC-04 and RC-43: the same key, or another key of the same intent, answers the one
 *   execution; another payload under the key is held (review R-01);
 * - RACE-35 for RC-04: a settlement before its read no longer counts, and a checkout whose receipt is
 *   written after its read waits for the session's lock and is refused SESSION_NOT_OPEN (review R-02).
 */
final class AssistedCloseRaceTest extends DatabaseTestCase
{
    private const string REASON = 'Kasir tidak bisa menutup sesinya.';

    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private int $rina = 0;

    private string $epoch = '';

    private int $register = 0;

    /** Rp20.000 a unit, counted whole. */
    private int $product = 0;

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
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
        $this->product = TokoContoh::product('RACE-A1', ['name' => 'Produk Tutup', 'sellingPrice' => '20000']);
        TokoContoh::stock($this->product, '10', '100000');
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_09_the_owner_assisted_close_against_the_users_own_close_in_both_orders(): void
    {
        // The Owner's close first, holding the session's lock: her own close waits, then finds the session
        // CLOSED; the Owner's count stands.
        $session = TokoContoh::openSession('Rina', $this->register);
        $assisted = $this->start($this->assisted($session, '0'), ['after_lock:4']);
        $own = $this->start(['session' => $session, 'activityVersion' => 0, 'physicalCash' => '5000'], [], SessionCloseScenario::class);
        Worker::ready($assisted, $own);

        $assisted->run()->awaitBarrier('after_lock:4');
        $own->run()->awaitBlocked($this->watcher());
        $assisted->release();

        $this->assertSame('COMMITTED', ($assisted->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $own->finish()->result);
        $this->assertSame([['OWNER_ASSISTED', $this->owner, 0]], $this->closesOf($session));

        // Her own close first: the Owner's waits, then finds it CLOSED; her count stands.
        $session = TokoContoh::openSession('Rina', $this->register);
        $own = $this->start(['session' => $session, 'activityVersion' => 0, 'physicalCash' => '0'], ['after_lock:4'], SessionCloseScenario::class);
        $assisted = $this->start($this->assisted($session, '5000'));
        Worker::ready($own, $assisted);

        $own->run()->awaitBarrier('after_lock:4');
        $assisted->run()->awaitBlocked($this->watcher());
        $own->release();

        $this->assertSame(['outcome' => 'CLOSED'], $own->finish()->result);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $assisted->finish()->result);
        $this->assertSame([['OWN', $this->rina, 0]], $this->closesOf($session));
        $this->assertSame([2, 2], [$this->watcher()->table('cash_session_closes')->count(), $this->watcher()->table('cash_session_reports')->count()]);
    }

    public function test_race_28_a_deactivation_against_her_checkout_leaves_her_session_open_and_unchanged_for_the_owners_close(): void
    {
        $session = TokoContoh::openSession('Rina', $this->register);
        TokoContoh::sell('Rina', $session, [['productId' => $this->product, 'quantity' => '1']]);
        $kept = (array) $this->watcher()->table('cash_sessions')->where('id', $session)->first();
        $version = $this->watcher()->table('users')->where('id', $this->rina)->value('version');

        // The deactivation holds her account (step 2.2): her checkout waits at its actor guard, then is
        // refused before any fact (RACE-04).
        $deactivation = $this->start($this->command('user.deactivate', $this->owner, ['userId' => $this->rina], ['userVersion' => $version]), ['after_lock:2.2']);
        $checkout = $this->sale($session);
        $sale = $this->start($checkout);
        Worker::ready($deactivation, $sale);

        $deactivation->run()->awaitBarrier('after_lock:2.2');
        $sale->run()->awaitBlocked($this->watcher());
        $deactivation->release();

        $this->assertSame('COMMITTED', ($deactivation->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'FORBIDDEN'], $sale->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $checkout['intent'])->count());

        // BR-CASH-11: the session OPEN and unchanged, its activity version and responsible user included.
        $this->assertSame('DEACTIVATED', $this->watcher()->table('users')->where('id', $this->rina)->value('status'));
        $this->assertSame($kept, (array) $this->watcher()->table('cash_sessions')->where('id', $session)->first());

        // The Owner-assisted close stays available and succeeds (WF-09).
        $close = $this->start($this->assisted($session, '20000', 1));
        Worker::ready($close);
        $this->assertSame('COMMITTED', ($close->run()->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['CLOSED', $this->rina], array_values((array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(['status', 'responsible_user_id'])));
        $this->assertSame([['OWNER_ASSISTED', $this->owner, 0]], $this->closesOf($session));
    }

    public function test_race_01_and_race_02_of_rc_04_and_rc_43_each_run_once_and_another_payload_under_the_key_is_held(): void
    {
        // EXECUTION_CONTEXT section 10, a command; RACE-01, RACE-02 (review R-01): the same key, or another
        // key of the same intent, waits on the first's identity, then answers its result; another payload
        // under the key is refused IDEMPOTENCY_CONFLICT and writes nothing.
        $closed = TokoContoh::closeSession(TokoContoh::openSession('Rina', $this->register));

        foreach (['same key', 'another key', 'another payload'] as $case) {
            $session = TokoContoh::openSession('Rina', $this->register);
            $commands = [
                'RC-04' => fn (string $intent, string $key, string $count): array => $this->assisted($session, $count, 0, $intent, $key),
                'RC-43' => fn (string $intent, string $key, string $count): array => $this->command(AddReviewNote::OPERATION, $this->owner, ['sessionId' => $closed, 'text' => 'Catatan '.$count], [], $intent, $key),
            ];

            foreach ($commands as $name => $make) {
                $intent = (string) Str::uuid();
                $key = (string) Str::uuid();
                $original = $this->start($make($intent, $key, '0'), ['after_identity']);
                $repeat = $this->start($make($intent, $case === 'another key' ? (string) Str::uuid() : $key, $case === 'another payload' ? '5000' : '0'));
                Worker::ready($original, $repeat);

                $original->run()->awaitBarrier('after_identity');
                $repeat->run()->awaitBlocked($this->watcher());
                $original->release();

                $first = $original->finish()->result ?? [];
                $this->assertSame(['COMMITTED', false], [$first['outcome'] ?? null, $first['repeated'] ?? null], $name.' '.$case);
                $this->assertSame(
                    $case === 'another payload' ? ['refused' => 'IDEMPOTENCY_CONFLICT'] : ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $first['commandId'] ?? null],
                    $repeat->finish()->result,
                    $name.' '.$case,
                );
                $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $name.' '.$case);
            }

            $this->assertSame([['OWNER_ASSISTED', $this->owner, 0]], $this->closesOf($session), $case);
        }

        // One note per committed intent: three.
        $this->assertSame(3, $this->watcher()->table('cash_session_review_notes')->where('session_id', $closed)->count());
    }

    public function test_race_35_of_rc_04_a_settlement_before_its_read_no_longer_counts_and_a_later_checkout_waits_for_its_lock(): void
    {
        // G-27, D-088, D-089 for the Owner-assisted close (review R-02): a checkout receipt of the
        // responsible user PENDING when RC-04 reads it refuses the close; settled by the Owner's status
        // check while RC-04 holds the session's lock, before its read — the check takes no session lock —
        // it no longer counts, and the close succeeds.
        $session = TokoContoh::openSession('Rina', $this->register);
        $intent = (string) Str::uuid();
        app(RequestReceipts::class)->record($this->rina, CheckoutSale::OPERATION, $intent, (string) Str::uuid(), $this->epoch, CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'));

        $refused = $this->start($this->assisted($session, '0'));
        Worker::ready($refused);
        $this->assertSame(['refused' => 'RECOUNT_REQUIRED'], $refused->run()->finish()->result);

        $close = $this->start($this->assisted($session, '0'), ['after_lock:4']);
        Worker::ready($close);
        $close->run()->awaitBarrier('after_lock:4');
        $owner = User::query()->findOrFail($this->owner);
        $this->assertSame('NOT_COMMITTED', app(ResolveIntent::class)($owner, CheckoutSale::OPERATION, $intent, $this->epoch, CarbonImmutable::now('UTC'))->outcome);
        $close->release();
        $this->assertSame('COMMITTED', ($close->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['OWNER_ASSISTED', $this->owner, 0]], $this->closesOf($session));

        // A checkout whose receipt is written after RC-04's read — the close past its first write — waits
        // for the session's lock and is refused SESSION_NOT_OPEN before any fact.
        $session = TokoContoh::openSession('Rina', $this->register);
        $closing = $this->start($this->assisted($session, '0'), ['after_write:1']);
        $late = $this->sale($session);
        $waiting = $this->start($late);
        Worker::ready($closing, $waiting);

        $closing->run()->awaitBarrier('after_write:1');
        app(RequestReceipts::class)->record($this->rina, CheckoutSale::OPERATION, self::text($late['intent']), self::text($late['key']), $this->epoch, CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP'));
        $waiting->run()->awaitBlocked($this->watcher());
        $closing->release();

        $this->assertSame('COMMITTED', ($closing->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'SESSION_NOT_OPEN'], $waiting->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $late['intent'])->count());
        $this->assertSame(0, $this->watcher()->table('sales')->where('cash_session_id', $session)->count());
    }

    /**
     * RC-04 of the session by the Owner, against this version, with this count; a new intent and key
     * unless given.
     *
     * @return array<string, mixed>
     */
    private function assisted(int $session, string $physicalCash, int $version = 0, ?string $intent = null, ?string $key = null): array
    {
        return $this->command(CloseCashSessionAssisted::OPERATION, $this->owner, ['sessionId' => $session, 'physicalCash' => $physicalCash, 'reason' => self::REASON], ['activityVersion' => $version], $intent, $key);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * RC-01 of one unit of the product by Rina in this session, paid with the exact cash.
     *
     * @return array<string, mixed>
     */
    private function sale(int $session): array
    {
        $revision = $this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id');

        return $this->command(CheckoutSale::OPERATION, $this->rina, ['sessionId' => $session, 'lines' => [['productId' => $this->product, 'quantity' => '1']], 'payment' => ['method' => 'CASH', 'tendered' => '20000']], ['lines' => [['priceRevisionId' => is_int($revision) ? $revision : 0]]]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function command(string $operation, int $actor, array $payload, array $expected, ?string $intent = null, ?string $key = null): array
    {
        return ['operation' => $operation, 'actor' => $actor, 'intent' => $intent ?? (string) Str::uuid(), 'key' => $key ?? (string) Str::uuid(), 'epoch' => $this->epoch, 'expected' => $expected, 'payload' => $payload];
    }

    /**
     * The closes of a session: their kind, actor and variance.
     *
     * @return list<list<mixed>>
     */
    private function closesOf(int $session): array
    {
        $closes = [];

        foreach ($this->watcher()->table('cash_session_closes')->where('session_id', $session)->get(['close_kind', 'close_actor_user_id', 'variance']) as $row) {
            $closes[] = array_values((array) $row);
        }

        return $closes;
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
