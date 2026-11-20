<?php

namespace Tests\Feature\Operations\Housekeeping;

use App\Console\Commands\HousekeepingRun;
use App\Database\PrivilegeMatrix;
use App\Domain\Commands\Operations;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * The static inventory of KEEP01 (AC-038-03; TASK-038 scope item 2 and section 7, the L4 route inventory;
 * SCHEMA section 4 and SCH-11; REQ-OPS-05; D-044): no product path deletes or rewrites a row of classes F,
 * FS, M or S, and no delete, purge or archival function exists for business records or master data.
 *
 * - The privilege matrix lets the runtime role delete only from tables of classes E, L and P, and never
 *   update a retained fact; db:check-privileges and the privilege family prove the database equals it.
 * - Every deletion in the application's code — SQL, the query builder, the file system — is in the reviewed
 *   inventory below with what it removes, and each table it names is of class E, L or P. A new deletion
 *   fails here until it is reviewed and listed. The scan finds a file's tables only where the code names
 *   them literally, so a further deletion in a file already listed that names none is not told apart
 *   (review R-03): the scan supplements the privilege family, which stays the enforcement for tables.
 * - No route deletes: no DELETE method, no command operation of deletion, purge or archival, and no route
 *   reaches the housekeeping, which is a console command only (ROUTE_CONTRACTS section 7). The application's
 *   own console commands hold no such function either; housekeeping:run is its one cleanup.
 */
final class RetentionInventoryTest extends TestCase
{
    /** Any form of deletion in code, comments excluded. */
    private const string DELETION = '/->delete\s*\(|::delete\s*\(|\bdelete\s+from\b|\btruncate\b|->destroy\s*\(|::destroy\s*\(|forceDelete|deleteDirectory|cleanDirectory|\bunlink\s*\(|\brmdir\s*\(|\b(?:Mass)?Prunable\b|->detach\s*\(|->sync\s*\(/i';

    /** A table named by an SQL deletion. */
    private const string SQL_TARGET = '/\b(?:delete\s+from|truncate(?:\s+table)?)\s+([a-z_][a-z0-9_]*)\b/i';

    /** A table named by a query-builder deletion in the same statement. */
    private const string BUILDER_TARGET = '/table\(\s*\'([a-z_][a-z0-9_]*)\'\s*\)[^;]*->(?:delete|truncate)\s*\(/';

    /** A verb of deletion, purge or archival in a name. */
    private const string VERB = '/(?:^|[._:\-])(?:delete|purge|archive|wipe|truncate|destroy|erase|prune|expunge)/i';

    /**
     * Each file of the application that deletes anything, with the tables of SCHEMA section 4 it deletes
     * from, or none when it removes files of an area outside the database or only names the forms.
     */
    private const array INVENTORY = [
        // Sign-in sessions end and are removed (SEC-SESS-04, class E).
        'app/Domain/Access/Accounts.php' => ['sessions'],
        // A link deleted when consumed or revoked, and expired ones by SC-02 (SEC-ACC-06, SEC-ACC-07, class E).
        'app/Domain/Access/OneTimeLinks.php' => ['password_reset_tokens'],
        // SC-02: entries older than 365 days, within the database's policy (SEC-LOG-05, class L).
        'app/Domain/Audit/SecurityLog.php' => ['security_log_entries'],
        // Projection maintenance: the active mapping of a retired mapping (SCH-11, class P).
        'app/Domain/Catalog/Barcodes/Barcodes.php' => ['barcode_active_mappings'],
        // The temporary CSV import area, outside the database (D-054, ARC-43).
        'app/Domain/Catalog/Import/CsvImportFiles.php' => [],
        // SC-02: resolved receipts after 30 days (D-088, class E).
        'app/Domain/Commands/RequestReceipts.php' => ['command_request_receipts'],
        // SC-02: files that no stored_files row names, after the grace period (TR-TX-05).
        'app/Domain/Media/MediaStore.php' => [],
        // The backup run's own private temporary area (BACKUP_RECOVERY section 4).
        'app/Domain/Operations/Backup/BackupRunner.php' => [],
        // The migration review names these forms to refuse them; it deletes nothing (RELEASE_PROCESS REL-20).
        'app/Domain/Operations/Release/MigrationReview.php' => [],
    ];

    public function test_the_runtime_role_deletes_only_ephemeral_data_log_entries_and_projections(): void
    {
        $deletable = [];

        foreach (PrivilegeMatrix::load()->tables() as $name => $table) {
            if (in_array($table['class'], ['F', 'FS', 'M', 'S'], true)) {
                $this->assertFalse($table['runtime']['delete'], $name);
            }

            if (in_array($table['class'], ['F', 'L'], true)) {
                $this->assertFalse($table['runtime']['update'], $name);
            }

            if ($table['runtime']['delete']) {
                $deletable[$name] = $table['class'];
            }
        }

        $this->assertSame([], array_diff(array_unique(array_values($deletable)), ['E', 'L', 'P']));
        $this->assertSame('L', $deletable['security_log_entries'] ?? null);
        $this->assertSame('E', $deletable['command_request_receipts'] ?? null);
        $this->assertSame('E', $deletable['password_reset_tokens'] ?? null);
    }

    public function test_every_deletion_in_the_code_is_reviewed_and_targets_no_retained_class(): void
    {
        $matrix = PrivilegeMatrix::load();
        $found = [];

        foreach (['app', 'routes'] as $root) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                $targets = $this->targetsIn(self::code($file->getPathname()));

                if ($targets === null) {
                    continue;
                }

                $found[$relative] = $targets;
            }
        }

        ksort($found);
        $this->assertSame(array_keys(self::INVENTORY), array_keys($found), 'A deletion outside the reviewed inventory, or one gone from it.');

        foreach ($found as $file => $targets) {
            $this->assertSame([], array_diff($targets, self::INVENTORY[$file]), $file.' deletes from a table its entry does not name.');
        }

        foreach (self::INVENTORY as $file => $tables) {
            foreach ($tables as $table) {
                $this->assertContains($matrix->table($table)['class'], ['E', 'L', 'P'], $file.': '.$table);
            }
        }
    }

    public function test_the_scan_finds_a_deletion_of_a_retained_table(): void
    {
        // The negative samples: each form is found and names its table.
        $this->assertSame(['audit_events'], $this->targetsIn("<?php DB::table('audit_events')->where('id', 1)->delete();"));
        $this->assertSame(['sales'], $this->targetsIn("<?php DB::delete('delete from sales where id = ?', [1]);"));
        $this->assertSame(['products'], $this->targetsIn("<?php DB::statement('TRUNCATE TABLE products');"));
        $this->assertSame([], $this->targetsIn('<?php File::delete($path);'));
        $this->assertSame([], $this->targetsIn('<?php File::cleanDirectory($path);'));
        $this->assertSame([], $this->targetsIn('<?php final class Sale extends Model { use Prunable; }'));
        $this->assertNull($this->targetsIn("<?php // delete from sales\n/* DB::table('sales')->delete(); */ \$a = 1;"));
    }

    public function test_no_route_deletes_and_none_reaches_the_housekeeping(): void
    {
        // L4: TR-HTTP-04; ROUTE_CONTRACTS section 7: SC-02 is a console command only.
        $routes = app(Router::class)->getRoutes()->getRoutes();
        $this->assertNotSame([], $routes);

        foreach ($routes as $route) {
            $this->assertNotContains('DELETE', $route->methods(), $route->uri());
            $this->assertStringNotContainsString('housekeeping', strtolower($route->uri().' '.$route->getActionName().' '.(string) $route->getName()), $route->uri());
            $this->assertStringNotContainsString('App\\Domain\\Operations\\Housekeeping', $route->getActionName(), $route->uri());
        }

        foreach (array_keys(Operations::FAMILIES) as $operation) {
            $this->assertDoesNotMatchRegularExpression(self::VERB, $operation);
        }

        $cleanups = [];

        foreach (Artisan::all() as $name => $command) {
            if (! str_starts_with($command::class, 'App\\Console\\Commands\\')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(self::VERB, $name);

            if (preg_match('/housekeeping|clean|clear|expire/i', $name) === 1) {
                $cleanups[] = $command::class;
            }
        }

        $this->assertSame([HousekeepingRun::class], $cleanups);
    }

    /**
     * The tables that the code deletes from, or null when it deletes nothing.
     *
     * @return list<string>|null
     */
    private function targetsIn(string $code): ?array
    {
        $code = self::stripComments($code);

        if (preg_match(self::DELETION, $code) !== 1) {
            return null;
        }

        preg_match_all(self::SQL_TARGET, $code, $sql);
        preg_match_all(self::BUILDER_TARGET, $code, $builder);
        $tables = array_values(array_unique(array_map(strtolower(...), [...$sql[1], ...$builder[1]])));
        sort($tables);

        return $tables;
    }

    private static function code(string $path): string
    {
        $code = file_get_contents($path);

        return is_string($code) ? $code : '';
    }

    private static function stripComments(string $code): string
    {
        $kept = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $kept .= is_array($token) ? $token[1] : $token;
        }

        return $kept;
    }
}
