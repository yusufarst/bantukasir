<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The start-up check as a command (AC-012-03): it reads the process's environment,
 * ends with a failure on any problem and names the setting, never its value. The
 * environment of the tests holds a credential that role backup never holds.
 */
final class CheckSettingsTest extends TestCase
{
    private string|false $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = getenv('APP_PROCESS_ROLE');
    }

    protected function tearDown(): void
    {
        putenv($this->role === false ? 'APP_PROCESS_ROLE' : 'APP_PROCESS_ROLE='.$this->role);

        parent::tearDown();
    }

    public function test_it_refuses_without_a_role(): void
    {
        putenv('APP_PROCESS_ROLE');

        $this->assertSame(1, Artisan::call('settings:check'));
        $this->assertSame("SETTING_REFUSED APP_PROCESS_ROLE missing\n", Artisan::output());
    }

    public function test_it_names_each_problem_and_never_a_value(): void
    {
        putenv('APP_PROCESS_ROLE=backup');

        $this->assertSame(1, Artisan::call('settings:check'));

        $output = Artisan::output();
        $this->assertStringContainsString('SETTING_REFUSED BACKUP_STORE_KEY missing', $output);
        $this->assertStringContainsString('SETTING_REFUSED DB_USERNAME forbidden', $output);
        $this->assertStringNotContainsString('bk_runtime', $output);
    }

    public function test_it_describes_a_role_for_the_image_checks(): void
    {
        $this->assertSame(0, Artisan::call('settings:check', ['--describe' => true, '--role' => 'web']));

        $lines = explode("\n", trim(Artisan::output()));
        $this->assertContains('required APP_KEY', $lines);
        $this->assertContains('default DB_HOST 127.0.0.1', $lines);
        $this->assertContains('forbidden DB_MIGRATION_PASSWORD', $lines);

        $this->assertSame(1, Artisan::call('settings:check', ['--describe' => true, '--role' => 'worker']));
    }
}
