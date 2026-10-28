<?php

namespace App\Domain\Audit;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The writer of the business audit (DOMAIN_MODEL BR-AUD-01 to BR-AUD-06;
 * SECURITY_MODEL SEC-LOG-01, SEC-LOG-02; SCHEMA section 5.4, `audit_events`).
 * An event is written in the transaction of the change it records, so that it
 * commits or rolls back with it; the writer refuses to write outside a
 * transaction. Events are append-only: the runtime role may only add them
 * (SEC-LOG-04).
 *
 * `change_data` holds the safe change data that every reader in scope may see;
 * `owner_only_data` holds cost details, the reason of a cost correction and
 * anything else only the Owner reads (BR-AUD-02, D-047), and is kept apart: a
 * reader for staff never selects it. Neither holds a password, a link token, a
 * secret, a session identifier or a payload (BR-AUD-05): a key that names one
 * is refused. The actor's display name is the one frozen in the actor
 * (SCHEMA section 3.5, G-16).
 *
 * The columns command_id, product_id and cash_session_id are written once
 * their keys exist (EXECUTION_CONTEXT EXC-44): TASK-008 added command_id with
 * its key to command_identities — the identity of the enveloped command whose
 * transaction writes the event, null for an authentication endpoint or an
 * operator procedure (TR-CMD-07) — and TASK-015 and TASK-027 add the other two
 * with their keys.
 */
final class AuditLog
{
    /** Keys that would carry a secret into the audit (BR-AUD-05). */
    private const string FORBIDDEN_KEY = '/password|token|secret|cookie|payload|session_?id/i';

    public function __construct(private readonly Clock $clock) {}

    /**
     * Writes one event and returns its internal key.
     *
     * @param  array<string, mixed>  $changeData
     * @param  array<string, mixed>|null  $ownerOnlyData
     * @param  array{type: string, id: int}|null  $subject  the primary record
     */
    public function record(
        AuditCategory $category,
        string $action,
        Actor $actor,
        array $changeData = [],
        ?array $ownerOnlyData = null,
        ?array $subject = null,
        ?string $reference = null,
        ?string $reason = null,
        ?string $operatorNote = null,
        ?int $commandId = null,
    ): int {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An audit event is written in the transaction of the change it records (BR-AUD-01).');
        }

        if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $action) !== 1) {
            throw new InvalidArgumentException(sprintf('%s is not an audit action code (INTERACTION_CONTRACTS section 8).', $action));
        }

        if ($operatorNote !== null && $actor->kind !== 'OPERATOR_PROCEDURE') {
            throw new InvalidArgumentException('Only an operator procedure records an operator note (SECURITY_MODEL section 14).');
        }

        self::refuseSecrets($changeData);
        self::refuseSecrets($ownerOnlyData ?? []);

        $id = DB::table('audit_events')->insertGetId([
            'occurred_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
            'category' => $category->value,
            'action' => $action,
            'actor_kind' => $actor->kind,
            'actor_user_id' => $actor->userId,
            'actor_process' => $actor->process,
            'actor_display_name' => $actor->displayName,
            'operator_note' => $operatorNote,
            'command_id' => $commandId,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'reference' => $reference,
            'reason' => $reason,
            'change_data' => json_encode((object) $changeData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'owner_only_data' => $ownerOnlyData === null ? null : json_encode((object) $ownerOnlyData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $id;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function refuseSecrets(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::FORBIDDEN_KEY, $key) === 1) {
                throw new InvalidArgumentException(sprintf('The audit never holds a %s (BR-AUD-05).', $key));
            }

            if (is_array($value)) {
                self::refuseSecrets($value);
            }
        }
    }
}
