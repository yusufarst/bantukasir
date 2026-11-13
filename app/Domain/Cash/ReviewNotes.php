<?php

namespace App\Domain\Cash;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The Owner's review notes on CLOSED cash sessions, in the Cash module (SCHEMA section 5.10,
 * `cash_session_review_notes`; BR-CASH-19; D-069 (f)), its only writer (STK-12). A note is only ever
 * added: never edited or removed, a correction being a new note. Adding one changes no count, variance
 * or report. It opens no transaction of its own (TR-TX-01): the Action that adds a note holds the
 * session's share lock.
 */
final class ReviewNotes
{
    /** How many notes of one session a page reads at most, the newest kept (TECHNICAL_RULES section 12). */
    public const int BOUND = 200;

    private const string RECORDED_AT = "to_char(recorded_at at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') as recorded_at";

    /**
     * Adds a note to the CLOSED session whose share lock the caller holds: its internal key. The note's
     * key to the session's close refuses a session without one.
     */
    public function add(int $sessionId, string $note, int $actorId, string $actorDisplayName, string $at, int $commandId): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A review note is added inside the transaction of its Action (TR-TX-01).');
        }

        $id = DB::table('cash_session_review_notes')->insertGetId([
            'session_id' => $sessionId,
            'note' => $note,
            'recorded_at' => $at,
            'actor_user_id' => $actorId,
            'actor_display_name' => $actorDisplayName,
            'command_id' => $commandId,
        ]);

        return (int) $id;
    }

    /**
     * The session of a note, or null when no such note exists.
     */
    public function sessionOf(int $noteId): ?int
    {
        $session = DB::table('cash_session_review_notes')->where('id', $noteId)->value('session_id');

        return is_int($session) ? $session : null;
    }

    /**
     * The notes of a session in the order they were added, each with its text, its time in UTC and the
     * frozen name of its author (UXI-144): at most BOUND, the newest.
     *
     * @return list<array{id: int, text: string, recordedAt: string, author: string}>
     */
    public function of(int $sessionId): array
    {
        $rows = DB::table('cash_session_review_notes')
            ->where('session_id', $sessionId)
            ->select(['id', 'note', 'actor_display_name'])
            ->selectRaw(self::RECORDED_AT)
            ->orderByDesc('id')
            ->limit(self::BOUND)
            ->get()
            ->all();
        $notes = [];

        foreach (array_reverse($rows) as $row) {
            $values = (array) $row;
            $id = $values['id'] ?? null;
            $text = $values['note'] ?? null;
            $at = $values['recorded_at'] ?? null;
            $author = $values['actor_display_name'] ?? null;

            if (! is_int($id) || ! is_string($text) || ! is_string($at) || ! is_string($author)) {
                throw new LogicException('A review note holds its columns (SCHEMA section 5.10).');
            }

            $notes[] = ['id' => $id, 'text' => $text, 'recordedAt' => $at, 'author' => $author];
        }

        return $notes;
    }
}
