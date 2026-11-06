<?php

namespace Tests\Unit\StaticChecks;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The two parts of the PostgreSQL suite (D-120; TEST_STRATEGY sections 6 and 7):
 * scripts/checks/postgres-parts.sh proves, with PHPUnit's own list of the tests,
 * that the suites Postgres1 and Postgres2 together hold every test of Postgres
 * once. The real configuration passes; small suites written in a temporary
 * folder outside the tree, whose parts overlap, leave a test out, reach beyond
 * the suite or are empty, are refused. The configuration, the Composer scripts
 * and the ci workflow keep the parts as D-120 sets them: Postgres2 names
 * directories of tests/Feature that exist, and Postgres1 is tests/Feature
 * without exactly those; a run that names no suite takes the suites of before
 * the parts; composer test:postgres stays the whole suite; each part has its own
 * job without a condition, and Command contract passes only after both.
 */
final class PostgresPartsTest extends TestCase
{
    use RunsChecks;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = self::makeTemporaryDirectory('postgres-parts');
        $this->writeSampleTests();
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->folder);

        parent::tearDown();
    }

    public function test_the_real_parts_hold_every_test_of_the_suite_once(): void
    {
        $process = self::runCheck(['bash', 'scripts/checks/postgres-parts.sh']);
        $output = $process->getOutput();

        $this->assertSame(0, $process->getExitCode(), $output);

        if (preg_match('/^POSTGRES_PARTS_COMPLETE Postgres1 (\d+) \+ Postgres2 (\d+) = Postgres (\d+) tests, each in one part$/m', $output, $counts) !== 1) {
            $this->fail('The check gave no count of the parts: '.$output);
        }

        $this->assertGreaterThan(0, (int) $counts[1]);
        $this->assertGreaterThan(0, (int) $counts[2]);
        $this->assertSame((int) $counts[3], (int) $counts[1] + (int) $counts[2]);
    }

    public function test_parts_that_split_the_suite_pass(): void
    {
        $process = $this->check($this->configuration(['.'], ['.'], ['C'], ['C']));

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_COMPLETE Postgres1 2 + Postgres2 2 = Postgres 4 tests, each in one part', $process->getOutput());
    }

    /**
     * The suite's directories, part 1's directories and exclusions, part 2's
     * directories, and the finding. The samples hold one test in A, one in B,
     * two (one test with two data sets) in C, and none in D.
     *
     * @return array<string, array{list<string>, list<string>, list<string>, list<string>, string}>
     */
    public static function brokenParts(): array
    {
        return [
            'a test in both parts' => [['.'], ['.'], [], ['C'], 'POSTGRES_PARTS_OVERLAP 2 test(s) in both parts'],
            'a test in neither part' => [['.'], ['A'], [], ['C'], 'POSTGRES_PARTS_GAP 1 test(s) of Postgres in neither part'],
            'a part beyond the suite' => [['A', 'B'], ['A', 'B'], [], ['C'], 'POSTGRES_PARTS_OUTSIDE 2 test(s) in a part but not in Postgres'],
            'an empty part' => [['.'], ['.'], [], ['D'], 'POSTGRES_PARTS_EMPTY Postgres2 holds no test'],
        ];
    }

    /**
     * @param  list<string>  $suite
     * @param  list<string>  $one
     * @param  list<string>  $oneExcludes
     * @param  list<string>  $two
     */
    #[DataProvider('brokenParts')]
    public function test_parts_that_do_not_split_the_suite_are_refused(array $suite, array $one, array $oneExcludes, array $two, string $expected): void
    {
        $process = $this->check($this->configuration($suite, $one, $oneExcludes, $two));

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString($expected, $process->getOutput());
    }

    public function test_a_configuration_that_cannot_be_listed_is_never_a_pass(): void
    {
        $process = $this->check($this->folder.'/absent.xml');

        $this->assertSame(2, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_UNREADABLE the tests of Postgres cannot be listed', $process->getOutput());
    }

    public function test_part_two_names_directories_of_the_suite_and_part_one_excludes_exactly_those(): void
    {
        $document = new DOMDocument;
        $this->assertTrue($document->load(self::projectRoot().'/phpunit.xml'));
        $xpath = new DOMXPath($document);

        // A run that names no suite takes the suites of before the parts, so
        // that no file is offered to two suites of one run.
        $root = $document->documentElement;
        $this->assertNotNull($root);
        $this->assertSame('Unit,Postgres,Concurrency,Network', $root->getAttribute('defaultTestSuite'));

        $this->assertSame(['tests/Feature'], self::paths($xpath, 'Postgres', 'directory'));
        $this->assertSame([], self::paths($xpath, 'Postgres', 'exclude'));
        $this->assertSame(['tests/Feature'], self::paths($xpath, 'Postgres1', 'directory'));

        $two = self::paths($xpath, 'Postgres2', 'directory');
        $this->assertNotSame([], $two);
        $this->assertSame($two, self::paths($xpath, 'Postgres1', 'exclude'));
        $this->assertSame([], self::paths($xpath, 'Postgres2', 'exclude'));

        foreach ($two as $directory) {
            $this->assertStringStartsWith('tests/Feature/', $directory);
            $this->assertDirectoryExists(self::projectRoot().'/'.$directory);
        }
    }

    public function test_composer_keeps_the_whole_suite_and_runs_each_part(): void
    {
        $composer = json_decode((string) file_get_contents(self::projectRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($composer);
        $scripts = $composer['scripts'] ?? null;
        $this->assertIsArray($scripts);

        $this->assertSame('phpunit --testsuite=Postgres', $scripts['test:postgres'] ?? null);
        $this->assertSame('phpunit --testsuite=Postgres1', $scripts['test:postgres:1'] ?? null);
        $this->assertSame('phpunit --testsuite=Postgres2', $scripts['test:postgres:2'] ?? null);

        $lint = $scripts['lint'] ?? null;
        $this->assertIsArray($lint);
        $this->assertContains('bash scripts/checks/postgres-parts.sh', $lint);
    }

    public function test_each_part_has_its_own_job_and_command_contract_passes_only_after_both(): void
    {
        $workflow = (string) file_get_contents(self::projectRoot().'/.github/workflows/ci.yml');
        $jobs = self::jobs($workflow);

        $this->assertSame(['Command contract', 'PostgreSQL (1/2)', 'PostgreSQL (2/2)', 'Image checks'], array_keys($jobs));
        $this->assertStringContainsString('run: docker compose exec -T app composer test:postgres:1', $jobs['PostgreSQL (1/2)']);
        $this->assertStringContainsString('run: docker compose exec -T app composer test:postgres:2', $jobs['PostgreSQL (2/2)']);
        $this->assertDoesNotMatchRegularExpression('/run:.*composer test:postgres/', $jobs['Command contract']);

        // Command contract's last step waits for both parts of this run.
        preg_match_all('/^\s*run: (.*)$/m', $jobs['Command contract'], $runs);
        $this->assertSame('bash scripts/checks/required-jobs.sh "PostgreSQL (1/2)" "PostgreSQL (2/2)"', trim((string) end($runs[1])));
        $this->assertStringContainsString('actions: read', $jobs['Command contract']);

        // No job has a condition; only the stop of each local environment runs
        // always; no failure is let through.
        preg_match_all('/^\s*if:.*$/m', $workflow, $conditions);
        $this->assertSame(['if: always()', 'if: always()', 'if: always()'], array_map('trim', $conditions[0]));
        $this->assertStringNotContainsString('continue-on-error', $workflow);
    }

    private function check(string $configuration): Process
    {
        return self::runCheck(['bash', 'scripts/checks/postgres-parts.sh', $configuration]);
    }

    /**
     * A configuration in the samples' folder with the suites Postgres,
     * Postgres1 and Postgres2.
     *
     * @param  list<string>  $suite
     * @param  list<string>  $one
     * @param  list<string>  $oneExcludes
     * @param  list<string>  $two
     */
    private function configuration(array $suite, array $one, array $oneExcludes, array $two): string
    {
        $file = $this->folder.'/phpunit-'.bin2hex(random_bytes(4)).'.xml';

        file_put_contents($file, sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<phpunit><testsuites><testsuite name="Postgres">%s</testsuite><testsuite name="Postgres1">%s%s</testsuite><testsuite name="Postgres2">%s</testsuite></testsuites></phpunit>'."\n",
            self::elements('directory', $suite),
            self::elements('directory', $one),
            self::elements('exclude', $oneExcludes),
            self::elements('directory', $two),
        ));

        return $file;
    }

    /**
     * @param  list<string>  $paths
     */
    private static function elements(string $name, array $paths): string
    {
        $xml = '';

        foreach ($paths as $path) {
            $xml .= sprintf('<%1$s>%2$s</%1$s>', $name, $path);
        }

        return $xml;
    }

    /**
     * The paths that a suite of the configuration names in its elements of
     * one kind, directory or exclude. A query that cannot be evaluated, or a
     * node that is not such an element, fails the test.
     *
     * @return list<string>
     */
    private static function paths(DOMXPath $xpath, string $suite, string $element): array
    {
        $query = sprintf('/phpunit/testsuites/testsuite[@name="%s"]/%s', $suite, $element);
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            self::fail('phpunit.xml could not be read with the query '.$query.'.');
        }

        $paths = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                self::fail(sprintf('The suite %s of phpunit.xml holds a %s where an element %s belongs.', $suite, $node::class, $element));
            }

            $paths[] = trim($node->textContent);
        }

        return $paths;
    }

    /**
     * Each job of the workflow by its name, with the text of its block.
     *
     * @return array<string, string>
     */
    private static function jobs(string $workflow): array
    {
        $start = strpos($workflow, "\njobs:\n");
        $blocks = preg_split('/^(?=    [a-z0-9-]+:\s*$)/m', $start === false ? '' : substr($workflow, $start));
        $jobs = [];

        foreach ($blocks === false ? [] : $blocks as $block) {
            if (preg_match('/^        name: (.+)$/m', $block, $name) === 1) {
                $jobs[trim($name[1])] = $block;
            }
        }

        return $jobs;
    }

    /**
     * Four test files: one test in A, one in B, one test with two data sets
     * in C, and a folder D without a test.
     */
    private function writeSampleTests(): void
    {
        $samples = [
            'A/FirstTest.php' => <<<'PHP'
                <?php

                namespace PostgresPartsSample\A;

                use PHPUnit\Framework\TestCase;

                final class FirstTest extends TestCase
                {
                    public function test_one(): void
                    {
                        $this->assertTrue(true);
                    }
                }

                PHP,
            'B/SecondTest.php' => <<<'PHP'
                <?php

                namespace PostgresPartsSample\B;

                use PHPUnit\Framework\TestCase;

                final class SecondTest extends TestCase
                {
                    public function test_two(): void
                    {
                        $this->assertTrue(true);
                    }
                }

                PHP,
            'C/ThirdTest.php' => <<<'PHP'
                <?php

                namespace PostgresPartsSample\C;

                use PHPUnit\Framework\Attributes\DataProvider;
                use PHPUnit\Framework\TestCase;

                final class ThirdTest extends TestCase
                {
                    public static function numbers(): array
                    {
                        return ['one' => [1], 'two' => [2]];
                    }

                    #[DataProvider('numbers')]
                    public function test_a_number(int $number): void
                    {
                        $this->assertGreaterThan(0, $number);
                    }
                }

                PHP,
            'D/notes.txt' => "No test is here.\n",
        ];

        foreach ($samples as $path => $content) {
            $file = $this->folder.'/'.$path;

            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0700);
            }

            file_put_contents($file, $content);
        }
    }
}
