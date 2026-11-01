<?php

namespace Tests\Harness\Catalog;

use App\Domain\Catalog\Barcodes\InternalCodes;
use LogicException;

/**
 * The internal codes that a test chooses (EXECUTION_CONTEXT section 10, tests control
 * randomness), so that a clash can be proven: the codes in turn, the last repeating.
 * A test binds it with $this->app->instance(InternalCodes::class, ...).
 */
final class ScriptedInternalCodes extends InternalCodes
{
    public int $drawn = 0;

    /**
     * @param  list<string>  $codes
     */
    public function __construct(private readonly array $codes)
    {
        if ($codes === []) {
            throw new LogicException('A script holds at least one code.');
        }
    }

    public function next(): string
    {
        $code = $this->codes[min($this->drawn, count($this->codes) - 1)];
        $this->drawn++;

        return $code;
    }
}
