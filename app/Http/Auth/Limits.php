<?php

namespace App\Http\Auth;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Http\Errors\ErrorAnswer;
use App\Models\User;
use App\Policies\ObjectScope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every rate bucket of SECURITY_MODEL section 11 (ROUTE_CONTRACTS RT-09):
 * `signin` for RA-01, `link` for RA-06 and `confirm` for RA-03 (TASK-006);
 * and `presignin`, the bucket of every request before sign-in — the sign-in
 * and link pages and their submissions, and the health route — with
 * `authenticated`, the bucket of every signed-in request, and the buckets
 * that the routes of later Tasks name: `search`, `command`, `photo`, `logo`,
 * `csv`, `print` and `link_issue` (TASK-007). The counters are those of the
 * database cache store (ADR-002); the client address is the request's, as
 * only the trusted proxy hops give it (App\Http\Middleware\TrustProxies).
 * Buckets before sign-in are keyed by address, never by user; a signed-in
 * request's buckets by its user, so that the shop's shared address never
 * turns a person's limit into the whole shop's; `link_issue` by the account
 * the new link is for.
 *
 * A throttled request runs nothing and answers THROTTLED with Retry-After —
 * 429 with the error body of ROUTE_CONTRACTS RT-07, or the page of UXS-12
 * for a page loaded with GET (App\Http\Errors\ErrorAnswer) — and the security
 * log records it (SEC-LOG-03).
 */
final class Limits
{
    /**
     * The field of a command's business payload that names the account a new
     * one-time link is for (RC-41 `userId`, INTERACTION_CONTRACTS section 5;
     * the envelope of ADR-007 section 1, whose JSON names TASK-008 fixes).
     */
    public const string LINK_ACCOUNT_FIELD = 'payload.userId';

    public static function register(): void
    {
        RateLimiter::for('signin', static fn (Request $request): array => [
            self::perMinute(5, 'signin:email:'.mb_strtolower(trim(self::text($request->input('email')))).'|'.$request->ip()),
            self::perMinute(10, 'signin:address:'.$request->ip()),
            self::perMinute(100, 'signin:installation'),
        ]);

        RateLimiter::for('link', static fn (Request $request): array => [
            self::perMinute(10, 'link:address:'.$request->ip()),
            self::perMinute(100, 'link:installation'),
        ]);

        RateLimiter::for('confirm', static fn (Request $request): Limit => self::perMinute(5, 'confirm:user:'.self::userKey($request)));

        RateLimiter::for('presignin', static fn (Request $request): Limit => self::perMinute(120, 'presignin:address:'.$request->ip()));
        RateLimiter::for('authenticated', static fn (Request $request): Limit => self::perMinute(600, 'authenticated:user:'.self::userKey($request)));
        RateLimiter::for('search', static fn (Request $request): Limit => self::perMinute(300, 'search:user:'.self::userKey($request)));
        RateLimiter::for('command', static fn (Request $request): Limit => self::perMinute(60, 'command:user:'.self::userKey($request)));
        RateLimiter::for('photo', static fn (Request $request): Limit => self::perHour(200, 'photo:user:'.self::userKey($request)));
        // A command counts in the bucket of its upload only when it carries
        // one: RC-32 without a new logo is in the bucket `command` alone
        // (ROUTE_CONTRACTS section 6, "bucket `logo` with a logo"; TASK-010).
        RateLimiter::for('logo', static fn (Request $request): Limit => $request->is('commands/*') && ! $request->hasFile('payload.logo')
            ? Limit::none()
            : self::perHour(20, 'logo:user:'.self::userKey($request)));
        RateLimiter::for('csv', static fn (Request $request): Limit => self::perHour(30, 'csv:user:'.self::userKey($request)));
        RateLimiter::for('print', static fn (Request $request): Limit => self::perMinute(60, 'print:user:'.self::userKey($request)));

        // One new link per account per 60 seconds, counted by the account's
        // key, so that one account never has two counters ("7" and "07"); a
        // request that names no account as a key (RC-35 creates it) has
        // nothing to count here, and the broker's own throttle stays the
        // check inside the transaction (OneTimeLinks::recentlyIssued).
        RateLimiter::for('link_issue', static function (Request $request): Limit {
            $account = ObjectScope::key($request->input(self::LINK_ACCOUNT_FIELD));

            if ($account === null) {
                return Limit::none();
            }

            return self::respond(Limit::perSecond(1, 60)->by('link_issue:account:'.$account));
        });
    }

    private static function perMinute(int $attempts, string $key): Limit
    {
        return self::respond(Limit::perMinute($attempts)->by($key));
    }

    private static function perHour(int $attempts, string $key): Limit
    {
        return self::respond(Limit::perHour($attempts)->by($key));
    }

    private static function respond(Limit $limit): Limit
    {
        return $limit->response(static function (Request $throttled, array $headers): Response {
            $user = $throttled->user();
            $actor = $user instanceof User ? SecurityActor::user($user->id) : SecurityActor::anonymous();

            app(SecurityLog::class)->record(SecurityEvent::Throttled, $actor, SecurityOutcome::Refused, $throttled);

            /** @var array<string, string|int> $headers */
            return ErrorAnswer::for($throttled, 429, $headers);
        });
    }

    /**
     * A signed-in request's bucket key: its user; a request without one — which
     * the routes of these buckets never let through — counts by its address.
     */
    private static function userKey(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : 'address:'.$request->ip();
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
