<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Inventory\OnboardingState;
use App\Http\Requests\Inventory\AdjustmentPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Catalog\ProductRows;
use App\Queries\Inventory\ProductStock;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * RP-410, UXP-410 "Sesuaikan stok" (ROUTE_CONTRACTS RP-410; NAVIGATION_CONTRACTS UXP-410;
 * BR-INV-31; CAP-16, the Owner's alone; TASK-024 scope item 3): a draft in page memory, with the
 * product as its first line when the address names it, read as RD-01 in the context `adjustment`
 * reads it — with its version, which RC-09 expects, and its stock — so that the page adds it as it
 * adds a result; a product that does not exist is NOT_FOUND (TR-ERR-03). It also says whether the
 * onboarding freeze stops an adjustment (UXS-27) and which page opened it, for its way back.
 */
final class AdjustmentsController
{
    /**
     * @throws NotFound
     */
    public function create(AdjustmentPageRequest $request, OnboardingState $onboarding, ProductStock $stock): Response
    {
        $productId = $request->product();
        $product = null;

        if ($productId !== null) {
            $row = ProductRows::query()->where('p.id', $productId)->first() ?? throw new NotFound;
            $viewer = $request->user();
            $grant = $viewer instanceof User ? Capabilities::of($viewer, Capability::Cap14) : Grant::None;
            $rows = $stock->added(['rows' => [ProductRows::row($row)->withVersion()], 'page' => 1, 'perPage' => 1, 'hasMore' => false], $grant)['rows'];
            $product = $rows[0] ?? throw new LogicException('A product read by its key is one row.');
        }

        return Inertia::render('inventory/AdjustmentCreate', [
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            'product' => $product,
            'opener' => $request->opener(),
        ]);
    }
}
