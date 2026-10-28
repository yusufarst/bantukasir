<?php

namespace App\Domain\Commands;

use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The identities, fences and durable results of commands (ADR-007 sections
 * 2 to 4; SCHEMA section 5.3, `command_identities` and `command_results`).
 *
 * The identity is the first write of a command's transaction (TECHNICAL_RULES
 * section 7.1 step 1): a second request with the same identity waits on the
 * unique key until the first transaction ends, then fails on the conflict if
 * the first committed (SQLSTATE 23505), which the caller answers after the
 * rollback by reading the original (TR-TX-08). A FENCE occupies (operation,
 * intent) for a resolution that found nothing (ADR-007 section 4). A COMMAND
 * identity cannot commit without its result, which its deferred presence key
 * requires.
 */
final class CommandIdentities
{
    public const string COMMAND = 'COMMAND';

    public const string FENCE = 'FENCE';

    public function __construct(private readonly Clock $clock) {}

    /**
     * Inserts the identity of a command by a user, in its transaction.
     */
    public function insertCommand(Envelope $envelope, int $actorId, string $fingerprint, string $receivedAt): int
    {
        self::requireTransaction();

        if (preg_match('/^[0-9a-f]{64}$/', $fingerprint) !== 1) {
            throw new InvalidArgumentException('A fingerprint is SHA-256, 64 hexadecimal digits.');
        }

        $id = DB::scalar(<<<'SQL'
            insert into command_identities (kind, operation, operation_version, source_intent_id, idempotency_key,
                actor_kind, actor_user_id, actor_process, fingerprint, recovery_epoch, received_at, recorded_at)
            values ('COMMAND', ?, ?, ?, ?, 'USER', ?, null, decode(?, 'hex'), decode(?, 'hex'), ?, ?)
            returning id
            SQL, [$envelope->operation, $envelope->version, $envelope->sourceIntentId, $envelope->idempotencyKey,
            $actorId, $fingerprint, $envelope->recoveryEpoch, $receivedAt, $this->now()]);

        return self::id($id);
    }

    /**
     * Inserts the FENCE of a resolution in the current epoch, with the user
     * who checked as its actor (ADR-007 section 4), in its transaction.
     */
    public function insertFence(string $operation, string $sourceIntentId, int $checkerId, string $currentEpoch, string $receivedAt): int
    {
        self::requireTransaction();

        $id = DB::scalar(<<<'SQL'
            insert into command_identities (kind, operation, operation_version, source_intent_id, idempotency_key,
                actor_kind, actor_user_id, actor_process, fingerprint, recovery_epoch, received_at, recorded_at)
            values ('FENCE', ?, null, ?, null, 'USER', ?, null, null, decode(?, 'hex'), ?, ?)
            returning id
            SQL, [$operation, $sourceIntentId, $checkerId, $currentEpoch, $receivedAt, $this->now()]);

        return self::id($id);
    }

    /**
     * The durable result of a COMMAND identity, written in its transaction
     * after its facts (ADR-007 section 2).
     */
    public function recordResult(int $commandId, StoredResult $result): void
    {
        self::requireTransaction();

        foreach ($result->detail as $key => $value) {
            if (preg_match('/^[a-z][a-zA-Z0-9]{0,63}$/', $key) !== 1) {
                throw new InvalidArgumentException(sprintf('%s is no reference name of a result.', $key));
            }
        }

        DB::table('command_results')->insert([
            'command_id' => $commandId,
            'identity_kind' => self::COMMAND,
            'result_type' => $result->type,
            'result_ref' => $result->ref,
            'result_detail' => json_encode((object) $result->detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'committed_at' => $this->now(),
        ]);
    }

    public function byKey(int $actorId, string $operation, string $idempotencyKey): ?StoredIdentity
    {
        return self::identity(DB::selectOne(
            self::SELECT." where kind = 'COMMAND' and actor_kind = 'USER' and actor_user_id = ? and operation = ? and idempotency_key = ?",
            [$actorId, $operation, $idempotencyKey],
        ));
    }

    public function byIntent(string $operation, string $sourceIntentId): ?StoredIdentity
    {
        return self::identity(DB::selectOne(self::SELECT.' where operation = ? and source_intent_id = ?', [$operation, $sourceIntentId]));
    }

    public function resultOf(int $commandId): StoredResult
    {
        $row = DB::table('command_results')->where('command_id', $commandId)->first(['result_type', 'result_ref']);

        if ($row === null) {
            throw new LogicException(sprintf('The committed identity %d has no result.', $commandId));
        }

        $values = (array) $row;
        $references = [];

        // The detail's references as texts, read by the database, so that no
        // number of it passes a floating-point decoder (ADR-006 item 5).
        foreach (DB::select('select d.key, d.value #>> \'{}\' as value, jsonb_typeof(d.value) as kind from command_results r, jsonb_each(r.result_detail) d where r.command_id = ?', [$commandId]) as $reference) {
            $key = is_string($reference->key ?? null) ? $reference->key : '';
            $value = is_string($reference->value ?? null) ? $reference->value : null;
            $references[$key] = match ($reference->kind ?? null) {
                'number' => is_string($value) && preg_match('/^-?[0-9]{1,18}$/', $value) === 1 ? (int) $value : $value,
                'string' => $value,
                default => null,
            };
        }

        return new StoredResult(
            is_string($values['result_type']) ? $values['result_type'] : '',
            is_int($values['result_ref']) ? $values['result_ref'] : null,
            $references,
        );
    }

    private const string SELECT = <<<'SQL'
        select id, kind, operation, source_intent_id::text as source_intent_id, idempotency_key::text as idempotency_key,
               actor_user_id, encode(fingerprint, 'hex') as fingerprint, encode(recovery_epoch, 'hex') as recovery_epoch
        from command_identities
        SQL;

    private static function identity(mixed $row): ?StoredIdentity
    {
        if ($row === null) {
            return null;
        }

        $values = (array) $row;

        return new StoredIdentity(
            self::id($values['id'] ?? null),
            self::text($values['kind'] ?? null),
            self::text($values['operation'] ?? null),
            self::text($values['source_intent_id'] ?? null),
            is_string($values['idempotency_key'] ?? null) ? $values['idempotency_key'] : null,
            is_int($values['actor_user_id'] ?? null) ? $values['actor_user_id'] : null,
            is_string($values['fingerprint'] ?? null) ? $values['fingerprint'] : null,
            self::text($values['recovery_epoch'] ?? null),
        );
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.uP');
    }

    private static function id(mixed $value): int
    {
        if (! is_int($value)) {
            throw new LogicException('The database returned no identity key.');
        }

        return $value;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : throw new LogicException('An identity column is missing.');
    }

    private static function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An identity and its result are written in the command\'s transaction (ADR-007 section 2).');
        }
    }
}
