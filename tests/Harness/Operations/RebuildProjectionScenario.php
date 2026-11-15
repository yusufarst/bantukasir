<?php

namespace Tests\Harness\Operations;

use App\Actions\Operations\RebuildProjection;
use Tests\Harness\Race\Scenario;

/**
 * OP-07's projection rebuild of one subject (SECURITY_MODEL SEC-OP-07; TASK-036), in a worker process:
 * whether a projection changed. A refusal ends the worker with its failure.
 */
final class RebuildProjectionScenario implements Scenario
{
    /**
     * @param  array<string, mixed>  $arguments  subject, id
     * @return array<string, mixed>
     */
    public function run(array $arguments): array
    {
        $subject = $arguments['subject'] ?? null;
        $id = $arguments['id'] ?? null;

        return ['changed' => app(RebuildProjection::class)(is_string($subject) ? $subject : '', is_int($id) ? $id : 0, 'Uji balapan')];
    }
}
