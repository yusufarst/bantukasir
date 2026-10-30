<?php

namespace App\Http\Controllers\Configuration;

use App\Domain\Configuration\Accents;
use App\DTO\Configuration\RevisionData;
use App\Policies\NotFound;
use App\Queries\Configuration\ProfileRevisions;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-607, UXP-607 "Profil usaha", and RP-608, UXP-608 "Ubah profil usaha"
 * (ROUTE_CONTRACTS section 2; COPY_DECK sections 11.7 and 11.8; TASK-010
 * scope item 5), both CAP-30. UXP-607 shows the revision in force and the
 * earlier ones (BR-CFG-02); UXP-608 the form, with the revision in force as
 * its expected version and the accent set of DESIGN_SYSTEM section 3.3, whose
 * names are the keys of TASK-002. Before the bootstrap there is no revision:
 * the pages are not found.
 */
final class ProfileController
{
    public function show(ProfileRevisions $revisions): Response
    {
        $current = $revisions->current() ?? throw new NotFound;

        return Inertia::render('configuration/Profile', [
            'current' => $current->toArray(),
            'earlier' => array_map(static fn (RevisionData $revision): array => $revision->toArray(), $revisions->earlier()),
        ]);
    }

    public function edit(ProfileRevisions $revisions): Response
    {
        $current = $revisions->current() ?? throw new NotFound;

        return Inertia::render('configuration/ProfileEdit', [
            'current' => $current->toArray(),
            'accents' => Accents::TOKENS,
        ]);
    }
}
