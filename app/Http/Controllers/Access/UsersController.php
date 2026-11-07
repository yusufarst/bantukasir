<?php

namespace App\Http\Controllers\Access;

use App\DTO\Access\UserRowData;
use App\Http\Requests\Access\UsersListRequest;
use App\Models\User;
use App\Queries\Access\UserAccounts;
use App\Queries\Cash\OpenSessions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RP-602 to RP-606, the Owner's user pages (ROUTE_CONTRACTS section 2; COPY_DECK
 * sections 11.2 to 11.6; TASK-011 scope item 4). UXP-606 receives only whose
 * link it is for: the link itself reaches the page from the answer of RC-35 or
 * RC-41, in page memory, and is never in its props, a flash, the session, the
 * URL or history state (SEC-ACC-05).
 */
final class UsersController
{
    public function index(UsersListRequest $request, UserAccounts $accounts, OpenSessions $sessions): Response
    {
        $status = $request->status();
        $role = $request->role();

        return Inertia::render('access/Users', [
            // The mark of an OPEN cash session, from the Cash module (UXI-334; TASK-027).
            'users' => array_map(static fn (UserRowData $row): array => $row->toArray(), $accounts->list($status, $role, $sessions->holders())),
            'filters' => ['status' => $status, 'role' => $role],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('access/UserCreate');
    }

    public function show(Request $request, string $user, UserAccounts $accounts): Response
    {
        return Inertia::render('access/User', ['user' => $accounts->detail($user, self::viewer($request))->toArray()]);
    }

    public function edit(Request $request, string $user, UserAccounts $accounts): Response
    {
        return Inertia::render('access/UserEdit', ['user' => $accounts->editable($user, self::viewer($request))->forEdit()]);
    }

    public function link(Request $request, string $user, UserAccounts $accounts): Response
    {
        $account = $accounts->detail($user, self::viewer($request));

        return Inertia::render('access/LinkHandover', ['user' => ['id' => $account->id, 'displayName' => $account->displayName]]);
    }

    private static function viewer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
