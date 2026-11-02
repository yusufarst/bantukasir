<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use RuntimeException;

/**
 * A refusal of the whole file (DOCUMENT_CONTRACTS DC-04; INTERACTION_CONTRACTS RR-01):
 * its message keys, each with its parameters. RR-01 and RC-31 answer it as CSV_INVALID
 * at the path of their request.
 */
final class CsvFileRefused extends RuntimeException
{
    /**
     * @param  list<array{key: string, params: array<string, string|int>}>  $entries
     */
    public function __construct(public readonly array $entries)
    {
        parent::__construct('The file is refused.');
    }

    /**
     * @param  array<string, string|int>  $params
     */
    public static function of(string $key, array $params = []): self
    {
        return new self([['key' => $key, 'params' => $params]]);
    }

    public function asRefusal(string $path): CommandRefused
    {
        return new CommandRefused(ErrorCode::CsvInvalid, fields: [$path => $this->entries]);
    }
}
