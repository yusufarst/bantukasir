<?php

namespace Tests\Feature\Cash;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Commands\CommandContext;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Envelope;
use App\Domain\Commands\Operations;
use App\Support\Time\Clock;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-02 `cash_session.open` through its route and its Action (AC-027-01, AC-027-03, AC-027-05;
 * INTERACTION_CONTRACTS RC-02; TECHNICAL_RULES section 7.2 and TR-TX-10; SCHEMA sections 5.10 and 6;
 * BR-CASH-04 to BR-CASH-07; G-16, G-18, G-23; CAP-21; EXECUTION_CONTEXT section 10, a command; suites
 * SESSION02, SESSION03, CMD01 and AUTH03; TASK-027 section 7, L2 and L4): an open with Rp0 and its
 * number, business date, audit event and result, creating no Sale and no movement; a blank or
 * malformed amount refused; an inactive register, one in use and an actor who already holds a
 * session refused; the roles, without recent authentication; the fields and the allowlist; the
 * failure after every write step, which leaves nothing and a gap in the numbers; and the numbers
 * across Asia/Jakarta midnight, in allocation order.
 */
final class OpenCashSessionTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/cash_session.open';

    /** The tables that a refused or failed open leaves as they were. */
    private const array FACTS = ['cash_sessions', 'cash_registers', 'stock_movements', 'command_identities', 'command_results', 'audit_events'];

    private int $register = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
    }

    public function test_a_cashier_opens_a_session_with_rp0_and_it_is_numbered_by_its_business_date(): void
    {
        // BR-CASH-04, BR-CASH-05; SCH-21, SCH-22; G-18: 10:30 in Asia/Jakarta on 7 November.
        $this->travelTo('2026-11-07T03:30:00.250000Z');
        $this->as('Rina');
        $rina = $this->people['Rina']['id'];
        $movements = $this->watcher()->table('stock_movements')->count();

        $envelope = $this->envelopeOf($this->register, '0');
        $response = $this->open($envelope)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed.number', 'cashier');
        $session = self::number($response->json('result.id'));
        $row = (array) $this->watcher()->table('cash_sessions')->where('id', $session)->sole();
        $counter = self::number($row['session_number_seq']);

        $this->assertSame(['cash_session', '/'], [$response->json('result.type'), $response->json('result.location')]);
        $this->assertSame(sprintf('20261107-%06d', $counter), $response->json('result.number'));
        $this->assertSame(
            [sprintf('20261107-%06d', $counter), '2026-11-07', $this->register, $rina, 'Rina', 0, 'OPEN', 0, null],
            [$row['session_number'], $row['opened_business_date'], $row['register_id'], $row['responsible_user_id'], $row['responsible_display_name'], $row['opening_cash'], $row['status'], $row['activity_version'], $row['close_presence']],
        );
        $this->assertSame(['2026-11-07T03:30:00.250000', '2026-11-07'], array_values((array) $this->watcher()->selectOne(
            "select to_char(opened_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US') as utc, ((opened_at at time zone 'Asia/Jakarta')::date)::text as jakarta from cash_sessions where id = ?",
            [$session],
        )));
        $this->assertSame(self::number($response->json('commandId')), $row['open_command_id']);

        // The audit event in the same transaction, naming its session (EXC-44).
        $audit = (array) $this->watcher()->table('audit_events')->where('action', 'CASH_SESSION_OPEN')->sole();
        $this->assertSame(['BUSINESS', 'USER', $rina, 'Rina', 'cash_session', $session, $session, $row['open_command_id']], [$audit['category'], $audit['actor_kind'], $audit['actor_user_id'], $audit['actor_display_name'], $audit['subject_type'], $audit['subject_id'], $audit['cash_session_id'], $audit['command_id']]);
        $this->assertSame(['number' => $row['session_number'], 'openingCash' => '0', 'register' => 'LACI-1'], self::dataOf($audit['change_data']));

        // Opening creates no Sale and no movement (BR-CASH-05).
        $this->assertSame($movements, $this->watcher()->table('stock_movements')->count());
        $this->assertSame('COMMITTED', $this->watcher()->table('command_request_receipts')->where('operation', OpenCashSession::OPERATION)->value('outcome'));

        // The same request again answers the original result (RACE-01 in one process).
        $repeat = $this->open($envelope);
        $repeat->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $session);
        $this->assertSame(1, $this->watcher()->table('cash_sessions')->count());

        // A status check rebuilds the same result with its number (RC-50; ADR-007 section 3).
        $resolved = $this->check($envelope)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.number', $row['session_number']);
        $this->assertJsonChannel($resolved, 'commands.resolve.number', 'cashier');
    }

    public function test_a_blank_or_malformed_amount_is_refused_and_a_valid_one_is_kept_exactly(): void
    {
        // BR-CASH-04; TR-VAL-06; COPY_DECK section 16.1: a blank amount has its own key; a number,
        // a decimal, a separator, a sign or a value above the limit is refused, never repaired.
        $owner = TokoContoh::openSession('Hendra', TokoContoh::register('LACI-O', 'Laci Owner'));
        $this->as('Rina');
        $before = $this->facts();

        foreach ([
            [['openingCash' => ''], 'ux.validation.money_blank'],
            [['openingCash' => null], 'ux.validation.money_blank'],
            [['openingCash' => '1.500'], 'ux.validation.money'],
            [['openingCash' => '15000.5'], 'ux.validation.money'],
            [['openingCash' => '1e3'], 'ux.validation.money'],
            [['openingCash' => '-5'], 'ux.validation.money_min'],
            [['openingCash' => '1000000000000'], 'ux.validation.money_max'],
            [['openingCash' => 15000], 'ux.validation.money'],
        ] as $index => [$payload, $key]) {
            $refused = $this->open($this->envelope([...['registerId' => $this->register, 'openingCash' => '0'], ...$payload], ['operation' => OpenCashSession::OPERATION, 'expected' => (object) []]))
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => []], ((array) ($fields['payload.openingCash'] ?? []))[0] ?? null, (string) $index);
        }

        // The amount is required, never defaulted; the responsible user, a number, a date or an
        // expected value are never taken (TR-VAL-03).
        foreach ([
            ['registerId' => $this->register],
            ['registerId' => $this->register, 'openingCash' => '0', 'responsibleUserId' => $this->people['Dewi']['id']],
            ['registerId' => $this->register, 'openingCash' => '0', 'sessionNumber' => '20261107-000001'],
            ['registerId' => $this->register, 'openingCash' => '0', 'businessDate' => '2026-11-07'],
            ['registerId' => (string) $this->register, 'openingCash' => '0'],
            ['registerId' => 0, 'openingCash' => '0'],
        ] as $payload) {
            $this->open($this->envelope($payload, ['operation' => OpenCashSession::OPERATION, 'expected' => (object) []]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->open($this->envelopeOf($this->register, '0', ['version' => 1]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->facts());

        // A valid amount is kept exactly, as whole rupiah; the refusals took no number (SCH-21,
        // TR-TX-10): it is the next after the Owner's.
        $kept = self::number($this->open($this->envelopeOf($this->register, '250000'))->assertStatus(201)->json('result.id'));
        $this->assertSame(250000, $this->watcher()->table('cash_sessions')->where('id', $kept)->value('opening_cash'));
        $this->assertSame($this->counterOf($owner) + 1, $this->counterOf($kept));
    }

    public function test_an_inactive_register_one_in_use_or_an_actor_with_a_session_is_refused(): void
    {
        // BR-CASH-05, BR-CASH-06: under the register's lock, the register active and without an OPEN
        // session (at its field), and the actor without one (at the form); a register that does not
        // exist is not found.
        $inactive = TokoContoh::register('LACI-X', 'Laci nonaktif', false);
        $second = TokoContoh::register('LACI-2', 'Laci 2');
        $dewi = TokoContoh::openSession('Dewi', $second);
        $this->as('Rina');
        $before = $this->facts();

        foreach ([$inactive, $second] as $register) {
            $this->open($this->envelopeOf($register, '0'))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')
                ->assertJsonPath('fields', ['payload.registerId' => [['key' => 'ux.validation.register_taken', 'params' => []]]]);
        }

        $this->open($this->envelopeOf(999999, '0'))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->facts());

        // The definite refusals took no number (SCH-21, TR-TX-10): the next open is the next after Dewi's.
        $rina = self::number($this->open($this->envelopeOf($this->register, '0'))->assertStatus(201)->json('result.id'));
        $this->assertSame($this->counterOf($dewi) + 1, $this->counterOf($rina));
        $third = TokoContoh::register('LACI-3', 'Laci 3');
        $after = $this->facts();
        $this->open($this->envelopeOf($third, '0'))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', []);
        $this->assertSame($after, $this->facts());

        // Neither did the refusal of an actor who holds a session.
        $this->as('Hendra');
        $hendra = self::number($this->open($this->envelopeOf($third, '0'))->assertStatus(201)->json('result.id'));
        $this->assertSame($this->counterOf($rina) + 1, $this->counterOf($hendra));
    }

    /** The counter of a session's number. */
    private function counterOf(int $session): int
    {
        return self::number($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number_seq'));
    }

    public function test_the_owner_the_cashier_and_the_combined_account_open_as_themselves_and_operations_never(): void
    {
        // CAP-21 for the Owner, a cashier and the combined account, each on their own session; no
        // recent authentication (INTERACTION_CONTRACTS section 1.5). Operations is refused at the gate,
        // with no receipt, and by the guard in the transaction (SEC-AZ-04).
        foreach (['Hendra' => 'LACI-H', 'Dewi' => 'LACI-D', 'Rina' => 'LACI-R'] as $person => $code) {
            $register = TokoContoh::register($code, 'Laci '.$person);
            $this->as($person);
            $response = $this->open($this->envelopeOf($register, '100000'))->assertStatus(201);
            $this->assertSame($this->people[$person]['id'], $this->watcher()->table('cash_sessions')->where('id', self::number($response->json('result.id')))->value('responsible_user_id'), $person);
        }

        $register = TokoContoh::register('LACI-A', 'Laci Agus');
        $this->as('Agus');
        $this->open($this->envelopeOf($register, '0'))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', OpenCashSession::OPERATION)->where('actor_user_id', $this->people['Agus']['id'])->count());

        try {
            app(OpenCashSession::class)->execute(new CommandContext($this->people['Agus']['id'], $this->rawEnvelope($register, '0'), app(Clock::class)->now()));
            $this->fail('Operations passed the guard.');
        } catch (ActorNotAllowed) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->watcher()->table('cash_sessions')->where('register_id', $register)->count());

        $this->browse(null);
        $this->open($this->envelopeOf($register, '0'))->assertStatus(401);
    }

    public function test_a_failure_after_every_write_step_leaves_nothing_and_a_gap_in_the_numbers(): void
    {
        // TS-21: three steps — the session, the audit event, the result. SCH-26: a number taken by a
        // rolled-back open is never reused; the next open leaves the gap.
        $this->as('Rina');
        $first = TokoContoh::openSession('Hendra', TokoContoh::register('LACI-O', 'Laci Owner'));
        $counter = self::number($this->watcher()->table('cash_sessions')->where('id', $first)->value('session_number_seq'));

        for ($step = 1; $step <= 3; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->facts();

            $this->open($this->envelopeOf($this->register, '0'))->assertStatus(500);

            $this->assertSame($before, $this->facts(), sprintf('after write step %d', $step));
            $this->assertSame('PENDING', $this->watcher()->table('command_request_receipts')->where('operation', OpenCashSession::OPERATION)->orderByDesc('id')->value('outcome'), (string) $step);
        }

        $points = new FailurePoints(4);
        $this->app->instance(CommandPoints::class, $points);
        $response = $this->open($this->envelopeOf($this->register, '0'))->assertStatus(201);
        $this->assertSame(['after_lock:2.1', 'after_lock:3', 'after_write:1', 'after_write:2', 'after_write:3'], array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))));
        $this->app->instance(CommandPoints::class, new FailurePoints);

        // The three failed opens each took a number: the next is the fourth after the Owner's.
        $this->assertSame($counter + 4, $this->watcher()->table('cash_sessions')->where('id', self::number($response->json('result.id')))->value('session_number_seq'));
    }

    public function test_the_numbers_follow_allocation_order_and_carry_the_business_date_across_jakarta_midnight(): void
    {
        // SCH-22, SCH-23, SCH-25; G-18; AC-027-05: one counter for the installation that never restarts
        // at midnight; the date part is the Asia/Jakarta date of the opening, 23:59:59.9 on 7
        // November and 00:00 on 8 November (17:00 UTC).
        $second = TokoContoh::register('LACI-2', 'Laci 2');
        $third = TokoContoh::register('LACI-3', 'Laci 3');
        $rina = $this->people['Rina']['id'];
        $dewi = $this->people['Dewi']['id'];
        $hendra = $this->people['Hendra']['id'];

        $this->travelTo('2026-11-07T16:59:59.900000Z');
        $before = $this->openAs($rina, $this->register);
        $this->travelTo('2026-11-07T17:00:00.000000Z');
        $after = $this->openAs($dewi, $second);
        $this->travelTo('2026-11-08T03:00:00.000000Z');
        $later = $this->openAs($hendra, $third);

        $rows = $this->watcher()->table('cash_sessions')->whereIn('id', [$before, $after, $later])->orderBy('session_number_seq')->get(['id', 'session_number', 'session_number_seq', 'opened_business_date'])->map(static fn (object $row): array => (array) $row)->all();
        $first = self::number($rows[0]['session_number_seq']);

        $this->assertSame([$before, $after, $later], array_column($rows, 'id'));
        $this->assertSame([$first, $first + 1, $first + 2], array_column($rows, 'session_number_seq'));
        $this->assertSame(['2026-11-07', '2026-11-08', '2026-11-08'], array_column($rows, 'opened_business_date'));
        $this->assertSame([sprintf('20261107-%06d', $first), sprintf('20261108-%06d', $first + 1), sprintf('20261108-%06d', $first + 2)], array_column($rows, 'session_number'));
    }

    public function test_a_revision_of_the_business_profile_exists_from_the_bootstrap(): void
    {
        // G-23: the bootstrap wrote the first revision and its pointer, and the open reads it.
        $this->assertSame(1, $this->watcher()->table('business_profile_current')->count());
        $this->assertNotNull($this->watcher()->table('business_profile_revisions')->value('id'));
        $this->openAs($this->people['Rina']['id'], $this->register);
        $this->assertSame(1, $this->watcher()->table('cash_sessions')->count());
    }

    /**
     * An envelope of RC-02 for this register and amount.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function envelopeOf(int $register, string $openingCash, array $expected = [], array $changes = []): array
    {
        return $this->envelope(['registerId' => $register, 'openingCash' => $openingCash], ['operation' => OpenCashSession::OPERATION, 'expected' => (object) $expected, ...$changes]);
    }

    private function rawEnvelope(int $register, string $openingCash): Envelope
    {
        return new Envelope(OpenCashSession::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, [], ['registerId' => $register, 'openingCash' => $openingCash]);
    }

    /**
     * RC-02's Action for this actor, received now: the session's internal key.
     *
     * @throws CommandRefused
     */
    private function openAs(int $actor, int $register): int
    {
        $id = app(OpenCashSession::class)->execute(new CommandContext($actor, $this->rawEnvelope($register, '0'), app(Clock::class)->now()))->result['id'] ?? null;

        return is_int($id) ? $id : 0;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function open(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * How many rows each fact table holds.
     *
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }

    /**
     * Data of jsonb with its keys sorted, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);
        ksort($data);

        return $data;
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
