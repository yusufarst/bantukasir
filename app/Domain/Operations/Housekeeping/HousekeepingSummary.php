<?php

namespace App\Domain\Operations\Housekeeping;

/**
 * What one run of SC-02 removed, by kind: counts only, never a key, a name or an address.
 */
final readonly class HousekeepingSummary
{
    public function __construct(
        public int $expiredLinks,
        public int $resolvedReceipts,
        public int $securityLogEntries,
        public int $unreferencedFiles,
        public int $temporaryCsvFiles,
    ) {}

    /**
     * @return array{expired_links: int, resolved_receipts: int, security_log_entries: int, unreferenced_files: int, temporary_csv_files: int}
     */
    public function counts(): array
    {
        return [
            'expired_links' => $this->expiredLinks,
            'resolved_receipts' => $this->resolvedReceipts,
            'security_log_entries' => $this->securityLogEntries,
            'unreferenced_files' => $this->unreferencedFiles,
            'temporary_csv_files' => $this->temporaryCsvFiles,
        ];
    }
}
