<?php

namespace Tests\Concurrency;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Tests\Harness\Access\UserCommandScenario;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\Worker;

/**
 * The races of RC-44, RC-45 and RC-46 (TASK-031; EXECUTION_CONTEXT section 10, a command; TECHNICAL_RULES
 * section 8, RACE-01 and RACE-02; DOCUMENT_CONTRACTS DC-01; BR-DOC-04; suites SESSION08 and CMD01), each
 * with processes of their own connections under barrier control:
 *
 * - two first print requests of one Sale at the same moment, by its cashier and by the Owner: the second
 *   waits for the lock of the Sale's prints, which the first holds to its commit, then sees the first,
 *   so that exactly one is the original and the other carries the copy marker;
 * - RACE-01 and RACE-02 for each of the three: the same key, or another key of the same intent, runs
 *   once and answers the same print request; another payload under the same key is refused.
 */
final class DocumentRaceTest extends DatabaseTestCase
{
    /** @var list<Worker> */
    private array $workers = [];

    private string $epoch = '';

    /** @var array<string, int> the account of each person */
    private array $people = [];

    private int $sale = 0;

    private int $session = 0;

    private ?Connection $watcher = null;

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Hendra', 'Rina'] as $person) {
            $this->people[$person] = TokoContoh::person($person)['id'];
        }

        $this->epoch = TokoContoh::recoveryEpoch();
        $product = TokoContoh::product('PEN-01', ['sellingPrice' => '5000']);
        TokoContoh::stock($product, '10');
        $this->session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-1', 'Laci 1'));
        $this->sale = TokoContoh::sell('Rina', $this->session, [['productId' => $product, 'quantity' => '1']]);
        TokoContoh::closeSession($this->session);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }

        parent::tearDown();
    }

    public function test_two_first_print_requests_of_one_sale_give_exactly_one_original(): void
    {
        $first = $this->start($this->command('Rina', 'document.print_receipt', ['saleId' => $this->sale, 'size' => '58']), ['after_write:1']);
        $second = $this->start($this->command('Hendra', 'document.print_receipt', ['saleId' => $this->sale, 'size' => 'A4']));
        Worker::ready($first, $second);

        $first->run()->awaitBarrier('after_write:1');
        $second->run()->awaitBlocked($this->watcher());
        $first->release();

        $original = $first->finish()->result ?? [];
        $copy = $second->finish()->result ?? [];
        $this->assertSame(['COMMITTED', 'COMMITTED'], [$original['outcome'] ?? null, $copy['outcome'] ?? null]);

        $events = $this->watcher()->table('audit_events')->where('action', 'PRINT')->where('subject_type', 'sale')->where('subject_id', $this->sale)->orderBy('id')->get(['command_id', 'change_data'])->all();
        $this->assertCount(2, $events);
        $flags = [];

        foreach ($events as $event) {
            $values = (array) $event;
            $data = json_decode(is_string($values['change_data'] ?? null) ? $values['change_data'] : '', true);
            $this->assertIsArray($data);
            $flags[] = [$values['command_id'] ?? null, $data['copy'] ?? null];
        }

        // The first to commit is the original; the one that waited for it is the copy.
        $this->assertSame([[$original['commandId'] ?? null, false], [$copy['commandId'] ?? null, true]], $flags);
    }

    public function test_race_01_and_race_02_each_print_and_export_runs_once_for_the_same_key_or_the_same_intent(): void
    {
        $commands = [
            'document.print_receipt' => [['saleId' => $this->sale, 'size' => '80'], ['saleId' => $this->sale, 'size' => '58']],
            'document.print_session_report' => [['sessionId' => $this->session], ['sessionId' => $this->session + 1000]],
            'document.export_session_report' => [['sessionId' => $this->session], ['sessionId' => $this->session + 1000]],
        ];

        foreach ($commands as $operation => [$payload, $otherPayload]) {
            foreach (['same key', 'another key', 'another payload'] as $case) {
                $intent = (string) Str::uuid();
                $key = (string) Str::uuid();
                $first = $this->start($this->command('Rina', $operation, $payload, $intent, $key), ['after_identity']);
                $second = $this->start($this->command('Rina', $operation, $case === 'another payload' ? $otherPayload : $payload, $intent, $case === 'another key' ? (string) Str::uuid() : $key));
                Worker::ready($first, $second);

                $first->run()->awaitBarrier('after_identity');
                $second->run()->awaitBlocked($this->watcher());
                $first->release();

                $original = $first->finish()->result ?? [];
                $this->assertSame(['COMMITTED', false], [$original['outcome'] ?? null, $original['repeated'] ?? null], $operation.' '.$case);
                $this->assertSame(
                    $case === 'another payload' ? ['refused' => 'IDEMPOTENCY_CONFLICT'] : ['outcome' => 'COMMITTED', 'repeated' => true, 'commandId' => $original['commandId'] ?? null],
                    $second->finish()->result,
                    $operation.' '.$case,
                );
                $this->assertSame(1, $this->watcher()->table('audit_events')->where('command_id', $original['commandId'] ?? 0)->count(), $operation.' '.$case);
                $this->assertSame(1, $this->watcher()->table('command_identities')->where('source_intent_id', $intent)->count(), $operation.' '.$case);
            }
        }
    }

    /**
     * A print or export request of a person, with a new intent and key unless given.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function command(string $person, string $operation, array $payload, ?string $intent = null, ?string $key = null): array
    {
        return [
            'operation' => $operation,
            'actor' => $this->people[$person],
            'intent' => $intent ?? (string) Str::uuid(),
            'key' => $key ?? (string) Str::uuid(),
            'epoch' => $this->epoch,
            'expected' => [],
            'payload' => $payload,
        ];
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
