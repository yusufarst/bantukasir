<?php

namespace App\Http\Middleware;

use App\Support\Validation\TrimmedInput;
use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;
use Illuminate\Http\Request;

/**
 * Laravel's global trimming of input, which also records the input paths whose
 * values it changed (TASK-003 finding A-01, reading R-26). Text fields keep
 * their trimming; the canonical-number rules refuse a value at a recorded
 * path, so that a padded number is refused, never repaired into a canonical
 * one (ADR-006 item 2). It replaces the framework's TrimStrings in the global
 * middleware (bootstrap/app.php), for query strings, JSON, urlencoded and
 * multipart bodies alike.
 */
class TrimStrings extends Middleware
{
    /**
     * The paths whose values the request being cleaned had trimmed.
     *
     * @var list<string>
     */
    private array $trimmed = [];

    /**
     * @param  Request  $request
     */
    protected function clean($request): void
    {
        $this->trimmed = [];

        parent::clean($request);

        TrimmedInput::record($request, $this->trimmed);
    }

    /**
     * @param  string  $key
     */
    protected function transform($key, $value): mixed
    {
        $transformed = parent::transform($key, $value);

        if ($transformed !== $value) {
            $this->trimmed[] = (string) $key;
        }

        return $transformed;
    }
}
