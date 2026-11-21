<?php

namespace Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use Tests\Performance\Budgets;
use Tests\Performance\Dataset\DatasetSize;
use Tests\Unit\StaticChecks\RunsChecks;

/**
 * The scenarios and their recording format cover every budget of TECHNICAL_RULES section 13.1 (AC-043-02;
 * TASK-043 scope item 2): Budgets names each operation of the section's table, in its order, with the
 * figure and the statistic the section states, and the k6 script measures each into its trend and
 * failure counter. The full datasets are those of section 13.2 (AC-043-01).
 */
final class BudgetsTest extends TestCase
{
    use RunsChecks;

    public function test_each_budget_of_section_13_1_has_its_metric_and_figure(): void
    {
        $rules = (string) file_get_contents(self::projectRoot().'/docs/06-api-performance/TECHNICAL_RULES.md');
        $section = (string) strstr((string) strstr($rules, '### 13.1 Proposed budgets'), '### 13.2', true);
        preg_match_all('/^\| (?!Operation |---)([^|]+?) \| ([^|]+?) \|/m', $section, $rows, PREG_SET_ORDER);

        $this->assertCount(12, $rows, 'Section 13.1 holds twelve budgets.');
        $this->assertSame(array_map(static fn (array $row): string => $row[1], $rows), array_column(array_values(Budgets::METRICS), 'operation'));

        foreach (array_values(Budgets::METRICS) as $index => $budget) {
            $stated = $rows[$index][2] ?? '';

            if ($budget['statistic'] === 'max') {
                $this->assertStringContainsString('30-second budget', $stated);
                $this->assertSame(30000, $budget['milliseconds']);

                continue;
            }

            $this->assertMatchesRegularExpression('/^p95 ≤ [0-9]+ m?s/', $stated);
            preg_match('/≤ ([0-9]+) (ms|s)/', $stated, $figure);
            $this->assertSame((int) ($figure[1] ?? 0) * (($figure[2] ?? '') === 's' ? 1000 : 1), $budget['milliseconds'], $budget['operation']);
        }

        $this->assertStringContainsStringIgnoringCase(Budgets::LOAD, $rules);
    }

    public function test_the_scenarios_measure_each_metric(): void
    {
        $script = (string) file_get_contents(self::projectRoot().'/tests/Performance/scenarios/perf01.js');
        preg_match('/const OPERATIONS = \[(.*?)\];/s', $script, $list);
        preg_match_all("/'([a-z0-9_]+)'/", $list[1] ?? '', $names);

        $this->assertSame(array_keys(Budgets::METRICS), array_map(static fn (string $name): string => 'op_'.$name, $names[1]));

        // Each operation is measured somewhere besides its declaration.
        foreach ($names[1] as $name) {
            $this->assertGreaterThanOrEqual(2, substr_count($script, "'".$name."'"), $name);
        }

        $this->assertStringContainsString("'p(95)'", $script);
        $this->assertStringContainsString("'count'", $script);

        // Review R-01: the Product Search budget times the page's results, RD-01 in the context `catalog`
        // with its sequence header, not the page's shell, which carries no result.
        $this->assertMatchesRegularExpression("~read\\(`/data/products/search\\?context=catalog&q=\\\$\\{term\\}`, 'product_search', false, \\{ 'X-Search-Sequence'~", $script);
        $this->assertStringNotContainsString("'product_search', null", $script);

        // Review R-02: a report answered with `report: null` (UXS-22) is a failure, never a sample.
        $this->assertSame(2, substr_count($script, "null, 'report');"));
        $this->assertStringContainsString('props[required] !== null', $script);
    }

    public function test_the_full_datasets_are_those_of_section_13_2(): void
    {
        $base = DatasetSize::base(DatasetSize::FULL);
        $this->assertSame([50, 5000, 500, 2500, 500, 10000, 2999, 7000, 9, 10, 365], [$base->categories, $base->activeProducts, $base->inactiveProducts, $base->secondTokens, $base->retiredTokens, $base->heavyMovements, $base->saleDocuments, $base->receivingDocuments, $base->linesPerSale, $base->linesPerReceiving, $base->days]);

        // 5,000 opening lines, 2,999 Sales of 9 lines and 7,000 receivings of 10: about 100,000 movement lines,
        // 10,000 of them of the first product; 7,500 active tokens.
        $this->assertSame(101991, $base->activeProducts + $base->saleDocuments * $base->linesPerSale + $base->receivingDocuments * $base->linesPerReceiving);
        $this->assertSame(10000, 1 + $base->saleDocuments + $base->receivingDocuments);
        $this->assertSame(7500, $base->activeProducts + $base->secondTokens);

        $growth = DatasetSize::growth(DatasetSize::FULL);
        $this->assertSame([600000, 3650, 4, 4], [$growth->saleDocuments, $growth->days, $growth->linesPerSale, $growth->printsPerSale]);

        // A small scale keeps the shape: the product of many movements, a Sale and receivings.
        $small = DatasetSize::base(100);
        $this->assertSame([12, 6, 1, 4, 1], [$small->activeProducts, $small->heavyMovements, $small->saleDocuments, $small->receivingDocuments, $small->days]);
    }
}
