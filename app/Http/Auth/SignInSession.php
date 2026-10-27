<?php

namespace App\Http\Auth;

use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;

/**
 * The two times of a sign-in session that its limits are counted from
 * (SECURITY_MODEL SEC-SESS-02; ADR-004 item 8), kept in the encrypted session
 * payload (SCHEMA section 5.1): the sign-in time and the time of the last
 * user-initiated request, both Unix seconds of the application clock (UTC).
 * A sign-in session ends 30 minutes after its last user-initiated request and
 * 12 hours after its sign-in at most (App\Http\Middleware\EnsureSignInSession).
 */
final class SignInSession
{
    public const int IDLE_SECONDS = 30 * 60;

    public const int ABSOLUTE_SECONDS = 12 * 60 * 60;

    private const string SIGNED_IN_AT = 'access.signed_in_at';

    private const string ACTIVE_AT = 'access.active_at';

    /** The flash of the sign-in page's note after a sign-in session ended (UXS-09). */
    public const string NOTICE = 'access.notice';

    /** The value of that flash, a state code that the page turns into its note, never text. */
    public const string SIGNED_OUT = 'signed_out';

    public function __construct(private readonly Clock $clock) {}

    /** Called at sign-in, after the session has been regenerated (SEC-AUTH-10). */
    public function start(Session $session): void
    {
        $now = $this->clock->now()->getTimestamp();

        $session->put(self::SIGNED_IN_AT, $now);
        $session->put(self::ACTIVE_AT, $now);
    }

    /**
     * Whether the sign-in session has passed either limit, or holds no times
     * of its own: a session that no sign-in of this application started is
     * never trusted.
     */
    public function hasEnded(Session $session): bool
    {
        $signedInAt = $session->get(self::SIGNED_IN_AT);
        $activeAt = $session->get(self::ACTIVE_AT);

        if (! is_int($signedInAt) || ! is_int($activeAt)) {
            return true;
        }

        $now = $this->clock->now()->getTimestamp();

        return $now - $signedInAt >= self::ABSOLUTE_SECONDS || $now - $activeAt >= self::IDLE_SECONDS;
    }

    /** A user-initiated request: the idle limit counts from now. */
    public function touch(Session $session): void
    {
        $session->put(self::ACTIVE_AT, $this->clock->now()->getTimestamp());
    }

    /**
     * What the shell's timers mirror (SEC-SESS-02, SEC-CH-02; TASK-007): the
     * seconds left until the idle limit and until the absolute limit, counted
     * now, and the instant of the absolute end, which the shell shows as a
     * time of day (UXA-21). Null for a session without its times, which is
     * never trusted (hasEnded).
     *
     * @return array{idleEndsIn: int, absoluteEndsIn: int, absoluteEndsAt: CarbonImmutable}|null
     */
    public function remaining(Session $session): ?array
    {
        $signedInAt = $session->get(self::SIGNED_IN_AT);
        $activeAt = $session->get(self::ACTIVE_AT);

        if (! is_int($signedInAt) || ! is_int($activeAt)) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();

        return [
            'idleEndsIn' => max(0, $activeAt + self::IDLE_SECONDS - $now),
            'absoluteEndsIn' => max(0, $signedInAt + self::ABSOLUTE_SECONDS - $now),
            'absoluteEndsAt' => CarbonImmutable::createFromTimestampUTC($signedInAt + self::ABSOLUTE_SECONDS),
        ];
    }
}
