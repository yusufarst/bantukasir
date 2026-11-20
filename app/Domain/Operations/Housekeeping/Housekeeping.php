<?php

namespace App\Domain\Operations\Housekeeping;

use App\Domain\Access\OneTimeLinks;
use App\Domain\Audit\SecurityLog;
use App\Domain\Catalog\Import\CsvImportFiles;
use App\Domain\Commands\RequestReceipts;
use App\Domain\Media\MediaStore;
use App\Support\Time\Clock;
use Closure;

/**
 * The cleanups of SC-02 `housekeeping:run` (ROUTE_CONTRACTS SC-02; ENVIRONMENTS sections 13 and 14;
 * SCHEMA section 4 and SCH-11; TASK-038 scope item 1), each by the service of the module that owns its
 * data (STACK_AND_MODULES section 2): expired one-time links (Access), resolved request receipts older
 * than 30 days (Commands), security log entries older than 365 days (Audit), stored files that no row
 * names after their grace period (Media) and temporary CSV import files older than 24 hours (Catalog).
 * These are the only cleanups: no table of class F, FS, M or S is ever their target, and each takes only
 * its own kind at its own age.
 *
 * One instant of the application clock bounds the whole run. Each table is cleaned in bounded batches,
 * one short transaction each, until a batch removes less than it may or the run's limit is reached; the
 * file areas are each walked once, up to the same limit. A failure ends the run where it happened; what
 * is left waits for the next hour's run.
 */
final class Housekeeping
{
    public function __construct(
        private readonly Clock $clock,
        private readonly OneTimeLinks $links,
        private readonly RequestReceipts $receipts,
        private readonly SecurityLog $securityLog,
        private readonly MediaStore $media,
        private readonly CsvImportFiles $csvImports,
    ) {}

    public function run(HousekeepingSettings $settings): HousekeepingSummary
    {
        $now = $this->clock->now();

        $links = $this->inBatches($settings, fn (int $limit): int => $this->links->removeExpired($now, $limit));
        $receipts = $this->inBatches($settings, fn (int $limit): int => $this->receipts->removeResolved($now, $limit));
        $entries = $this->inBatches($settings, fn (int $limit): int => $this->securityLog->removeExpired($now, $limit));
        $files = $this->media->removeUnreferenced($now, $settings->fileGraceSeconds, $settings->batchSize, $settings->limit());
        $csv = $this->csvImports->removeExpired($now, $settings->limit());

        return new HousekeepingSummary($links, $receipts, $entries, $files, $csv);
    }

    /**
     * @param  Closure(int): int  $batch  removes at most the given number and returns how many it removed
     */
    private function inBatches(HousekeepingSettings $settings, Closure $batch): int
    {
        $removed = 0;

        for ($round = 0; $round < $settings->maxBatches; $round++) {
            $count = $batch($settings->batchSize);
            $removed += $count;

            if ($count < $settings->batchSize) {
                break;
            }
        }

        return $removed;
    }
}
