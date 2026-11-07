<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\OnboardingState;
use App\Domain\Inventory\StockLedger;
use App\Http\Requests\Inventory\ReversalPageRequest;
use App\Policies\NotFound;
use App\Queries\Inventory\Reversibility;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-411, UXP-411 "Batalkan mutasi" (ROUTE_CONTRACTS RP-411; NAVIGATION_CONTRACTS UXP-411; BR-INV-34
 * to BR-INV-37; CAP-17, the Owner's alone; TASK-026 scope item 4): the movement to reverse, its exact
 * inverse and the stock now and after, for a draft — the reason — in page memory only (NC-10); whether
 * it is already reversed and whether the onboarding freeze stops it, so that an ineligible source
 * shows why (UXS-13); and the page that opened it. A movement that does not exist or that no reversal
 * may cancel is NOT_FOUND (TR-ERR-03).
 */
final class ReversalsController
{
    /**
     * @throws NotFound
     */
    public function create(ReversalPageRequest $request, string $movement, Reversibility $reversibility, OnboardingState $onboarding): Response
    {
        $preview = $reversibility->preview($movement);

        return Inertia::render('inventory/Reversal', [
            ...$preview,
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            'opener' => self::openerOf($request->opener(), $preview['source']['type'] ?? null),
        ]);
    }

    /**
     * The page that opened RP-411, when it is one that shows this source: a receiving document only
     * for a RECEIPT, a manual issue only for a MANUAL_ISSUE; otherwise none, and the way back is
     * UXP-403 of the movement (NAVIGATION_CONTRACTS UXP-411; review finding R-06).
     */
    private static function openerOf(?string $opener, mixed $type): ?string
    {
        return match ($opener) {
            'receiving' => $type === StockLedger::RECEIPT ? $opener : null,
            'issue' => $type === StockLedger::MANUAL_ISSUE ? $opener : null,
            default => $opener,
        };
    }
}
