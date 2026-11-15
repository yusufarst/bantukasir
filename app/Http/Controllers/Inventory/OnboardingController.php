<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Inventory\OnboardingState;
use App\DTO\Catalog\CategoryRowData;
use App\Http\Requests\Inventory\OnboardingPageRequest;
use App\Http\Requests\Inventory\OpeningEntryPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Catalog\CategoryRows;
use App\Queries\Catalog\ProductRows;
use App\Queries\Inventory\OnboardingChecklist;
use App\Queries\Inventory\OpeningEligibility;
use App\Queries\Inventory\ProductStock;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * RP-412, UXP-412 "Stok awal", and RP-413, UXP-413 "Input stok awal" (ROUTE_CONTRACTS RP-412, RP-413;
 * NAVIGATION_CONTRACTS UXP-412, UXP-413; BR-INV-23 to BR-INV-28, BR-INV-47, BR-INV-48; D-071, D-072,
 * D-087; CAP-15, the Owner's alone; TASK-035 scope item 5).
 *
 * RP-412: the onboarding state — not started, active since its start, released at its record, or held
 * for a restore reconciliation — with its version, which the onboarding and opening commands expect;
 * one page of the checklist with its filter; and the counts of the release review (BR-INV-28).
 *
 * RP-413: an empty draft in page memory, only while onboarding is open — active, never released and not
 * held — and otherwise `302` to RP-412; with the onboarding version it expects and, when the address
 * names it, the product that "Catat ulang stok awal" names, read as RD-01 in the context `opening` reads
 * it — its version, its stock and its eligibility — so that the page takes it as it takes a result. A
 * product that does not exist is NOT_FOUND (TR-ERR-03).
 */
final class OnboardingController
{
    public function show(OnboardingPageRequest $request, OnboardingState $onboarding, OnboardingChecklist $checklist): Response
    {
        $status = $onboarding->status() ?? throw new LogicException('The bootstrap inserts the onboarding state (SEC-OP-01).');
        $times = $onboarding->times();
        $filters = $request->filters();

        return Inertia::render('inventory/Onboarding', [
            'onboarding' => [
                'state' => $status->state,
                'version' => $status->version,
                'held' => $status->held,
                'startedAt' => $status->state === OnboardingState::ACTIVE ? $times['changedAt'] : null,
                'releasedAt' => $times['releasedAt'],
            ],
            'filters' => ['filter' => $filters['filter']],
            'checklist' => $checklist->page($filters['filter'], $filters['after'], $filters['before']),
            'counts' => $checklist->counts(),
        ]);
    }

    /**
     * @throws NotFound
     */
    public function entry(OpeningEntryPageRequest $request, OnboardingState $onboarding, ProductStock $stock, OpeningEligibility $eligibility, CategoryRows $categories): Response|RedirectResponse
    {
        $status = $onboarding->status();

        if ($status === null || ! $status->isOpen()) {
            return redirect()->route('onboarding.show');
        }

        $productId = $request->product();
        $product = null;

        if ($productId !== null) {
            $row = ProductRows::query()->where('p.id', $productId)->first() ?? throw new NotFound;
            $viewer = $request->user();
            $grant = $viewer instanceof User ? Capabilities::of($viewer, Capability::Cap14) : Grant::None;
            $page = $stock->added(['rows' => [ProductRows::row($row)->withVersion()], 'page' => 1, 'perPage' => 1, 'hasMore' => false], $grant);
            $product = $eligibility->added($page)['rows'][0] ?? throw new LogicException('A product read by its key is one row.');
        }

        return Inertia::render('inventory/OpeningEntry', [
            'onboardingVersion' => $status->version,
            'product' => $product,
            // "Tambah produk" over the page, the draft kept (UXI-293; NC-11), as on RP-405.
            'categories' => array_map(static fn (CategoryRowData $row): array => $row->toArray(), $categories->choices()),
        ]);
    }
}
