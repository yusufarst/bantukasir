<?php

namespace Tests\Feature\Cash;

use App\Actions\Cash\AddReviewNote;
use App\Actions\Commands\CommandContext;
use App\Database\CommandPoints;
use App\Domain\Access\ActorNotAllowed;
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
 * RC-43 `review_note.add` through its route and its Action (AC-032-04; INTERACTION_CONTRACTS RC-43;
 * TECHNICAL_RULES section 7.2, "Owner review note on a CLOSED session", TR-VAL-05; SCHEMA section 5.10;
 * BR-CASH-19; D-068, D-069 (f); CAP-22; WF-10; EXECUTION_CONTEXT section 10, a command; suites SESSION07
 * and CMD01; TASK-032 section 7, L4): the Owner's notes on a CLOSED session, several of them, each with
 * its author and time, changing nothing of the close or the report; a session still OPEN STALE_VERSION and
 * a missing one NOT_FOUND; the text's bounds and its line breaks; the failure after every write step;
 * repeats; the other roles refused.
 */
final class ReviewNoteTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private const string COMMAND = '/commands/review_note.add';

    /** The tables that a refused or failed note leaves as they were. */
    private const array FACTS = ['cash_session_review_notes', 'command_identities', 'command_results', 'audit_events'];

    private int $register = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->register = TokoContoh::register('LACI-1', 'Laci 1');
    }

    public function test_the_owner_adds_several_notes_to_a_closed_session_and_nothing_else_changes(): void
    {
        // AC-032-04; D-068, D-069 (f), BR-CASH-19: a positive variance explained by a note, then a second
        // note correcting the first; the close and the report as they were recorded.
        $session = TokoContoh::openSession('Rina', $this->register, '10000');
        TokoContoh::closeSession($session, '15000');
        $number = self::text($this->watcher()->table('cash_sessions')->where('id', $session)->value('session_number'));
        $frozen = $this->recorded($session);
        $hendra = $this->people['Hendra']['id'];

        $this->travelTo('2026-11-13T05:00:00.000000Z');
        $this->as('Hendra');
        $first = $this->envelopeOf($session, "Uang pengembalian masih di laci saat dihitung.\nSudah diserahkan ke pelanggan.");
        $response = $this->note($first)->assertStatus(201)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertJsonChannel($response, 'commands.committed', 'owner');
        $noteId = self::number($response->json('result.id'));
        $commandId = self::number($response->json('commandId'));
        $this->assertSame(['cash_session_review_note', '/cash-sessions/'.$session], [$response->json('result.type'), $response->json('result.location')]);

        $this->travelTo('2026-11-13T05:10:00.000000Z');
        $this->note($this->envelopeOf($session, 'Koreksi: selisih Rp5.000 dari pengembalian.'))->assertStatus(201);

        // Each note with its text, line breaks kept, its author's frozen name and its time, in order.
        $rows = [];

        foreach ($this->watcher()->select(<<<'SQL'
            select id, note, actor_user_id, actor_display_name, to_char(recorded_at at time zone 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US') as recorded_at, command_id
            from cash_session_review_notes where session_id = ? order by id
            SQL, [$session]) as $row) {
            $rows[] = array_values((array) $row);
        }

        $this->assertSame([$noteId, $commandId], [$rows[0][0] ?? null, $rows[0][5] ?? null]);
        $this->assertSame([
            ["Uang pengembalian masih di laci saat dihitung.\nSudah diserahkan ke pelanggan.", $hendra, 'Hendra', '2026-11-13T05:00:00.000000'],
            ['Koreksi: selisih Rp5.000 dari pengembalian.', $hendra, 'Hendra', '2026-11-13T05:10:00.000000'],
        ], array_map(static fn (array $row): array => array_slice($row, 1, 4), $rows));

        // The count, the variance and the report never change (BR-CASH-19).
        $this->assertSame($frozen, $this->recorded($session));

        // Its audit event, naming its session.
        $audit = (array) $this->watcher()->table('audit_events')->where('action', 'REVIEW_NOTE_ADD')->orderBy('id')->first();
        $this->assertSame(['BUSINESS', $hendra, 'cash_session_review_note', $noteId, $session, $commandId], [$audit['category'], $audit['actor_user_id'], $audit['subject_type'], $audit['subject_id'], $audit['cash_session_id'], $audit['command_id']]);
        $this->assertSame(['noteId' => $noteId, 'number' => $number], self::dataOf($audit['change_data']));

        // The same request answers the original note; a status check rebuilds it (RC-50).
        $this->note($first)->assertStatus(200)->assertJsonPath('repeated', true)->assertJsonPath('result.id', $noteId);
        $resolved = $this->check($first)->assertOk()->assertJsonPath('outcome', 'COMMITTED')->assertJsonPath('result.location', '/cash-sessions/'.$session);
        $this->assertJsonChannel($resolved, 'commands.resolve', 'owner');
        $this->assertSame(2, $this->watcher()->table('cash_session_review_notes')->count());
    }

    public function test_a_note_on_an_open_or_missing_session_is_refused_and_its_text_keeps_its_bounds(): void
    {
        // RC-43: the session CLOSED under its share lock, STALE_VERSION otherwise; TR-VAL-05: 1 to 2,000
        // characters, a line break the only control character; no author, time or other field.
        $open = TokoContoh::openSession('Rina', $this->register);
        $closed = TokoContoh::closeSession(TokoContoh::openSession('Dewi', TokoContoh::register('LACI-2', 'Laci 2')));
        $this->as('Hendra');
        $before = $this->facts();

        $stale = $this->note($this->envelopeOf($open, 'Catatan'))->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->assertJsonChannel($stale, 'commands.refused', 'owner');
        $this->note($this->envelopeOf(999999, 'Catatan'))->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

        foreach ([
            [['text' => ''], 'ux.validation.required'],
            [['text' => "  \n "], 'ux.validation.required'],
            [['text' => str_repeat('a', 2001)], 'ux.validation.text_max'],
        ] as $index => [$payload, $key]) {
            $refused = $this->note($this->envelope([...['sessionId' => $closed], ...$payload], ['operation' => AddReviewNote::OPERATION]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame($key, ((array) (((array) ($fields['payload.text'] ?? []))[0] ?? []))['key'] ?? null, (string) $index);
        }

        foreach ([
            ['sessionId' => $closed],
            ['sessionId' => $closed, 'text' => "Tab\tdi sini"],
            ['sessionId' => $closed, 'text' => 7],
            ['sessionId' => (string) $closed, 'text' => 'Catatan'],
            ['sessionId' => $closed, 'text' => 'Catatan', 'author' => 'Rina'],
            ['sessionId' => $closed, 'text' => 'Catatan', 'variance' => '0'],
        ] as $payload) {
            $this->note($this->envelope($payload, ['operation' => AddReviewNote::OPERATION]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->note($this->envelope(['sessionId' => $closed, 'text' => 'Catatan'], ['operation' => AddReviewNote::OPERATION, 'expected' => ['activityVersion' => 0]]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->facts());

        // 2,000 characters, counted as characters, and line breaks are a note.
        $this->note($this->envelopeOf($closed, str_repeat('é', 1998)."\nx"))->assertStatus(201);
        $this->assertSame(2000, mb_strlen(self::text($this->watcher()->table('cash_session_review_notes')->value('note'))));
    }

    public function test_a_failure_after_every_write_step_leaves_nothing(): void
    {
        // TS-21: three steps — the note, the audit event, the result.
        $session = TokoContoh::closeSession(TokoContoh::openSession('Rina', $this->register));
        $this->as('Hendra');

        for ($step = 1; $step <= 3; $step++) {
            $this->app->instance(CommandPoints::class, new FailurePoints($step));
            $before = $this->facts();
            $envelope = $this->envelopeOf($session, 'Catatan');

            $this->note($envelope)->assertStatus(500);

            $this->assertSame($before, $this->facts(), sprintf('after write step %d', $step));
            $this->assertSame(['PENDING'], $this->outcomesOf($envelope), (string) $step);
        }

        // The locks of TR §7.2: the actor, then the session under its share lock.
        $points = new FailurePoints(4);
        $this->app->instance(CommandPoints::class, $points);
        $this->note($this->envelopeOf($session, 'Catatan'))->assertStatus(201);
        $this->assertSame(
            ['after_lock:2.1', 'after_lock:4', 'after_write:1', 'after_write:2', 'after_write:3'],
            array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))),
        );
        $this->assertSame(1, $this->watcher()->table('cash_session_review_notes')->count());
    }

    public function test_only_the_owner_adds_a_note_and_every_other_role_is_refused(): void
    {
        // CAP-22 for review notes is the Owner's alone: staff and the combined account are refused at the
        // gate, with no receipt, and by the guard in the transaction (SEC-AZ-04); no recent authentication
        // (INTERACTION_CONTRACTS section 1.5).
        $session = TokoContoh::closeSession(TokoContoh::openSession('Rina', $this->register));

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->note($this->envelopeOf($session, 'Catatan'))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('operation', AddReviewNote::OPERATION)->where('actor_user_id', $this->people[$person]['id'])->count(), $person);

            try {
                app(AddReviewNote::class)->execute(new CommandContext($this->people[$person]['id'], new Envelope(AddReviewNote::OPERATION, Operations::VERSION, (string) Str::uuid(), (string) Str::uuid(), $this->epoch, [], ['sessionId' => $session, 'text' => 'Catatan']), app(Clock::class)->now()));
                $this->fail($person.' passed the guard.');
            } catch (ActorNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        $this->browse(null);
        $this->note($this->envelopeOf($session, 'Catatan'))->assertStatus(401);
        $this->assertSame(0, $this->watcher()->table('cash_session_review_notes')->count());
    }

    /**
     * An envelope of RC-43 for this session and text.
     *
     * @return array<string, mixed>
     */
    private function envelopeOf(int $session, string $text): array
    {
        return $this->envelope(['sessionId' => $session, 'text' => $text], ['operation' => AddReviewNote::OPERATION]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    private function note(array $envelope): TestResponse
    {
        return $this->postJson(self::COMMAND, $envelope);
    }

    /**
     * The session, its close and its report as they stand.
     *
     * @return list<array<string, mixed>>
     */
    private function recorded(int $session): array
    {
        return [
            (array) $this->watcher()->table('cash_sessions')->where('id', $session)->first(),
            (array) $this->watcher()->table('cash_session_closes')->where('session_id', $session)->first(),
            (array) $this->watcher()->table('cash_session_reports')->where('session_id', $session)->first(),
        ];
    }

    /**
     * How many rows each fact table holds.
     *
     * @return array<string, int>
     */
    private function facts(): array
    {
        $facts = [];

        foreach (self::FACTS as $table) {
            $facts[$table] = $this->watcher()->table($table)->count();
        }

        return $facts;
    }

    /**
     * Data of jsonb with its keys sorted.
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
