<?php

namespace App\Domain\Commands;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The stale-intent reports of ADR-009 section 3 (SCHEMA section 5.3,
 * `stale_intent_reports`): a command or a status check whose epoch is not
 * the current one is refused EPOCH_STALE, and the refusal records, if
 * absent, a report of the operation, intent, actor and epoch — the work list
 * of the restore reconciliation (TASK-037). The report is written at most
 * once, also under concurrent refusals: the unique key on (operation,
 * intent) makes a second insert do nothing (RACE-34).
 */
final class StaleIntentReports
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Records the report if absent, in the caller's transaction, and returns
     * whether this call wrote it.
     */
    public function reportIfAbsent(string $operation, string $sourceIntentId, int $actorId, string $reportedEpoch): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A stale-intent report is written in a transaction of its own (ADR-009 section 3).');
        }

        $written = DB::affectingStatement(<<<'SQL'
            insert into stale_intent_reports (operation, source_intent_id, actor_user_id, reported_epoch, first_reported_at)
            values (?, ?, ?, decode(?, 'hex'), ?)
            on conflict (operation, source_intent_id) do nothing
            SQL, [$operation, $sourceIntentId, $actorId, $reportedEpoch, $this->clock->now()->format('Y-m-d H:i:s.uP')]);

        return $written === 1;
    }

    /**
     * The epoch the intent's report recorded, as hexadecimal, if one exists.
     */
    public function epochOf(string $operation, string $sourceIntentId): ?string
    {
        $epoch = DB::scalar("select encode(reported_epoch, 'hex') from stale_intent_reports where operation = ? and source_intent_id = ?", [$operation, $sourceIntentId]);

        return is_string($epoch) ? $epoch : null;
    }

    /**
     * The actor of the intent's report, if one exists.
     */
    public function actorOf(string $operation, string $sourceIntentId): ?int
    {
        $actor = DB::table('stale_intent_reports')->where('operation', $operation)->where('source_intent_id', $sourceIntentId)->value('actor_user_id');

        return is_int($actor) ? $actor : null;
    }
}
