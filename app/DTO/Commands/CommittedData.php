<?php

namespace App\DTO\Commands;

use App\Actions\Commands\CommandOutcome;
use App\Actions\Commands\Resolution;

/**
 * The answers of the command platform built from allowlisted fields only
 * (INTERACTION_CONTRACTS sections 1.2 and 2.1; SEC-CH-11): a committed
 * command, now (201) or repeated (200), and the answer of a status check. A
 * result names its type, internal key, document number when it has one, and
 * the path that shows it — nothing else. A one-time link appears only in the
 * answer that committed it (SEC-ACC-05): its address, with the token in the
 * fragment, and its expiry.
 */
final class CommittedData
{
    /**
     * @return array<string, mixed>
     */
    public static function command(CommandOutcome $outcome): array
    {
        $answer = [
            'outcome' => 'COMMITTED',
            'operation' => $outcome->operation,
            'commandId' => $outcome->commandId,
            'repeated' => $outcome->repeated,
            'result' => $outcome->result === null ? null : self::result($outcome->result),
        ];

        if ($outcome->link !== null) {
            $answer['link'] = ['url' => $outcome->link['url'], 'expiresAt' => $outcome->link['expiresAt']];
        }

        return $answer;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolution(Resolution $resolution): array
    {
        $answer = ['outcome' => $resolution->outcome, 'operation' => $resolution->operation];

        if ($resolution->outcome === Resolution::COMMITTED) {
            $answer['commandId'] = $resolution->commandId;
            $answer['result'] = $resolution->result === null ? null : self::result($resolution->result);
        }

        return $answer;
    }

    /**
     * A print request of a receipt also says whether it is a copy (RC-44; DC-01; TASK-031).
     *
     * @param  array{type: string, id: int|null, number?: string, copy?: bool, location: string}  $result
     * @return array{type: string, id: int|null, number?: string, copy?: bool, location: string}
     */
    private static function result(array $result): array
    {
        $allowed = ['type' => $result['type'], 'id' => $result['id']];

        if (isset($result['number'])) {
            $allowed['number'] = $result['number'];
        }

        if (isset($result['copy'])) {
            $allowed['copy'] = $result['copy'];
        }

        $allowed['location'] = $result['location'];

        return $allowed;
    }
}
