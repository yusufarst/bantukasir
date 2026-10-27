<?php

namespace App\DTO;

/**
 * The base of every DTO that reaches the browser (SECURITY_MODEL SEC-CH-01;
 * STK-21; TASK-007 scope item 3): an allowlist of named fields for one role
 * and one object, built by a Query, never from a model. Inertia page props,
 * shared props and JSON bodies are built only from these; an Eloquent model is
 * never serialized, and its hidden attributes are not a privacy boundary. The
 * payload-inspection harness of tests/Harness/Channels/ checks every channel
 * against the allowlists, per role; each Task adds the DTOs of what it serves.
 */
interface PageData
{
    /**
     * The fields, by name, as the browser receives them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
