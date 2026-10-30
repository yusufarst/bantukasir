<?php

namespace App\Queries\Configuration;

use App\Domain\Configuration\Accents;
use App\DTO\Configuration\ShopData;
use Illuminate\Support\Facades\DB;

/**
 * The shop's identity for the shell (DESIGN_SYSTEM section 7.2; SEC-CH-02;
 * TASK-010 scope item 6): one read of the revision in force, without a lock.
 * The initials are the first letters of the name's first two words, in
 * capitals ("Toko Contoh" gives "TC"; a name of one word, its first letter).
 */
final class Shop
{
    public function __invoke(): ShopData
    {
        $row = DB::table('business_profile_current', 'c')
            ->join('business_profile_revisions as r', 'r.id', '=', 'c.revision_id')
            ->where('c.id', 1)
            ->first(['r.shop_name', 'r.accent_token']);

        $values = $row === null ? [] : (array) $row;
        $name = is_string($values['shop_name'] ?? null) ? $values['shop_name'] : null;
        $accent = is_string($values['accent_token'] ?? null) ? $values['accent_token'] : null;

        return new ShopData($name, $name === null ? null : self::initials($name), Accents::rendered($accent));
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';

        foreach (array_slice($words === false ? [] : $words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }
}
