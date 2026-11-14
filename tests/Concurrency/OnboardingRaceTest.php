<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of onboarding (AC-035-01 to AC-035-05; TECHNICAL_RULES section 8, RACE-04, RACE-06, RACE-11,
 * RACE-31 and RACE-32; SCHEMA section 7; G-10, G-22, G-24, G-26; TASK-035 section 7, L3; suites OPEN01 and
 * FIN03), each with processes of their own connections under barrier control:
 *
 * - RACE-04, the start of onboarding against a running posting, in both orders: the one that holds the
 *   onboarding state's lock first is in force; a posting that waited for a start is refused
 *   ONBOARDING_FREEZE with nothing written;
 * - RACE-32: two starts, two releases, a start against a release — one of each at most;
 * - RACE-06 and RACE-31: two entries of one product, a zero count against a positive row, two withdrawals
 *   of one zero count, two replacements of one reversed line — at most one effective entry, the loser
 *   refused with nothing written — and a withdrawal against a new entry of its product, which then counts;
 * - RACE-11 from an OPENING: the completion of an OPENING's cost holds the product's guard for its whole
 *   replay, and a manual issue and a checkout of the product wait and take their allocation from the
 *   valuation it published;
 * - RACE-01 and RACE-02 for each of the five operations (EXECUTION_CONTEXT section 10, a command): the
 *   same key, or another key of the same intent, waits on the original's identity and answers its result.
 *
 * No lock is held while people count (AC-035-05; TR-TX-03): every lock is a command's, to its commit.
 */
final class OnboardingRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private int $owner = 0;

    private string $epoch = '';

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
        $this->epoch = TokoContoh::recoveryEpoch();
        $this->product = TokoContoh::product('RACE-O1', ['categoryId' => TokoContoh::category(), 'name' => 'Produk Awal']);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_race_04_a_posting_that_holds_the_onboarding_lock_first_commits_and_the_start_follows(): void
    {
        // G-10, TR §7.1 step 2.3: the receiving holds the share lock of the onboarding state to its
        // commit; the start waits for its update lock, then starts from NOT_STARTED. Both commit.
        $posting = $this->start($this->receiving(), ['after_lock:7']);
        $starting = $this->start($this->command('onboarding.start', [], ['onboardingVersion' => 1]));
        Worker::ready($posting, $starting);

        $posting->run()->awaitBarrier('after_lock:7');
        $starting->run()->awaitBlocked($this->watcher());
        $posting->release();

        $this->assertSame('COMMITTED', ($posting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($starting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['ACTIVE', 2], array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version'])));
        $this->assertSame(1, $this->watcher()->table('stock_movements')->where('movement_type', 'RECEIPT')->count());
    }

    public function test_race_04_a_posting_that_waited_for_the_start_is_refused_with_nothing_written(): void
    {
        // G-10: the start holds the update lock of the onboarding state; the receiving waits for its share
        // lock, then reads ACTIVE: ONBOARDING_FREEZE, no fact.
        $starting = $this->start($this->command('onboarding.start', [], ['onboardingVersion' => 1]), ['after_lock:2.3']);
        $waiting = $this->receiving();
        $posting = $this->start($waiting);
        Worker::ready($starting, $posting);

        $starting->run()->awaitBarrier('after_lock:2.3');
        $posting->run()->awaitBlocked($this->watcher());
        $starting->release();

        $this->assertSame('COMMITTED', ($starting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => 'ONBOARDING_FREEZE'], $posting->finish()->result);
        $this->assertSame(0, $this->watcher()->table('stock_movements')->count());
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $waiting['intent'])->count());
    }

    public function test_race_32_one_start_and_one_release_at_most_also_against_each_other(): void
    {
        // D-071: the first of two starts commits; the second waits for the update lock and finds ACTIVE.
        $this->assertOneOfTwo($this->command('onboarding.start', [], ['onboardingVersion' => 1]), $this->command('onboarding.start', [], ['onboardingVersion' => 1]), 'after_lock:2.3', 'STALE_VERSION');
        $this->assertSame(['ACTIVE', 2], array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version'])));

        // A release against a start: the release commits RELEASED; the start finds it (G-26).
        $this->assertOneOfTwo($this->command('onboarding.release', [], ['onboardingVersion' => 2]), $this->command('onboarding.start', [], ['onboardingVersion' => 2]), 'after_lock:2.3', 'STALE_VERSION');
        $this->assertSame(['RELEASED', 3], array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version'])));
        $this->assertSame(1, $this->watcher()->table('onboarding_releases')->count());
    }

    public function test_race_32_two_releases_give_one_release_record(): void
    {
        TokoContoh::startOnboarding();

        $this->assertOneOfTwo($this->command('onboarding.release', [], ['onboardingVersion' => 2]), $this->command('onboarding.release', [], ['onboardingVersion' => 2]), 'after_lock:2.3', 'STALE_VERSION');
        $this->assertSame(1, $this->watcher()->table('onboarding_releases')->count());
        $this->assertSame(1, $this->watcher()->table('audit_events')->where('action', 'ONBOARDING_RELEASE')->count());
    }

    public function test_race_06_and_race_31_two_entries_of_one_product_give_one_effective_entry(): void
    {
        // G-22: both hold the onboarding share lock; the second waits for the product's guard, then finds
        // the product counted. Two positive rows: one OPENING. A zero count against a positive row: one
        // zero count.
        TokoContoh::startOnboarding();
        $this->assertOneOfTwo($this->entry($this->product, '5'), $this->entry($this->product, '6'), 'after_lock:7', 'VALIDATION_FAILED');
        $this->assertSame(['5.000'], $this->watcher()->table('stock_movements')->where('product_id', $this->product)->pluck('quantity')->all());

        $other = TokoContoh::product('RACE-O2', ['categoryId' => TokoContoh::category('Lain'), 'name' => 'Produk Lain']);
        $this->assertOneOfTwo($this->entry($other, '0'), $this->entry($other, '3'), 'after_lock:7', 'VALIDATION_FAILED');
        $this->assertSame([1, 0], [$this->watcher()->table('onboarding_zero_counts')->where('product_id', $other)->count(), $this->watcher()->table('stock_movements')->where('product_id', $other)->count()]);
        $this->assertSame(1, $this->watcher()->table('opening_stock_lines')->count());
    }

    public function test_race_31_a_zero_count_is_withdrawn_once_and_a_withdrawal_against_a_new_entry_lets_it_count(): void
    {
        // D-087: two withdrawals of one zero count, under the product's guard: one; then a withdrawal
        // holds the guard while a positive row of its product waits, and the row counts after it.
        TokoContoh::startOnboarding();
        $zeroCount = TokoContoh::zeroCount($this->product);
        $this->assertOneOfTwo($this->withdrawal($zeroCount), $this->withdrawal($zeroCount), 'after_lock:6', 'STALE_VERSION');
        $this->assertSame(1, $this->watcher()->table('onboarding_zero_count_withdrawals')->count());

        $other = TokoContoh::product('RACE-O2', ['categoryId' => TokoContoh::category('Lain'), 'name' => 'Produk Lain']);
        $withdrawing = $this->start($this->withdrawal(TokoContoh::zeroCount($other)), ['after_lock:6']);
        $counting = $this->start($this->entry($other, '4'));
        Worker::ready($withdrawing, $counting);

        $withdrawing->run()->awaitBarrier('after_lock:6');
        $counting->run()->awaitBlocked($this->watcher());
        $withdrawing->release();

        $this->assertSame('COMMITTED', ($withdrawing->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($counting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['4.000'], $this->watcher()->table('stock_movements')->where('product_id', $other)->pluck('quantity')->all());
    }

    public function test_race_31_a_reversed_line_is_replaced_once(): void
    {
        // G-24, BR-INV-48: two replacements of one reversed line, under the product's guard: one
        // replacement OPENING; the second finds the line replaced.
        TokoContoh::startOnboarding();
        $original = TokoContoh::openingStock($this->product, '10', '1000');
        TokoContoh::reverse($original);
        $line = TokoContoh::openingLineOf($original);

        $this->assertOneOfTwo($this->replacement($line, '8'), $this->replacement($line, '9'), 'after_lock:7', 'STALE_VERSION');
        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING'], $this->watcher()->table('stock_movements')->where('product_id', $this->product)->orderBy('product_sequence')->pluck('movement_type')->all());
        $this->assertSame(1, $this->watcher()->table('opening_stock_lines')->where('replaces_line_id', $line)->count());
    }

    public function test_race_11_from_an_opening_postings_wait_for_its_replay_and_take_the_published_valuation(): void
    {
        // BR-COST-26, ADR-008 section 7: 10 at UNKNOWN in onboarding; after the release, completing the
        // OPENING's cost at Rp50.000 holds the product's guard from step 6 to its commit. A manual issue of
        // 2 waits, then takes Rp10.000; a checkout of 3 waits too, then takes Rp15.000.
        TokoContoh::startOnboarding();
        $opening = TokoContoh::openingStock($this->product, '10', null);
        TokoContoh::releaseOnboarding();
        $session = TokoContoh::openSession('Hendra', TokoContoh::register());
        $correction = $this->command('cost_correction.confirm', ['evidenceId' => TokoContoh::evidenceOf($opening), 'newCost' => ['amount' => '50000'], 'reason' => 'Nota ditemukan'], ['correctionNo' => 0]);

        $correcting = $this->start($correction, ['after_lock:7']);
        $issuing = $this->start($this->command('manual_issue.confirm', ['reasonCode' => 'DAMAGED', 'note' => null, 'lines' => [['productId' => $this->product, 'quantity' => '2']]], ['products' => [['productId' => $this->product, 'version' => 1]]]));
        Worker::ready($correcting, $issuing);

        $correcting->run()->awaitBarrier('after_lock:7');
        $issuing->run()->awaitBlocked($this->watcher());
        $correcting->release();

        $this->assertSame('COMMITTED', ($correcting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($issuing->finish()->result ?? [])['outcome'] ?? null);

        $evidence = TokoContoh::evidenceOf($opening);
        $again = $this->command('cost_correction.confirm', ['evidenceId' => $evidence, 'newCost' => ['amount' => '50000'], 'reason' => 'Nota yang sama'], ['correctionNo' => 1]);
        $revision = $this->watcher()->table('products')->where('id', $this->product)->value('current_price_revision_id');
        $sale = $this->command('sale.checkout', ['sessionId' => $session, 'lines' => [['productId' => $this->product, 'quantity' => '3']], 'payment' => ['method' => 'CASH', 'tendered' => '22500']], ['lines' => [['priceRevisionId' => is_int($revision) ? $revision : 0]]]);
        $correcting = $this->start($again, ['after_lock:7']);
        $selling = $this->start($sale);
        Worker::ready($correcting, $selling);

        $correcting->run()->awaitBarrier('after_lock:7');
        $selling->run()->awaitBlocked($this->watcher());
        $correcting->release();

        $this->assertSame('COMMITTED', ($correcting->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame('COMMITTED', ($selling->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame([['MANUAL_ISSUE', 'KNOWN', '10000.000000', 'KNOWN', '10000.000000'], ['SALE_ISSUE', 'KNOWN', '15000.000000', 'KNOWN', '15000.000000']], array_values($this->watcher()->table('issue_cost_allocations as a')->join('issue_cost_allocation_projections as p', 'p.allocation_id', '=', 'a.id')
            ->where('a.product_id', $this->product)->orderBy('a.product_sequence')->get(['a.issue_type', 'a.at_commit_status', 'a.at_commit_amount', 'p.current_status', 'p.current_amount'])->map(static fn (object $row): array => array_values((array) $row))->all()));
        $this->assertSame(['KNOWN', '25000.000000'], array_values((array) $this->watcher()->table('inventory_valuations')->where('product_id', $this->product)->first(['value_status', 'value'])));
    }

    public function test_race_01_each_onboarding_command_sent_twice_with_its_key_runs_once(): void
    {
        $this->assertEachRunsOnce(true);
    }

    public function test_race_02_each_onboarding_command_sent_again_under_another_key_of_its_intent_runs_once(): void
    {
        $this->assertEachRunsOnce(false);
    }

    /**
     * RACE-01 or RACE-02 for each of the five operations, in the order of one period: the start, an entry,
     * the withdrawal of a zero count, the replacement of a reversed OPENING and the release (ADR-007).
     */
    private function assertEachRunsOnce(bool $sameKey): void
    {
        $this->assertRunsOnce($this->command('onboarding.start', [], ['onboardingVersion' => 1]), $sameKey);
        $this->assertRunsOnce($this->entry($this->product, '5'), $sameKey);

        $other = TokoContoh::product('RACE-O2', ['categoryId' => TokoContoh::category('Lain'), 'name' => 'Produk Lain']);
        $this->assertRunsOnce($this->withdrawal(TokoContoh::zeroCount($other)), $sameKey);

        $opening = TokoContoh::openingOf($this->product);
        TokoContoh::reverse($opening);
        $this->assertRunsOnce($this->replacement(TokoContoh::openingLineOf($opening), '4'), $sameKey);
        $this->assertRunsOnce($this->command('onboarding.release', [], ['onboardingVersion' => 2]), $sameKey);

        $this->assertSame(['OPENING', 'REVERSAL', 'OPENING'], $this->watcher()->table('stock_movements')->where('product_id', $this->product)->orderBy('product_sequence')->pluck('movement_type')->all());
        $this->assertSame([1, 1, ['RELEASED', 3]], [
            $this->watcher()->table('onboarding_zero_count_withdrawals')->count(),
            $this->watcher()->table('onboarding_releases')->count(),
            array_values((array) $this->watcher()->table('onboarding_state')->first(['state', 'version'])),
        ]);
    }

    /**
     * RACE-01 with the same key, or RACE-02 with another key of the same intent: the repeat waits on the
     * original's identity, then answers its result, and the command ran once.
     *
     * @param  array<string, mixed>  $original
     */
    private function assertRunsOnce(array $original, bool $sameKey): void
    {
        $operation = is_string($original['operation'] ?? null) ? $original['operation'] : '';
        $first = $this->start($original, ['after_identity']);
        $repeat = $this->start([...$original, 'key' => $sameKey ? ($original['key'] ?? null) : (string) Str::uuid()]);
        Worker::ready($first, $repeat);

        $first->run()->awaitBarrier('after_identity');
        $repeat->run()->awaitBlocked($this->watcher());
        $first->release();

        $committed = $first->finish()->result ?? [];
        $this->assertSame(['COMMITTED', false], [$committed['outcome'] ?? null, $committed['repeated'] ?? null], $operation);
        $this->assertSame(['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $committed['commandId'] ?? null], $repeat->finish()->result, $operation);
        $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $original['intent'] ?? null)->count(), $operation);
    }

    /**
     * Two commands of which the first, paused at a barrier with its locks, commits, and the second, which
     * waited for them, is refused with this code, under its own intent, with nothing written.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function assertOneOfTwo(array $first, array $second, string $barrier, string $refused): void
    {
        $winner = $this->start($first, [$barrier]);
        $loser = $this->start($second);
        Worker::ready($winner, $loser);

        $winner->run()->awaitBarrier($barrier);
        $loser->run()->awaitBlocked($this->watcher());
        $winner->release();

        $this->assertSame('COMMITTED', ($winner->finish()->result ?? [])['outcome'] ?? null);
        $this->assertSame(['refused' => $refused], $loser->finish()->result);
        $this->assertSame(0, $this->watcher()->table('command_identities')->where('source_intent_id', $second['intent'] ?? null)->count());
    }

    /**
     * RC-11 of one row of a product by the Owner, at the versions as they stand: a zero count for "0",
     * else a positive row at a KNOWN cost.
     *
     * @return array<string, mixed>
     */
    private function entry(int $product, string $quantity): array
    {
        $row = $quantity === '0'
            ? ['productId' => $product, 'quantity' => '0', 'countReference' => null]
            : ['productId' => $product, 'quantity' => $quantity, 'countReference' => 'HIT-01', 'cost' => ['status' => 'KNOWN', 'amount' => '1000']];

        return $this->command('opening_stock.confirm', ['rows' => [$row]], ['onboardingVersion' => $this->onboardingVersion(), 'products' => [['productId' => $product, 'version' => $this->versionOf($product)]]]);
    }

    /**
     * RC-14 of a zero count by the Owner.
     *
     * @return array<string, mixed>
     */
    private function withdrawal(int $zeroCount): array
    {
        return $this->command('onboarding.withdraw_zero_count', ['zeroCountId' => $zeroCount, 'reason' => 'Salah hitung'], ['onboardingVersion' => $this->onboardingVersion()]);
    }

    /**
     * RC-48 of a reversed line by the Owner, at an UNKNOWN cost.
     *
     * @return array<string, mixed>
     */
    private function replacement(int $line, string $quantity): array
    {
        return $this->command('opening_stock.replace', ['replacesLineId' => $line, 'quantity' => $quantity, 'countReference' => 'HIT-02', 'cost' => ['status' => 'UNKNOWN'], 'reason' => 'Hitung ulang'], ['onboardingVersion' => $this->onboardingVersion(), 'productVersion' => $this->versionOf($this->product)]);
    }

    /**
     * RC-07 of one line of the product by the Owner.
     *
     * @return array<string, mixed>
     */
    private function receiving(): array
    {
        return $this->command('receiving.confirm', ['reference' => null, 'documentDate' => null, 'lines' => [['productId' => $this->product, 'quantity' => '1', 'cost' => ['status' => 'KNOWN', 'amount' => '1000']]]], ['products' => [['productId' => $this->product, 'version' => 1]]]);
    }

    /**
     * A command by the Owner under a new intent and key.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function command(string $operation, array $payload, array $expected): array
    {
        return ['operation' => $operation, 'actor' => $this->owner, 'intent' => (string) Str::uuid(), 'key' => (string) Str::uuid(), 'epoch' => $this->epoch, 'expected' => $expected, 'payload' => $payload];
    }

    private function onboardingVersion(): int
    {
        $version = $this->watcher()->table('onboarding_state')->value('version');

        return is_int($version) ? $version : 0;
    }

    private function versionOf(int $product): int
    {
        $version = $this->watcher()->table('products')->where('id', $product)->value('version');

        return is_int($version) ? $version : 0;
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
