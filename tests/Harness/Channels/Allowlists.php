<?php

namespace Tests\Harness\Channels;

/**
 * The allowlists of the sensitive channels, per role (SECURITY_MODEL
 * SEC-CH-01, SEC-CH-02, SEC-PR-03; suite AUTH02; TASK-007 scope item 3): the
 * paths of every field that each channel may carry to each kind of account.
 * A path names a field by its keys from the top, with `*` for every item of
 * a list or every key of a map. ChannelInspector compares what a response
 * really carries with these lists, exactly: a field the list does not name is
 * a leak, and a field it names that is missing is a defect of the test data.
 *
 * Every later Task adds the pages and JSON reads it serves, per role, and the
 * secrets that must never appear in them (EXECUTION_CONTEXT section 10).
 */
final class Allowlists
{
    /** The kinds of account of the matrix, and a visitor without a sign-in session. */
    public const array ACTORS = ['owner', 'ops', 'cashier', 'combined', 'guest'];

    /**
     * SEC-CH-02: the shared props of a page of a signed-in user, the same
     * fields for every role — its identity, display name, roles and capability
     * flags, and the limits of its sign-in session — beside Inertia's own
     * validation-error channel (SEC-CH-03) and the once prop of the dictionary
     * (ADR-005), which the inspector checks apart.
     */
    public const array SHARED_SIGNED_IN = [
        'auth',
        'auth.user',
        'auth.user.id',
        'auth.user.displayName',
        'auth.user.roles',
        'auth.user.roles.*',
        'auth.user.capabilities',
        'auth.user.capabilities.*',
        'auth.session',
        'auth.session.idleLimit',
        'auth.session.idleEndsIn',
        'auth.session.absoluteEndsAt',
        'auth.session.absoluteEndsIn',
        'errors',
    ];

    /** The shared props before sign-in: nobody's data. */
    public const array SHARED_GUEST = ['auth', 'errors'];

    /**
     * The page props of each Inertia page, per kind of account; `*` for every
     * kind that may open it.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array PAGES = [
        'access/SignIn' => ['guest' => ['notice']],
        'access/SetPassword' => ['guest' => ['passwordSet']],
        'access/Account' => ['*' => ['account', 'account.displayName', 'account.email', 'account.roles', 'account.roles.*', 'account.owner', 'passwordChanged']],
        'access/ChangePassword' => ['*' => []],
        'shell/Error' => ['*' => ['state', 'retryAfter'], 'guest' => ['state', 'retryAfter']],
    ];

    /**
     * The JSON bodies of the reads and answers that are not pages, per route
     * name and kind of account.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const array JSON = [
        'password.confirmation' => ['*' => ['confirmed']],
        // RD-04 with its rows (TASK-008): the actor's own, and with scope=all,
        // which only the Owner gets, every user's with the user.
        'data.requests' => ['*' => ['rows', 'rows.*', 'rows.*.operation', 'rows.*.family', 'rows.*.sourceIntentId', 'rows.*.idempotencyKey', 'rows.*.epoch', 'rows.*.receivedAt', 'rows.*.outcome', 'nextCursor', 'previousCursor']],
        'data.requests.all' => ['owner' => ['rows', 'rows.*', 'rows.*.operation', 'rows.*.family', 'rows.*.sourceIntentId', 'rows.*.idempotencyKey', 'rows.*.epoch', 'rows.*.receivedAt', 'rows.*.outcome', 'rows.*.user', 'rows.*.user.id', 'rows.*.user.displayName', 'nextCursor', 'previousCursor']],
        // RC-50 answered COMMITTED (TASK-008), and a command's answer.
        'commands.resolve' => ['*' => ['outcome', 'operation', 'commandId', 'result', 'result.type', 'result.id', 'result.location']],
        'commands.committed' => ['*' => ['outcome', 'operation', 'commandId', 'repeated', 'result', 'result.type', 'result.id', 'result.location']],
        'commands.refused' => ['*' => ['outcome', 'code', 'messageKey', 'params', 'fields', 'lines']],
    ];

    /**
     * The paths a page may carry for this kind of account, or null when the
     * page is not one this kind may open.
     *
     * @return list<string>|null
     */
    public static function page(string $component, string $actor): ?array
    {
        return self::pick(self::PAGES[$component] ?? null, $actor);
    }

    /**
     * @return list<string>|null
     */
    public static function json(string $route, string $actor): ?array
    {
        return self::pick(self::JSON[$route] ?? null, $actor);
    }

    /**
     * The list of this kind of account, or the list of every signed-in kind
     * (`*`), which a visitor without a sign-in session never gets.
     *
     * @param  array<string, list<string>>|null  $lists
     * @return list<string>|null
     */
    private static function pick(?array $lists, string $actor): ?array
    {
        if ($lists === null) {
            return null;
        }

        if (array_key_exists($actor, $lists)) {
            return $lists[$actor];
        }

        return $actor === 'guest' || ! array_key_exists('*', $lists) ? null : $lists['*'];
    }
}
