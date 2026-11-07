<?php

namespace Tests\Feature\Cash;

use App\Actions\Cash\CreateCashRegister;
use App\Actions\Cash\UpdateCashRegister;
use App\Database\CommandPoints;
use Illuminate\Database\Connection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Race\FailurePoints;

/**
 * RC-33 `cash_register.create` and RC-34 `cash_register.update` through their routes (AC-027-04;
 * INTERACTION_CONTRACTS RC-33, RC-34 and section 1.5; TECHNICAL_RULES section 7.2; SCHEMA section
 * 5.10; SCH-14; BR-CASH-02; G-19; CAP-31; EXECUTION_CONTEXT section 10, a command; suites CONFIG01,
 * CMD01 and AUTH03; TASK-027 section 7, L4): the Owner creates and changes a register with recent
 * authentication and audit; a code held by another register in any letter case is refused at its
 * field; a change is refused while a session is OPEN on the register, at the version reviewed, and
 * when it changes nothing; the fields and the allowlist; the roles; the failure after every write
 * step.
 */
final class CashRegisterCommandsTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    /** The tables that a refused or failed command leaves as they were. */
    private const array FACTS = ['cash_registers', 'command_identities', 'command_results', 'audit_events'];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
    }

    public function test_the_owner_creates_and_changes_a_register_with_its_audit(): void
    {
        // BR-CASH-02: code, label and active, at version 1, then one version on with each change.
        $this->owner();
        $hendra = $this->people['Hendra']['id'];

        $created = $this->create(['code' => 'LACI-1', 'label' => 'Laci depan', 'active' => true])->assertStatus(201);
        $this->assertJsonChannel($created, 'commands.committed', 'owner');
        $register = self::number($created->json('result.id'));
        $this->assertSame(['cash_register', '/settings/cash-registers'], [$created->json('result.type'), $created->json('result.location')]);
        $this->assertSame(['LACI-1', 'Laci depan', true, 1], $this->registerOf($register));

        $changed = $this->update(['registerId' => $register, 'code' => 'LACI-A', 'label' => 'Laci depan kiri', 'active' => false], 1)->assertStatus(201);
        $this->assertSame(['cash_register', $register, '/settings/cash-registers'], [$changed->json('result.type'), $changed->json('result.id'), $changed->json('result.location')]);
        $this->assertSame(['LACI-A', 'Laci depan kiri', false, 2], $this->registerOf($register));

        $events = $this->watcher()->table('audit_events')->whereIn('action', ['REGISTER_CREATE', 'REGISTER_CHANGE'])->orderBy('id')->get(['action', 'actor_user_id', 'subject_type', 'subject_id', 'change_data', 'command_id'])->map(static fn (object $row): array => (array) $row)->all();
        $this->assertSame([['REGISTER_CREATE', $hendra, 'cash_register', $register], ['REGISTER_CHANGE', $hendra, 'cash_register', $register]], array_map(static fn (array $event): array => [$event['action'], $event['actor_user_id'], $event['subject_type'], $event['subject_id']], $events));
        $this->assertSame(['active' => true, 'code' => 'LACI-1', 'label' => 'Laci depan'], self::dataOf($events[0]['change_data']));
        $this->assertSame(['from' => ['active' => true, 'code' => 'LACI-1', 'label' => 'Laci depan'], 'to' => ['active' => false, 'code' => 'LACI-A', 'label' => 'Laci depan kiri']], self::dataOf($events[1]['change_data']));
        $this->assertSame([self::number($created->json('commandId')), self::number($changed->json('commandId'))], array_column($events, 'command_id'));
    }

    public function test_a_code_held_by_another_register_in_any_letter_case_is_refused_at_its_field(): void
    {
        // SCH-14: the unique index alone decides, also for a change; the label is not unique.
        $this->owner();
        $first = TokoContoh::register('LACI-1', 'Laci');
        $second = TokoContoh::register('LACI-2', 'Laci');
        $before = $this->facts();

        foreach (['laci-1', 'LACI-1', 'Laci-1'] as $code) {
            $this->create(['code' => $code, 'label' => 'Laci baru', 'active' => true])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')
                ->assertJsonPath('fields', ['payload.code' => [['key' => 'ux.validation.register_code_taken', 'params' => []]]]);
        }

        $this->update(['registerId' => $second, 'code' => 'laci-1', 'label' => 'Laci', 'active' => true], 1)->assertStatus(422)
            ->assertJsonPath('fields', ['payload.code' => [['key' => 'ux.validation.register_code_taken', 'params' => []]]]);
        $this->assertSame($before, $this->facts());

        // A register keeps its own code in another letter case.
        $this->update(['registerId' => $first, 'code' => 'laci-1', 'label' => 'Laci', 'active' => true], 1)->assertStatus(201);
    }

    public function test_a_change_is_refused_while_a_session_is_open_on_the_register_at_the_version_reviewed(): void
    {
        // G-19, RACE-26's guard in one process; RC-34's expected version (STALE_VERSION); a change
        // that changes nothing (TASK-011).
        $register = TokoContoh::register('LACI-1', 'Laci 1');
        $this->owner();
        $before = $this->facts();

        $this->update(['registerId' => $register, 'code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], 1)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonPath('fields', []);
        $this->update(['registerId' => $register, 'code' => 'LACI-1', 'label' => 'Laci lain', 'active' => true], 2)->assertStatus(409)->assertJsonPath('code', 'STALE_VERSION');
        $this->update(['registerId' => 999999, 'code' => 'LACI-1', 'label' => 'Laci lain', 'active' => true], 1)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->assertSame($before, $this->facts());

        TokoContoh::openSession('Rina', $register);
        $before = $this->facts();
        $this->update(['registerId' => $register, 'code' => 'LACI-1', 'label' => 'Laci lain', 'active' => false], 1)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('fields', ['payload.registerId' => [['key' => 'ux.validation.register_in_use', 'params' => []]]]);
        $this->assertSame($before, $this->facts());
        $this->assertSame(['LACI-1', 'Laci 1', true, 1], $this->registerOf($register));
    }

    public function test_the_fields_are_validated_with_their_keys(): void
    {
        // TR-VAL-01 to TR-VAL-07; TECHNICAL_RULES section 11.3; SCHEMA section 5.10: code 1 to 32,
        // label 1 to 100, without a control character; active a JSON boolean.
        $this->owner();
        $base = ['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true];
        $before = $this->facts();

        foreach ([
            [['code' => ''], 'payload.code', 'ux.validation.required', []],
            [['code' => str_repeat('K', 33)], 'payload.code', 'ux.validation.text_max', ['maks' => 32]],
            [['label' => null], 'payload.label', 'ux.validation.required', []],
            [['label' => str_repeat('L', 101)], 'payload.label', 'ux.validation.text_max', ['maks' => 100]],
            [['active' => 'yes'], 'payload.active', 'ux.validation.choose_one', []],
        ] as $index => [$payload, $path, $key, $params]) {
            $refused = $this->create([...$base, ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
            $fields = (array) $refused->json('fields');
            $this->assertSame(['key' => $key, 'params' => $params], ((array) ($fields[$path] ?? []))[0] ?? null, sprintf('case %d at %s', $index, $path));
        }

        foreach ([['active' => 'true'], ['active' => 1], ['code' => "LACI\t1"], ['label' => "Laci\n1"], ['version' => 1], ['registerId' => 5]] as $payload) {
            $this->create([...$base, ...$payload])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        $this->command(UpdateCashRegister::OPERATION, ['code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->command(UpdateCashRegister::OPERATION, ['registerId' => '5', 'code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], ['registerVersion' => 1])->assertStatus(422);
        $this->command(UpdateCashRegister::OPERATION, ['registerId' => 5, 'code' => 'LACI-1', 'label' => 'Laci 1', 'active' => true], ['registerVersion' => '1'])->assertStatus(422);
        $this->assertSame($before, $this->facts());

        // Outer whitespace is trimmed; the code is kept as typed otherwise.
        $created = $this->create(['code' => '  Laci-B  ', 'label' => ' Laci belakang ', 'active' => true])->assertStatus(201);
        $this->assertSame(['Laci-B', 'Laci belakang', true, 1], $this->registerOf(self::number($created->json('result.id'))));
    }

    public function test_only_the_owner_with_recent_authentication_configures_registers(): void
    {
        // CAP-31; THR-19: staff and the combined account are refused at the gate, with no receipt;
        // AUTH03: without a recent password confirmation the Owner's command answers 423 and runs
        // nothing.
        $register = TokoContoh::register('LACI-1', 'Laci 1');
        $before = $this->facts();

        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $this->postJson('/user/confirm-password', ['password' => $this->people[$person]['password']])->assertStatus(201);
            $this->create(['code' => 'LACI-9', 'label' => 'Laci 9', 'active' => true])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->update(['registerId' => $register, 'code' => 'LACI-9', 'label' => 'Laci 9', 'active' => true], 1)->assertStatus(403);
            $this->assertSame(0, $this->watcher()->table('command_request_receipts')->where('actor_user_id', $this->people[$person]['id'])->whereIn('operation', [CreateCashRegister::OPERATION, UpdateCashRegister::OPERATION])->count(), $person);
        }

        $this->as('Hendra');
        $this->create(['code' => 'LACI-9', 'label' => 'Laci 9', 'active' => true])->assertStatus(423)->assertJsonPath('code', 'RECENT_AUTH_REQUIRED');
        $this->update(['registerId' => $register, 'code' => 'LACI-9', 'label' => 'Laci 9', 'active' => true], 1)->assertStatus(423);
        $this->assertSame(0, $this->watcher()->table('command_request_receipts')->whereIn('operation', [CreateCashRegister::OPERATION, UpdateCashRegister::OPERATION])->count());
        $this->assertSame($before, $this->facts());
    }

    public function test_a_failure_after_every_write_step_leaves_nothing(): void
    {
        // TS-21: three steps each — the register, the audit event, the result.
        $register = TokoContoh::register('LACI-1', 'Laci 1');
        $this->owner();

        foreach ([
            'create' => fn (): TestResponse => $this->create(['code' => 'LACI-2', 'label' => 'Laci 2', 'active' => true]),
            'update' => fn (): TestResponse => $this->update(['registerId' => $register, 'code' => 'LACI-1', 'label' => 'Laci baru', 'active' => true], 1),
        ] as $command => $send) {
            for ($step = 1; $step <= 3; $step++) {
                $this->app->instance(CommandPoints::class, new FailurePoints($step));
                $before = [$this->facts(), $this->registerOf($register)];

                $send()->assertStatus(500);

                $this->assertSame($before, [$this->facts(), $this->registerOf($register)], sprintf('%s after write step %d', $command, $step));
            }

            $points = new FailurePoints(4);
            $this->app->instance(CommandPoints::class, $points);
            $send()->assertStatus(201);
            $expected = $command === 'update' ? ['after_lock:2.1', 'after_lock:3', 'after_write:1', 'after_write:2', 'after_write:3'] : ['after_lock:2.1', 'after_write:1', 'after_write:2', 'after_write:3'];
            $this->assertSame($expected, array_values(array_filter($points->passed, static fn (string $point): bool => str_starts_with($point, 'after_lock:') || str_starts_with($point, 'after_write:'))), $command);
            $this->app->instance(CommandPoints::class, new FailurePoints);
        }
    }

    /** The Owner, with the recent authentication of RC-33 and RC-34 (INTERACTION_CONTRACTS section 1.5). */
    private function owner(): void
    {
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function create(array $payload): TestResponse
    {
        return $this->command(CreateCashRegister::OPERATION, $payload, []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function update(array $payload, int $version): TestResponse
    {
        return $this->command(UpdateCashRegister::OPERATION, $payload, ['registerVersion' => $version]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     * @return TestResponse<Response>
     */
    private function command(string $operation, array $payload, array $expected): TestResponse
    {
        return $this->postJson('/commands/'.$operation, $this->envelope($payload, ['operation' => $operation, 'expected' => (object) $expected]));
    }

    /**
     * A register's code, label, active flag and version.
     *
     * @return list<mixed>
     */
    private function registerOf(int $id): array
    {
        return array_values((array) $this->watcher()->table('cash_registers')->where('id', $id)->first(['code', 'label', 'active', 'version']));
    }

    /**
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
     * Data of jsonb with its keys sorted, also inside, since jsonb keeps no order of its own.
     *
     * @return array<array-key, mixed>
     */
    private static function dataOf(mixed $json): array
    {
        $data = json_decode(is_string($json) ? $json : '', true);
        self::assertIsArray($data);

        return self::sortedKeys($data);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortedKeys(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortedKeys($value);
            }
        }

        return $data;
    }

    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }
}
