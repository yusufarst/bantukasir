<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Inventory\OnboardingState;
use App\Http\Requests\Inventory\IssuesPageRequest;
use App\Models\User;
use App\Policies\NotFound;
use App\Queries\Inventory\ManualIssueRecords;
use App\Queries\Inventory\Reversibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-407 to RP-409, the manual issue pages (ROUTE_CONTRACTS RP-407 to RP-409; BR-INV-18 to
 * BR-INV-22; D-018; CAP-13; TASK-023 scope item 4). RP-407 lists the confirmed issues and says
 * whether the onboarding freeze stops a new one (UXI-253, UXS-27); it is the Owner's explicit
 * "Barang keluar" entry, as it is every holder's of CAP-13 (D-018). RP-408 is an empty draft in
 * page memory: nothing is read from the server but whether the freeze is on. RP-409 shows one
 * issue with its lines and their movements, with no cost; to the Owner also whether each line's
 * MANUAL_ISSUE is reversed and whether the freeze is on, for "Batalkan mutasi" (UXI-266; CAP-17;
 * TASK-026).
 */
final class IssuesController
{
    public function index(IssuesPageRequest $request, ManualIssueRecords $issues, OnboardingState $onboarding): Response
    {
        $filters = $request->filters();

        return Inertia::render('inventory/Issues', [
            'filters' => ['from' => $filters['from'], 'to' => $filters['to'], 'reason' => $filters['reason']],
            'issues' => $issues->list($filters),
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
        ]);
    }

    public function create(OnboardingState $onboarding): Response
    {
        return Inertia::render('inventory/IssueCreate', [
            'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
        ]);
    }

    /**
     * @throws NotFound
     */
    public function show(Request $request, string $issue, ManualIssueRecords $issues, Reversibility $reversibility, OnboardingState $onboarding): Response
    {
        $read = $issues->detail($issue);
        $viewer = $request->user();

        // "Batalkan mutasi" per line, the Owner's (UXI-266; CAP-17; TASK-026).
        if ($viewer instanceof User && Capabilities::allows($viewer, Capability::Cap17)) {
            return Inertia::render('inventory/Issue', [
                ...$read,
                'lines' => $reversibility->withReversed($read['lines']),
                'postingFrozen' => $onboarding->current() === OnboardingState::ACTIVE,
            ]);
        }

        return Inertia::render('inventory/Issue', $read);
    }
}
