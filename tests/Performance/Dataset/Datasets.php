<?php

namespace Tests\Performance\Dataset;

use App\Actions\Operations\RunReconciliation;
use App\Database\DatabaseRefused;
use App\Domain\Operations\Reconciliation\ReconciliationRuns;
use App\Support\Time\BusinessDate;
use App\Support\Time\Clock;
use App\Support\Time\TestClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The provisional benchmark dataset and the ten-year growth dataset of TECHNICAL_RULES section 13.2
 * (ASSUMPTION; OPEN_ITEMS T-09; TASK-043 scope item 1, AC-043-01), synthetic, created through the
 * Actions on an empty installation of Toko Contoh, only where the database's marker and the configured
 * environment name are both test or local (ENVIRONMENTS ENV-04, through TokoContoh::build). The same
 * seed, scale and last day give the same dataset (Fingerprint), and every build ends with a run of the
 * reconciliation of SC-01 over every product, which must be clean.
 *
 * The test clock stands at each record's instant; the dataset's last day is in the past of the real
 * clock, so that the server, which reads the real one, sees a shop whose history ends yesterday.
 */
final class Datasets
{
    public const string BASE = 'base';

    public const string GROWTH = 'growth';

    /** The seed of the dataset that the CI rehearsal and the dry run use (TASK-043). */
    public const int SEED = 43;

    /**
     * Builds the dataset and returns its summary: its name, seed, scale and last day, its counts and
     * the outcome of the reconciliation after it.
     *
     * @return array{dataset: string, seed: int, scale: int, until: string, heavyProduct: int, counts: array<string, int>, reconciliation: string}
     *
     * @throws DatabaseRefused on any database whose marker is not test or local
     */
    public static function build(string $dataset, int $seed, int $scale, string $until): array
    {
        TokoContoh::build(DB::connection());

        if (DB::table('products')->exists() || DB::table('sales')->exists()) {
            throw new LogicException('A dataset is built on an installation without products or Sales.');
        }

        $last = BusinessDate::fromString($until);
        $size = match ($dataset) {
            self::BASE => DatasetSize::base($scale),
            self::GROWTH => DatasetSize::growth($scale),
            default => throw new InvalidArgumentException('The dataset is base or growth.'),
        };

        // The catalogue on the day before the first day of the history, from midnight in Asia/Jakarta.
        $first = CarbonImmutable::parse($last->toString().'T00:00:00+07:00')->subDays($size->days);
        $clock = new TestClock($first);
        app()->instance(Clock::class, $clock);

        $random = new Seeded($seed);
        $shop = new ShopBuilder($random, $clock);
        $shop->catalogue($size, $first);
        $active = $shop->active();
        $heavy = $active[0];

        if ($dataset === self::BASE) {
            self::history($shop, $random, $clock, $size, $first, $active);
        } else {
            self::growth($shop, $random, $clock, $size, $first, $active);
        }

        $outcome = app(RunReconciliation::class)(null, ReconciliationRuns::SCHEDULE)->outcome;

        return ['dataset' => $dataset, 'seed' => $seed, 'scale' => $scale, 'until' => $last->toString(), 'heavyProduct' => $heavy, 'counts' => self::counts($heavy), 'reconciliation' => $outcome];
    }

    /**
     * What the dataset holds, counted from the database.
     *
     * @return array<string, int>
     */
    public static function counts(int $heavy): array
    {
        $count = static fn (string $table): int => DB::table($table)->count();

        return [
            'categories' => $count('categories'),
            'activeProducts' => DB::table('products')->where('active', true)->count(),
            'inactiveProducts' => DB::table('products')->where('active', false)->count(),
            'activeTokens' => $count('barcode_active_mappings'),
            'retiredTokens' => $count('barcode_mapping_retirements'),
            'movements' => $count('stock_movements'),
            'heavyProductMovements' => DB::table('stock_movements')->where('product_id', $heavy)->count(),
            'registers' => $count('cash_registers'),
            'cashiers' => count(ShopBuilder::CASHIERS),
            'sessions' => $count('cash_sessions'),
            'sales' => $count('sales'),
            'saleLines' => $count('sale_lines'),
            'receivings' => $count('receiving_documents'),
            'auditEvents' => $count('audit_events'),
        ];
    }

    /**
     * The base dataset's history: the opening receipts, then the documents of the product of 10,000
     * movements spread over the days, each day's Sales in the two cashiers' sessions.
     *
     * @param  non-empty-list<int>  $active
     */
    private static function history(ShopBuilder $shop, Seeded $random, TestClock $clock, DatasetSize $size, CarbonImmutable $first, array $active): void
    {
        $heavy = $active[0];
        $others = array_slice($active, 1);
        $opening = array_fill_keys($others, 60);
        $opening[$heavy] = 2 * $size->saleDocuments + 50;
        ksort($opening);
        $clock->set($first->addHours(22));
        $shop->receiveAll($opening);

        $kinds = $random->shuffle([...array_fill(0, $size->saleDocuments, 'S'), ...array_fill(0, $size->receivingDocuments, 'R')]);
        $total = count($kinds);

        for ($day = 0; $day < $size->days; $day++) {
            $from = intdiv($day * $total, $size->days);
            $kindsOfDay = array_slice($kinds, $from, intdiv(($day + 1) * $total, $size->days) - $from);
            $morning = $first->addDays($day + 1)->addHours(7);
            $clock->set($morning);
            $sessions = in_array('S', $kindsOfDay, true) ? $shop->openSessions() : [];
            $step = intdiv(13 * 3600, max(1, count($kindsOfDay)));

            foreach ($kindsOfDay as $index => $kind) {
                $clock->set($morning->addHour()->addSeconds($index * $step));

                if ($kind === 'S') {
                    $cashier = $random->pick(ShopBuilder::CASHIERS);
                    $quantities = [$heavy => 1];

                    foreach ($random->sample($others, $size->linesPerSale - 1) as $product) {
                        $quantities[$product] = $random->between(1, 3);
                    }

                    $shop->sell($cashier, $sessions[$cashier], $quantities, $random->chance(20));
                } else {
                    $quantities = [$heavy => $random->between(10, 50)];

                    foreach ($random->sample($others, $size->linesPerReceiving - 1) as $product) {
                        $quantities[$product] = $random->between(10, 50);
                    }

                    $shop->receive($quantities, sprintf('PO-%05d', $from + $index + 1));
                }
            }

            if ($sessions !== []) {
                $clock->set($morning->addHours(14));
                $shop->closeSessions($sessions);
            }
        }
    }

    /**
     * The ten-year growth: the opening receipts, then each day's Sales of one to four lines in the two
     * cashiers' sessions, each receipt printed and printed again, the stock received whenever a Sale
     * would need more.
     *
     * @param  non-empty-list<int>  $active
     */
    private static function growth(ShopBuilder $shop, Seeded $random, TestClock $clock, DatasetSize $size, CarbonImmutable $first, array $active): void
    {
        $clock->set($first->addHours(22));
        $shop->receiveAll(array_fill_keys($active, 50));
        $sizes = ['58', '80'];

        for ($day = 0; $day < $size->days; $day++) {
            $count = intdiv(($day + 1) * $size->saleDocuments, $size->days) - intdiv($day * $size->saleDocuments, $size->days);
            $morning = $first->addDays($day + 1)->addHours(7);
            $clock->set($morning);

            if ($count === 0) {
                continue;
            }

            $sessions = $shop->openSessions();
            $step = intdiv(13 * 3600, $count);

            for ($s = 0; $s < $count; $s++) {
                $clock->set($morning->addHour()->addSeconds($s * $step));
                $cashier = $random->pick(ShopBuilder::CASHIERS);
                $quantities = [];

                foreach ($random->sample($active, $random->between(1, $size->linesPerSale)) as $product) {
                    $quantities[$product] = $random->between(1, 3);
                }

                $sale = $shop->sell($cashier, $sessions[$cashier], $quantities, $random->chance(20));

                for ($p = 0; $p < $size->printsPerSale; $p++) {
                    $shop->printReceipt($cashier, $sale, $sizes[$p % 2]);
                }
            }

            $clock->set($morning->addHours(14));
            $shop->closeSessions($sessions);
        }
    }
}
