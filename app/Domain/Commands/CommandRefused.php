<?php

namespace App\Domain\Commands;

use InvalidArgumentException;
use RuntimeException;

/**
 * A definite refusal of a command, a status check or a read with one of the
 * error codes of TECHNICAL_RULES section 4 (ErrorCode): the transaction, if
 * any, rolls back and the answer is the error body of ROUTE_CONTRACTS RT-07.
 *
 * `params`, `fields` and `lines` carry only safe values (TR-ERR-02): `fields`
 * maps a path of the request to its message keys, each with its parameters;
 * `lines` maps a line index to safe values (INTERACTION_CONTRACTS section
 * 1.6). The message key is the code's, or one of its variants.
 */
final class CommandRefused extends RuntimeException
{
    public readonly string $messageKey;

    /**
     * @param  array<string, string|int>  $params
     * @param  array<string, list<array{key: string, params: array<string, string|int>}>>  $fields
     * @param  array<int, array<string, string|int>>  $lines
     */
    public function __construct(
        public readonly ErrorCode $error,
        public readonly array $params = [],
        public readonly array $fields = [],
        public readonly array $lines = [],
        ?string $messageKey = null,
    ) {
        $messageKey ??= $error->messageKey();

        if (! in_array($messageKey, $error->variants(), true)) {
            throw new InvalidArgumentException(sprintf('%s is not a message key of %s (COPY_DECK section 13.1).', $messageKey, $error->value));
        }

        $this->messageKey = $messageKey;

        parent::__construct(sprintf('Refused %s.', $error->value));
    }

    public static function of(ErrorCode $code): self
    {
        return new self($code);
    }
}
