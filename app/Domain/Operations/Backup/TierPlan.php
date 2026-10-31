<?php

namespace App\Domain\Operations\Backup;

use Carbon\CarbonInterface;

/**
 * The tiers one run writes (BACKUP_RECOVERY sections 3 and 4): every run writes
 * db/6h/; the scheduled 00.00 run of a Sunday also db/weekly/ and of the 1st of
 * the month also db/monthly/, in Asia/Jakarta. An on-demand run writes db/6h/ only.
 */
final class TierPlan
{
    public const string SIX_HOURS = '6h';

    public const string WEEKLY = 'weekly';

    public const string MONTHLY = 'monthly';

    private const string TIME_ZONE = 'Asia/Jakarta';

    /**
     * @return non-empty-list<string>
     */
    public static function for(BackupReason $reason, CarbonInterface $startedAt): array
    {
        $tiers = [self::SIX_HOURS];
        $local = $startedAt->copy()->setTimezone(self::TIME_ZONE);

        if ($reason !== BackupReason::SCHEDULED || $local->format('H') !== '00') {
            return $tiers;
        }

        if ($local->format('w') === '0') {
            $tiers[] = self::WEEKLY;
        }

        if ($local->format('j') === '1') {
            $tiers[] = self::MONTHLY;
        }

        return $tiers;
    }
}
