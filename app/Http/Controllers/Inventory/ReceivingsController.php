<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Role;
use App\Domain\Inventory\OnboardingState;
use App\DTO\Catalog\CategoryRowData;
use App\Http\Requests\Inventory\ReceivingsPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Catalog\CategoryRows;
use App\Queries\Inventory\ReceivingDocuments;
use App\Queries\Inventory\Reversibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-404 to RP-406, the receiving pages (ROUTE_CONTRACTS RP-404 to RP-406; KS-09; BR-INV-12 to
 * BR-INV-17; D-033, D-047, D-069 (a); CAP-11, CAP-12; TASK-022 scope item 1). RP-404 lists the
 * confirmed documents and says whether the onboarding freeze stops a new one (UXI-231, UXS-27).
 * RP-405 is an empty draft in page memory: nothing is read from the server but whether the
 * freeze is on, whether an explicit zero cost is the viewer's to enter (the Owner's alone,
 * BR-COST-04) and the categories that "Tambah produk" over the page offers (UXI-235, NC-11);
 * every holder of CAP-11 holds CAP-04. RP-406 shows one document with its purchase cost, which
 * every holder of CAP-11 may see (CAP-12), and a zero's evidence or reason only to the Owner; to
 * the Owner also whether each line's RECEIPT is reversed and whether the onboarding freeze is on, for
 * "Batalkan mutasi" (UXI-250; CAP-17; TASK-026).
 */
final class ReceivingsController
{
    public function index(ReceivingsPageRequest $request, ReceivingDocuments $documents, OnboardingState $onboarding): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/Receivings', [
            'filters' => ['from' => $filters['from'], 'to' => $filters['to'], 'q' => $filters['q']],
            'receivings' => $documents->list($filters),
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
        ]);
    }

    public function create(Request $request, OnboardingState $onboarding, CategoryRows $categories): Response
    {
        return Inertia::render('inventory/ReceivingCreate', [
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            'zeroAllowed' => self::isOwner($request->user()),
            'categories' => array_map(static fn (CategoryRowData $row): array => $row->toArray(), $categories->choices()),
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(Request $request, string $document, ReceivingDocuments $documents, Reversibility $reversibility, OnboardingState $onboarding): Response
    {
        // A zero's evidence or reason is cost evidence, CAP-20's, as on UXP-403 (review finding R-10).
        $viewer = $request->user();
        $read = $documents->detail($document, $viewer instanceof User && Capabilities::allows($viewer, Capability::Cap20));

        // "Batalkan mutasi" per line, the Owner's (UXI-250; CAP-17; TASK-026).
        if ($viewer instanceof User && Capabilities::allows($viewer, Capability::Cap17)) {
            return Inertia::render('inventory/Receiving', [
                ...$read,
                'lines' => $reversibility->withReversed($read['lines']),
                'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            ]);
        }

        return Inertia::render('inventory/Receiving', $read);
    }

    private static function isOwner(mixed $viewer): bool
    {
        return $viewer instanceof User && in_array(Role::SuperAdmin, $viewer->roles(), true);
    }
}
