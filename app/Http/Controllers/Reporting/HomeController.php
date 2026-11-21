<?php

namespace App\Http\Controllers\Reporting;

use App\Models\User;
use App\Queries\Reporting\OwnerHome;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * RP-007, UXP-007 "Beranda" (ROUTE_CONTRACTS RP-007; KEY_SCREENS KS-02; NAVIGATION_CONTRACTS UXP-007; UI_STATES
 * UXS-22; CAP-22, the Owner's alone; TASK-041 scope item 1): the Owner's home, whose three panels — today, the open
 * cash sessions and the attention items — are deferred props, each in a group of its own, so that each is read by
 * a request of its own after the page and fails on its own. A panel whose read fails is null, which the page shows
 * as UXS-22 with "Coba lagi", never zero, while the other panels stand; its cause goes to the log, never to the page.
 * The page takes no parameter.
 */
final class HomeController
{
    /** The deferred props, each its own group, read again by name with "Coba lagi". */
    public const string TODAY = 'today';

    public const string OPEN_SESSIONS = 'openSessions';

    public const string ATTENTION = 'attention';

    public function show(Request $request, OwnerHome $home): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return Inertia::render('reporting/Home', [
            self::TODAY => Inertia::defer(static fn (): ?array => self::guarded(static fn (): array => $home->today()), self::TODAY),
            self::OPEN_SESSIONS => Inertia::defer(static fn (): ?array => self::guarded(static fn (): array => $home->openSessions()), self::OPEN_SESSIONS),
            self::ATTENTION => Inertia::defer(static fn (): ?array => self::guarded(static fn (): array => $home->attention($user)), self::ATTENTION),
        ]);
    }

    /**
     * One panel's read, or null when it fails.
     *
     * @param  callable(): array<array-key, mixed>  $read
     * @return array<array-key, mixed>|null
     */
    private static function guarded(callable $read): ?array
    {
        try {
            return $read();
        } catch (Throwable $failure) {
            report($failure);

            return null;
        }
    }
}
