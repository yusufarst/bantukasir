<?php

namespace Tests\Harness\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;

/**
 * The helpers of the L4 tests of the command platform (TASK-008): the people
 * of Toko Contoh signed in in their own browsers, the probe's tables, an
 * envelope of the probe, and the requests of the probe, RC-50 and RD-04.
 * A test using it extends Tests\Harness\DatabaseTestCase.
 */
trait CommandRequests
{
    use Browsers;

    /** @var array<string, array{id: int, email: string, password: string}> */
    protected array $people = [];

    /** @var array<string, string> the session cookie of each person's browser */
    private array $browsers = [];

    protected string $epoch = '';

    protected function setUpCommands(): void
    {
        $this->withoutVite();
        $this->useDatabaseSessions();

        foreach (Probe::createTables($this->migration()) as $table) {
            $this->harnessTable($table);
        }

        foreach (['Hendra', 'Agus', 'Rina', 'Dewi'] as $person) {
            $this->people[$person] = TokoContoh::person($person);
        }

        $this->epoch = TokoContoh::recoveryEpoch();
    }

    /**
     * The next requests come from this person's browser, signed in once.
     */
    protected function as(string $person): void
    {
        Cache::store()->flush();

        if (! isset($this->browsers[$person])) {
            $this->browsers[$person] = $this->signInBrowser($this->people[$person]['email'], $this->people[$person]['password']);
        }

        $this->browse($this->browsers[$person]);
    }

    /**
     * An envelope of the probe; a new intent and key unless given.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $changes  fields that replace the envelope's
     * @return array<string, mixed>
     */
    protected function envelope(array $payload = ['note' => 'Catatan uji'], array $changes = []): array
    {
        return [
            'operation' => Probe::OPERATION,
            'version' => 1,
            'sourceIntentId' => (string) Str::uuid(),
            'idempotencyKey' => (string) Str::uuid(),
            'recoveryEpoch' => $this->epoch,
            'expected' => (object) [],
            'payload' => $payload,
            ...$changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @param  array<string, string>  $server
     * @return TestResponse<Response>
     */
    protected function probe(array $envelope, array $server = []): TestResponse
    {
        return $this->withServerVariables($server)->postJson(Probe::PATH, $envelope);
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return TestResponse<Response>
     */
    protected function check(array $envelope, ?string $epoch = null): TestResponse
    {
        return $this->withServerVariables([])->postJson('/commands/resolve', [
            'operation' => $envelope['operation'],
            'sourceIntentId' => $envelope['sourceIntentId'],
            'recoveryEpoch' => $epoch ?? $envelope['recoveryEpoch'],
        ]);
    }

    /**
     * The receipts of an intent as committed, in key order.
     *
     * @param  array<string, mixed>  $envelope
     * @return list<array<string, mixed>>
     */
    protected function receiptsOf(array $envelope): array
    {
        return array_values(array_map(
            static fn (object $row): array => (array) $row,
            $this->observer()->table('command_request_receipts')
                ->where('operation', $envelope['operation'])->where('source_intent_id', $envelope['sourceIntentId'])
                ->orderBy('id')
                ->get(['id', 'actor_user_id', 'outcome', 'outcome_at', 'held_code', 'settled_by_command_id', 'settled_by_user_id'])
                ->all(),
        ));
    }

    /**
     * How many rows each table of the command platform and the probe holds.
     *
     * @return array<string, int>
     */
    protected function counts(): array
    {
        $observer = $this->observer();
        $counts = [];

        foreach (['command_identities', 'command_results', 'command_request_receipts', 'stale_intent_reports', 'audit_events', 'harness_probe_facts'] as $table) {
            $counts[$table] = $observer->table($table)->count();
        }

        $counts['counter'] = self::number($observer->table('harness_probe_counters')->where('id', 1)->value('value'));

        return $counts;
    }

    protected static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    protected static function number(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    /**
     * The rows of an answer of RD-04.
     *
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    protected static function rowsOf(TestResponse $response): array
    {
        $rows = [];

        foreach ((array) $response->json('rows') as $row) {
            $values = [];

            foreach (is_array($row) ? $row : [] as $key => $value) {
                $values[(string) $key] = $value;
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * The receipts' outcomes by intent.
     *
     * @param  array<string, mixed>  $envelope
     * @return list<string>
     */
    protected function outcomesOf(array $envelope): array
    {
        return array_map(static fn (array $row): string => is_string($row['outcome']) ? $row['outcome'] : '', $this->receiptsOf($envelope));
    }
}
