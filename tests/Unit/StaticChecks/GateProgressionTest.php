<?php

namespace Tests\Unit\StaticChecks;

use PHPUnit\Framework\TestCase;

/**
 * The gate progression of TASK-044 to TASK-050 (D-142; TASK_PLAN section 3;
 * EXECUTION_CONTEXT CE-10): scripts/checks/gate-progression.sh proves, with
 * the tick's own selection functions of scripts/executor/lib.sh, that every
 * gate of the release path has its row, that no Task waits at its start for a
 * gate that it or a later Task completes, and that the selection starts each
 * Task in turn as the external gates are satisfied, and that no authorization
 * names an image digest that does not exist yet. The real plan passes; a copy
 * written in a temporary folder outside the tree, with a cycle put back, a
 * gate's row removed or the release image's own authorization removed, is
 * refused.
 */
final class GateProgressionTest extends TestCase
{
    use RunsChecks;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = self::makeTemporaryDirectory('gate-progression');
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->folder);

        parent::tearDown();
    }

    public function test_the_real_plan_progresses_from_task_044_to_task_050_without_a_cycle(): void
    {
        $process = self::runCheck(['bash', 'scripts/checks/gate-progression.sh']);

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertMatchesRegularExpression('/^Gate progression: [1-9]\d* passed, 0 failed$/m', $process->getOutput());
        $this->assertStringContainsString('PASS 3 TASK-050 starts with G4 and RELEASE still open', $process->getOutput());
    }

    public function test_a_task_that_waits_at_its_start_for_a_gate_it_completes_is_refused(): void
    {
        $plan = $this->copyOfThePlan(fn (string $text): string => str_replace(
            '| TASK-044 | OPERATOR, TARGET-STAGING, DEVICES-AVAILABLE; before `DONE`: DEVICES |',
            '| TASK-044 | OPERATOR, TARGET-STAGING, DEVICES-AVAILABLE, DEVICES |',
            $text,
        ));

        $process = self::runCheck(['bash', 'scripts/checks/gate-progression.sh', $plan]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('FAIL 2 TASK-046 does not wait at its start for DEVICES: completed by TASK-046', $process->getOutput());
    }

    public function test_a_gate_without_its_row_in_section_3_is_refused(): void
    {
        $plan = $this->copyOfThePlan(fn (string $text): string => (string) preg_replace('/^\| G4-STORE \|.*\n/m', '', $text));

        $process = self::runCheck(['bash', 'scripts/checks/gate-progression.sh', $plan]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('FAIL 1 TASK-045: gate G4-STORE has a row in section 3: no row', $process->getOutput());
    }

    public function test_a_production_authorization_without_the_release_images_own_digest_is_refused(): void
    {
        $plan = $this->copyOfThePlan(fn (string $text): string => str_replace(
            'RELEASE-AUTH, GATE-PUBLISH; before `DONE`: PRODUCTION-DEPLOY, G4, RELEASE |',
            'RELEASE-AUTH, GATE-PUBLISH; before `DONE`: G4, RELEASE |',
            $text,
        ));

        $process = self::runCheck(['bash', 'scripts/checks/gate-progression.sh', $plan]);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('FAIL 5 PRODUCTION-DEPLOY is completed inside TASK-050, never at its start', $process->getOutput());
    }

    /**
     * @param  callable(string): string  $change
     */
    private function copyOfThePlan(callable $change): string
    {
        $text = (string) file_get_contents(self::projectRoot().'/docs/11-tasks/TASK_PLAN.md');
        $changed = $change($text);
        $this->assertNotSame($text, $changed, 'The sample did not change the plan.');
        $path = $this->folder.'/TASK_PLAN.md';
        file_put_contents($path, $changed);

        return $path;
    }
}
