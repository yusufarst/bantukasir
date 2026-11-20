<?php

namespace Tests\Unit\StaticChecks;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The parts of the PostgreSQL suite (D-120, D-131; TEST_STRATEGY sections 6
 * and 7): scripts/checks/postgres-parts.sh proves, with PHPUnit's own list of
 * the tests, that the suites Postgres1 to Postgres<n> together hold every test
 * of Postgres once. The real configuration passes; small suites written in a
 * temporary folder outside the tree, whose parts overlap, leave a test out,
 * reach beyond the suite, are empty or are not numbered from 1 without a gap,
 * are refused. The configuration, the Composer scripts and the ci workflow keep
 * the parts as D-120 and D-131 set them: each part after the first names
 * directories of tests/Feature that exist, and Postgres1 is tests/Feature
 * without exactly those; a run that names no suite takes the suites of before
 * the parts; composer test:postgres stays the whole suite; each part has its
 * own Composer script and its own job without a condition, and Command
 * contract passes only after every part.
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

        if (preg_match('/^POSTGRES_PARTS_COMPLETE (.+) = Postgres (\d+) tests, each in one part$/m', $output, $counts) !== 1) {
            $this->fail('The check gave no count of the parts: '.$output);
        }

        $parts = explode(' + ', $counts[1]);
        $this->assertCount(count(self::parts()), $parts);
        $sum = 0;

        foreach ($parts as $index => $part) {
            $this->assertMatchesRegularExpression('/^Postgres'.($index + 1).' [1-9]\d*$/', $part);
            $sum += (int) substr($part, strrpos($part, ' ') + 1);
        }

        $this->assertSame((int) $counts[2], $sum);
    }

    public function test_parts_that_split_the_suite_pass(): void
    {
        $process = $this->check($this->configuration(['.'], ['.'], ['C'], ['C']));

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_COMPLETE Postgres1 2 + Postgres2 2 = Postgres 4 tests, each in one part', $process->getOutput());
    }

    public function test_three_parts_that_split_the_suite_pass(): void
    {
        $process = $this->check($this->configuration(['.'], ['.'], ['B', 'C'], ['C'], [['B']]));

        $this->assertSame(0, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_COMPLETE Postgres1 1 + Postgres2 2 + Postgres3 1 = Postgres 4 tests, each in one part', $process->getOutput());
    }

    /**
     * The suite's directories, part 1's directories and exclusions, part 2's
     * directories, the finding, and the directories of any further parts. The
     * samples hold one test in A, one in B, two (one test with two data sets)
     * in C, and none in D.
     *
     * @return array<string, array{0: list<string>, 1: list<string>, 2: list<string>, 3: list<string>, 4: string, 5?: list<list<string>>}>
     */
    public static function brokenParts(): array
    {
        return [
            'a test in both parts' => [['.'], ['.'], [], ['C'], 'POSTGRES_PARTS_OVERLAP 2 test(s) in more than one part'],
            'a test in two of three parts' => [['.'], ['.'], ['B', 'C'], ['C'], 'POSTGRES_PARTS_OVERLAP 2 test(s) in more than one part', [['B', 'C']]],
            'a test in neither part' => [['.'], ['A'], [], ['C'], 'POSTGRES_PARTS_GAP 1 test(s) of Postgres in no part'],
            'a test in none of three parts' => [['.'], ['A'], [], ['C'], 'POSTGRES_PARTS_GAP 1 test(s) of Postgres in no part', [['D']]],
            'a part beyond the suite' => [['A', 'B'], ['A', 'B'], [], ['C'], 'POSTGRES_PARTS_OUTSIDE 2 test(s) in a part but not in Postgres'],
            'an empty part' => [['.'], ['.'], [], ['D'], 'POSTGRES_PARTS_EMPTY Postgres2 holds no test'],
            'an empty third part' => [['.'], ['.'], ['C'], ['C'], 'POSTGRES_PARTS_EMPTY Postgres3 holds no test', [['D']]],
        ];
    }

    /**
     * @param  list<string>  $suite
     * @param  list<string>  $one
     * @param  list<string>  $oneExcludes
     * @param  list<string>  $two
     * @param  list<list<string>>  $more
     */
    #[DataProvider('brokenParts')]
    public function test_parts_that_do_not_split_the_suite_are_refused(array $suite, array $one, array $oneExcludes, array $two, string $expected, array $more = []): void
    {
        $process = $this->check($this->configuration($suite, $one, $oneExcludes, $two, $more));

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString($expected, $process->getOutput());
    }

    /**
     * The parts' names as suites of a configuration, and the numbers the
     * finding shows.
     *
     * @return array<string, array{list<string>, string}>
     */
    public static function misnumberedParts(): array
    {
        return [
            'a single part' => [['Postgres1'], '1 '],
            'a gap in the numbers' => [['Postgres1', 'Postgres3'], '1 3 '],
            'no part 1' => [['Postgres2', 'Postgres3'], '2 3 '],
        ];
    }

    /**
     * @param  list<string>  $names
     */
    #[DataProvider('misnumberedParts')]
    public function test_parts_not_numbered_from_one_without_a_gap_are_refused(array $names, string $numbers): void
    {
        $suites = '';

        foreach ($names as $name) {
            $suites .= sprintf('<testsuite name="%s"><directory>.</directory></testsuite>', $name);
        }

        $file = $this->folder.'/phpunit-'.bin2hex(random_bytes(4)).'.xml';
        file_put_contents($file, '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<phpunit><testsuites><testsuite name="Postgres"><directory>.</directory></testsuite>'.$suites.'</testsuites></phpunit>'."\n");

        $process = $this->check($file);

        $this->assertSame(1, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_NUMBERING the parts of '.$file.' are not Postgres1 to Postgres<n> with n of at least 2: '.$numbers, $process->getOutput());
    }

    public function test_a_configuration_that_cannot_be_listed_is_never_a_pass(): void
    {
        $process = $this->check($this->folder.'/absent.xml');

        $this->assertSame(2, $process->getExitCode(), $process->getOutput());
        $this->assertStringContainsString('POSTGRES_PARTS_UNREADABLE the tests of Postgres cannot be listed', $process->getOutput());
    }

    public function test_each_later_part_names_directories_of_the_suite_and_part_one_excludes_exactly_those(): void
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

        $parts = self::parts();
        $this->assertGreaterThanOrEqual(2, count($parts));
        $later = [];

        foreach (array_slice($parts, 1) as $part) {
            $directories = self::paths($xpath, $part, 'directory');
            $this->assertNotSame([], $directories, $part);
            $this->assertSame([], self::paths($xpath, $part, 'exclude'), $part);

            foreach ($directories as $directory) {
                $this->assertStringStartsWith('tests/Feature/', $directory);
                $this->assertDirectoryExists(self::projectRoot().'/'.$directory);
                $later[] = $directory;
            }
        }

        $excludes = self::paths($xpath, 'Postgres1', 'exclude');
        sort($later);
        sort($excludes);
        $this->assertSame($later, $excludes);
    }

    public function test_composer_keeps_the_whole_suite_and_runs_each_part(): void
    {
        $composer = json_decode((string) file_get_contents(self::projectRoot().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($composer);
        $scripts = $composer['scripts'] ?? null;
        $this->assertIsArray($scripts);

        $this->assertSame('phpunit --testsuite=Postgres', $scripts['test:postgres'] ?? null);

        $parts = self::parts();

        foreach ($parts as $index => $part) {
            $this->assertSame('phpunit --testsuite='.$part, $scripts['test:postgres:'.($index + 1)] ?? null);
        }

        $this->assertArrayNotHasKey('test:postgres:'.(count($parts) + 1), $scripts);

        $lint = $scripts['lint'] ?? null;
        $this->assertIsArray($lint);
        $this->assertContains('bash scripts/checks/postgres-parts.sh', $lint);
    }

    public function test_each_part_has_its_own_job_and_command_contract_passes_only_after_every_part(): void
    {
        $workflow = (string) file_get_contents(self::projectRoot().'/.github/workflows/ci.yml');
        $jobs = self::jobs($workflow);

        $n = count(self::parts());
        $names = [];

        for ($k = 1; $k <= $n; $k++) {
            $names[] = sprintf('PostgreSQL (%d/%d)', $k, $n);
        }

        // TASK-037's job Restore rehearsal runs beside them, on its own runner and local environment.
        $this->assertSame(['Command contract', ...$names, 'Restore rehearsal', 'Image checks'], array_keys($jobs));
        $this->assertDoesNotMatchRegularExpression('/run:.*composer test:postgres/', $jobs['Restore rehearsal']);

        foreach ($names as $index => $name) {
            $this->assertStringContainsString('run: docker compose exec -T app composer test:postgres:'.($index + 1)."\n", $jobs[$name]);
        }

        $this->assertDoesNotMatchRegularExpression('/run:.*composer test:postgres/', $jobs['Command contract']);

        // Command contract's last step waits for every part of this run.
        preg_match_all('/^\s*run: (.*)$/m', $jobs['Command contract'], $runs);
        $expected = 'bash scripts/checks/required-jobs.sh '.implode(' ', array_map(static fn (string $name): string => '"'.$name.'"', $names));
        $this->assertSame($expected, trim((string) end($runs[1])));
        $this->assertStringContainsString('actions: read', $jobs['Command contract']);

        // No job has a condition; only the stop of each local environment runs
        // always — Command contract's, each part's and the rehearsal's; no
        // failure is let through.
        preg_match_all('/^\s*if:.*$/m', $workflow, $conditions);
        $this->assertSame(array_fill(0, $n + 2, 'if: always()'), array_map('trim', $conditions[0]));
        $this->assertStringNotContainsString('continue-on-error', $workflow);
    }

    /**
     * The parts of phpunit.xml, Postgres1 to Postgres<n>, in the order of
     * their numbers; the numbering itself is the check's to prove.
     *
     * @return list<string>
     */
    private static function parts(): array
    {
        $document = new DOMDocument;
        self::assertTrue($document->load(self::projectRoot().'/phpunit.xml'));
        $nodes = (new DOMXPath($document))->query('/phpunit/testsuites/testsuite/@name');
        self::assertNotFalse($nodes);
        $numbers = [];

        foreach ($nodes as $node) {
            if (preg_match('/^Postgres([1-9]\d*)$/', (string) $node->nodeValue, $match) === 1) {
                $numbers[] = (int) $match[1];
            }
        }

        sort($numbers);
        self::assertSame(range(1, max(1, count($numbers))), $numbers);

        return array_map(static fn (int $number): string => 'Postgres'.$number, $numbers);
    }

    private function check(string $configuration): Process
    {
        return self::runCheck(['bash', 'scripts/checks/postgres-parts.sh', $configuration]);
    }

    /**
     * A configuration in the samples' folder with the suites Postgres,
     * Postgres1, Postgres2 and one further part for each list of $more.
     *
     * @param  list<string>  $suite
     * @param  list<string>  $one
     * @param  list<string>  $oneExcludes
     * @param  list<string>  $two
     * @param  list<list<string>>  $more
     */
    private function configuration(array $suite, array $one, array $oneExcludes, array $two, array $more = []): string
    {
        $file = $this->folder.'/phpunit-'.bin2hex(random_bytes(4)).'.xml';
        $further = '';

        foreach ($more as $index => $directories) {
            $further .= sprintf('<testsuite name="Postgres%d">%s</testsuite>', $index + 3, self::elements('directory', $directories));
        }

        file_put_contents($file, sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<phpunit><testsuites><testsuite name="Postgres">%s</testsuite><testsuite name="Postgres1">%s%s</testsuite><testsuite name="Postgres2">%s</testsuite>%s</testsuites></phpunit>'."\n",
            self::elements('directory', $suite),
            self::elements('directory', $one),
            self::elements('exclude', $oneExcludes),
            self::elements('directory', $two),
            $further,
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
