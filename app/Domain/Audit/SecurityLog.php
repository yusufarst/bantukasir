<?php

namespace App\Domain\Audit;

use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The writer of the security log (SECURITY_MODEL SEC-LOG-03; SCHEMA section
 * 5.4, `security_log_entries`). It is separate from the business audit and is
 * written by the request after its business transaction has ended, committed
 * or rolled back, never inside it (TR-TX-02): the writer refuses while a
 * transaction is open, so that no rollback can erase an entry. Each entry is
 * also written as one JSON line to the container's output (logging channel
 * `security`, ENVIRONMENTS section 14).
 *
 * An entry holds the event, the actor, the account it concerns when known,
 * the request's identifier and route name, the outcome code and the client
 * address — the request's address as the trusted proxy chain gives it
 * (ADR-003 item 10; TASK-007 configures the trusted proxies). It never holds
 * a typed identifier, a password, a token, a cookie, a session identifier, a
 * user agent or a payload (SEC-CH-08). Only the Owner reads it (CAP-32).
 */
final class SecurityLog
{
    /** SEC-LOG-05: 365 days as 8,760 hours, the bound of the database's policy, which no time zone shortens. */
    public const int RETENTION_HOURS = 8760;

    public function __construct(private readonly Clock $clock) {}

    public function record(
        SecurityEvent $event,
        SecurityActor $actor,
        SecurityOutcome $outcome,
        Request $request,
        ?int $subjectUserId = null,
    ): void {
        $route = $request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;
        $address = filter_var($request->ip(), FILTER_VALIDATE_IP);

        $this->write([
            'event_type' => $event->value,
            'actor_kind' => $actor->kind,
            'actor_user_id' => $actor->userId,
            'actor_process' => $actor->process,
            'subject_user_id' => $subjectUserId,
            'request_id' => RequestId::of($request),
            'route_name' => is_string($routeName) && $routeName !== '' ? mb_substr($routeName, 0, 200) : null,
            'outcome_code' => $outcome->value,
            'client_address' => is_string($address) ? $address : null,
        ]);
    }

    /**
     * An entry without a request: an operator procedure or a scheduled
     * command names its run, and records no client address.
     */
    public function recordRun(SecurityEvent $event, SecurityActor $actor, SecurityOutcome $outcome, string $runId, ?int $subjectUserId = null): void
    {
        $this->write([
            'event_type' => $event->value,
            'actor_kind' => $actor->kind,
            'actor_user_id' => $actor->userId,
            'actor_process' => $actor->process,
            'subject_user_id' => $subjectUserId,
            'request_id' => mb_substr($runId, 0, 64),
            'route_name' => null,
            'outcome_code' => $outcome->value,
            'client_address' => null,
        ]);
    }

    /**
     * Removes at most $limit entries older than the retention at $now, oldest
     * first, in a transaction of its own, and returns how many it removed
     * (SEC-LOG-05; SCHEMA section 5.4; D-088; SC-02, TASK-038). The database
     * holds the same bound by its own clock: the policy
     * security_log_entries_retention admits the DELETE of an entry only when
     * it is older than 8,760 hours by the database's now(), so an entry
     * younger by either clock stays (OPEN_ITEMS T-23). Nothing else deletes
     * an entry, and nothing changes one.
     */
    public function removeExpired(CarbonImmutable $now, int $limit): int
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('The housekeeping of the security log runs in a transaction of its own.');
        }

        $olderThan = $now->utc()->subHours(self::RETENTION_HOURS)->format('Y-m-d H:i:s.uP');

        return DB::transaction(static fn (): int => DB::delete(<<<'SQL'
            delete from security_log_entries
            where id in (
                select id from security_log_entries
                where occurred_at < ?
                order by occurred_at, id
                limit ?
            )
            and occurred_at < ?
            SQL, [$olderThan, max(1, $limit), $olderThan]));
    }

    /**
     * @param  array{event_type: string, actor_kind: string, actor_user_id: int|null, actor_process: string|null, subject_user_id: int|null, request_id: string, route_name: string|null, outcome_code: string, client_address: string|null}  $entry
     */
    private function write(array $entry): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('The security log is written after the business transaction has ended, never inside it (SEC-LOG-03, TR-TX-02).');
        }

        $entry = ['occurred_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'), ...$entry];

        Log::channel('security')->info('security_log', $entry);
        DB::table('security_log_entries')->insert($entry);
    }
}
