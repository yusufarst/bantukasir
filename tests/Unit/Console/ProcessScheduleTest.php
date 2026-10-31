<?php

namespace Tests\Unit\Console;

use App\Console\ProcessSchedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The scheduled commands per process role (AC-012-02; ENVIRONMENTS section 13;
 * ADR-010 item 6): role web registers SC-01, SC-02 and SC-04; role backup only
 * SC-03; another role none; in the scheduler's time zone and without overlap. An
 * entry whose command does not exist yet is skipped when due.
 */
final class ProcessScheduleTest extends TestCase
{
    /**
     * @return list<Event>
     */
    private function eventsOf(mixed $role): array
    {
        $schedule = new Schedule('Asia/Jakarta');

        ProcessSchedule::register($schedule, $role);

        return array_values($schedule->events());
    }

    /**
     * @return array<string, array{mixed, array<string, array{string, string}>}>
     */
    public static function roles(): array
    {
        return [
            'web' => ['web', [
                'SC-01' => ['reconciliation:run', '0 3 * * *'],
                'SC-02' => ['housekeeping:run', '0 * * * *'],
                'SC-04' => ['monitor:heartbeat', '*/5 * * * *'],
            ]],
            'backup' => ['backup', [
                'SC-03' => ['backup:run', '0 0,6,12,18 * * *'],
            ]],
            'none' => [null, []],
            'another' => ['worker', []],
        ];
    }

    /**
     * @param  array<string, array{string, string}>  $expected
     */
    #[DataProvider('roles')]
    public function test_each_role_registers_only_its_commands(mixed $role, array $expected): void
    {
        $registered = [];

        foreach ($this->eventsOf($role) as $event) {
            $this->assertSame('Asia/Jakarta', $event->timezone);
            $this->assertTrue($event->withoutOverlapping, (string) $event->description);
            $this->assertSame(60, $event->expiresAt);

            $registered[(string) $event->description] = [substr((string) $event->command, (int) strrpos((string) $event->command, ' ') + 1), $event->expression];
        }

        $this->assertSame($expected, $registered);
    }

    public function test_an_entry_runs_only_once_its_command_exists(): void
    {
        [$event] = $this->eventsOf('web');

        $this->assertStringEndsWith('reconciliation:run', (string) $event->command);
        $this->assertArrayNotHasKey('reconciliation:run', Artisan::all());
        $this->assertFalse($event->filtersPass($this->app));

        Artisan::registerCommand(new ClosureCommand('reconciliation:run', function (): int {
            return 0;
        }));

        $this->assertTrue($event->filtersPass($this->app));
    }

    public function test_sc_03_exists_and_keeps_its_lines_in_the_log_that_rb_09_reads(): void
    {
        [$event] = $this->eventsOf('backup');

        $this->assertArrayHasKey('backup:run', Artisan::all());
        $this->assertTrue($event->filtersPass($this->app));
        $this->assertSame(storage_path(ProcessSchedule::BACKUP_LOG), $event->output);
        $this->assertTrue($event->shouldAppendOutput);
    }

    public function test_the_application_registers_the_schedule_of_its_configured_role(): void
    {
        config(['app.process_role' => 'backup']);

        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('backup:run', $output);
        $this->assertStringNotContainsString('reconciliation:run', $output);
    }
}
