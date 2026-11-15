<?php

namespace Tests\Feature\Operations\Restore;

use App\Database\Identifier;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Restore\TestRestore;

/**
 * The sequence advance of BACKUP_RECOVERY RB-01 step 13 after the restores of TEST_STRATEGY TS-24
 * (AC-037-02; TASK-037 scope item 5; SCHEMA SCH-26 to SCH-28; suite REC02): after a restore and OP-04,
 * scripts/operations/restore/advance-sequences.sql, run as the migration role, sets both number sequences
 * so that no Sale or cash session number issued after the backup point is issued again — more than 100,000
 * above the restored value and above the highest counter of the gap review — and refuses another
 * environment, the runtime role and a counter that is no whole number, changing nothing.
 */
final class SequenceAdvanceTest extends DatabaseTestCase
{
    private const string SCRIPT = 'scripts/operations/restore/advance-sequences.sql';

    private const string PSQL = '/usr/lib/postgresql/18/bin/psql';

    private string $area = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = sys_get_temp_dir().'/bk-restore-test-'.Str::random(12);
        File::ensureDirectoryExists($this->area, 0700);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->area);

        parent::tearDown();
    }

    public function test_no_number_issued_after_the_backup_point_is_issued_again(): void
    {
        $product = TokoContoh::product('SQ-01', ['categoryId' => TokoContoh::category()]);
        TokoContoh::stock($product, '20', '1000');
        $register = TokoContoh::register();
        $first = TokoContoh::openSession('Rina', $register);
        TokoContoh::sell('Rina', $first, [['productId' => $product, 'quantity' => '1']]);
        $dump = TestRestore::dump($this->area);

        // After the backup point: numbers in customers' hands that the restore loses.
        $lostSale = TokoContoh::sell('Rina', $first, [['productId' => $product, 'quantity' => '1']]);
        TokoContoh::closeSession($first);
        $lostSession = TokoContoh::openSession('Rina', TokoContoh::register('LACI-2', 'Laci 2'));
        $issued = [$this->saleNumbers(), $this->sessionNumbers()];
        $seen = [self::counterOf($this->readText($this->observer()->table('sales')->where('id', $lostSale)->value('sale_number'))), self::counterOf($this->readText($this->observer()->table('cash_sessions')->where('id', $lostSession)->value('session_number')))];

        TestRestore::restore($dump);
        $this->assertSame(0, Artisan::call('restore:after', ['--environment' => 'test', '--note' => 'Pemulihan insiden S1']), Artisan::output());
        $restored = [$this->lastValue('sale_number_seq'), $this->lastValue('cash_session_number_seq')];

        // The script refuses what it must never touch, and changes nothing then.
        $this->assertStringContainsString('the typed environment is not the marker of this database', $this->advance('production', $seen, 'pgsql_migration')->errorOutput());
        $this->assertStringContainsString('the session is not the migration role of this database', $this->advance('test', $seen, 'pgsql')->errorOutput());
        $this->assertStringContainsString('sales_seen and sessions_seen must be the highest counters seen', $this->advance('test', ['1; select 1', '0'], 'pgsql_migration')->errorOutput());
        $this->assertSame($restored, [$this->lastValue('sale_number_seq'), $this->lastValue('cash_session_number_seq')]);

        $advanced = $this->advance('test', $seen, 'pgsql_migration');
        $this->assertTrue($advanced->successful(), $advanced->errorOutput());
        $this->assertSame([max($restored[0], $seen[0]) + 100000, max($restored[1], $seen[1]) + 100000], [$this->lastValue('sale_number_seq'), $this->lastValue('cash_session_number_seq')]);

        // The numbers issued now are new ones: the session of the restored data closes, a new one opens,
        // and each takes a counter more than 100,000 above anything issued before.
        TokoContoh::closeSession($first);
        $session = TokoContoh::openSession('Rina', TokoContoh::register('LACI-3', 'Laci 3'));
        $sale = TokoContoh::sell('Rina', $session, [['productId' => $product, 'quantity' => '1']]);
        $saleNumber = $this->readText($this->observer()->table('sales')->where('id', $sale)->value('sale_number'));
        $sessionNumber = $this->readText($this->observer()->table('cash_sessions')->where('id', $session)->value('session_number'));
        $this->assertNotContains($saleNumber, $issued[0]);
        $this->assertNotContains($sessionNumber, $issued[1]);
        $this->assertGreaterThan(max($restored[0], $seen[0]) + 100000, self::counterOf($saleNumber));
        $this->assertGreaterThan(max($restored[1], $seen[1]) + 100000, self::counterOf($sessionNumber));
    }

    /**
     * @param  array{int|string, int|string}  $seen
     */
    private function advance(string $environment, array $seen, string $connection): ProcessResult
    {
        $value = static fn (string $name): string => is_scalar($setting = config('database.connections.'.$connection.'.'.$name)) ? (string) $setting : '';

        return Process::timeout(60)->env(['PGPASSWORD' => ''])->run([
            self::PSQL, '-X', '-v', 'ON_ERROR_STOP=1', '-v', 'environment='.$environment, '-v', 'sales_seen='.$seen[0], '-v', 'sessions_seen='.$seen[1],
            '-h', $value('host'), '-p', $value('port'), '-U', $value('username'), '-d', $value('database'),
            '-f', base_path(self::SCRIPT),
        ]);
    }

    private function lastValue(string $sequence): int
    {
        $value = $this->migration()->scalar('select last_value from '.Identifier::quote($sequence));
        $this->assertIsInt($value);

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function saleNumbers(): array
    {
        return array_values($this->observer()->table('sales')->pluck('sale_number')->all());
    }

    /**
     * @return list<mixed>
     */
    private function sessionNumbers(): array
    {
        return array_values($this->observer()->table('cash_sessions')->pluck('session_number')->all());
    }

    private static function counterOf(string $number): int
    {
        return (int) substr($number, strpos($number, '-') + 1);
    }
}
