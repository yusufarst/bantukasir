<?php

namespace App\Http\Auth;

use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The rate buckets of the authentication endpoints (SECURITY_MODEL section
 * 11; ROUTE_CONTRACTS RT-09; TASK-006): `signin` for RA-01, `link` for RA-06
 * and `confirm` for RA-03. TASK-007 adds the other buckets. The counters are
 * those of the database cache store (ADR-002); the client address is the
 * request's, as the trusted proxy chain gives it (TASK-007). Buckets before
 * sign-in are keyed by address, never by user; the confirmation bucket by the
 * signed-in user.
 *
 * A throttled request runs nothing and answers THROTTLED with Retry-After
 * (429; the body of ROUTE_CONTRACTS RT-07, COPY_DECK `ux.state.throttled`),
 * and the security log records it (SEC-LOG-03, AC-006-02).
 */
final class Limits
{
    public static function register(): void
    {
        RateLimiter::for('signin', static fn (Request $request): array => [
            self::limit(5, 'signin:email:'.mb_strtolower(trim(self::text($request->input('email')))).'|'.$request->ip()),
            self::limit(10, 'signin:address:'.$request->ip()),
            self::limit(100, 'signin:installation'),
        ]);

        RateLimiter::for('link', static fn (Request $request): array => [
            self::limit(10, 'link:address:'.$request->ip()),
            self::limit(100, 'link:installation'),
        ]);

        RateLimiter::for('confirm', static fn (Request $request): Limit => self::limit(5, 'confirm:user:'.self::userKey($request)));
    }

    private static function limit(int $perMinute, string $key): Limit
    {
        return Limit::perMinute($perMinute)->by($key)->response(static function (Request $throttled, array $headers): JsonResponse {
            $user = $throttled->user();
            $actor = $user instanceof User ? SecurityActor::user($user->id) : SecurityActor::anonymous();

            app(SecurityLog::class)->record(SecurityEvent::Throttled, $actor, SecurityOutcome::Refused, $throttled);

            /** @var array<string, string|int> $headers */
            return new JsonResponse([
                'outcome' => 'REJECTED',
                'code' => 'THROTTLED',
                'messageKey' => 'ux.state.throttled',
                'params' => (object) [],
                'fields' => (object) [],
                'lines' => (object) [],
            ], 429, $headers);
        });
    }

    private static function userKey(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? (string) $user->id : 'none';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
