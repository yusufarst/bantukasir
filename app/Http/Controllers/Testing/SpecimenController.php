<?php

namespace App\Http\Controllers\Testing;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The component specimen of the design system (TASK-005 scope item 8): every
 * component of DESIGN_SYSTEM section 8 and the state presentations, with
 * synthetic data, for the browser tests and their accessibility, width,
 * zoom, contrast and CSP checks; and the specimen of the application shell
 * (TASK-007), which frames a page with synthetic destinations of every role,
 * on the side navigation, the icon rail of the till and the compact top bar,
 * because no destination of the navigation exists yet. Both exist only in the
 * local and testing environments — routes/web.php registers them nowhere
 * else — and are not routes of ROUTE_CONTRACTS: they read and write nothing
 * and show no business data. Their answers carry the content security policy
 * of SEC-HDR-03, as every answer does (App\Http\Middleware\SecurityHeaders).
 */
final class SpecimenController
{
    /** The environments whose route table holds the specimen; never staging or production. */
    public const ENVIRONMENTS = ['local', 'testing'];

    public static function enabledIn(string $environment): bool
    {
        return in_array($environment, self::ENVIRONMENTS, true);
    }

    public function __invoke(): Response
    {
        return Inertia::render('Testing/Specimen');
    }

    public function shell(): Response
    {
        return Inertia::render('Testing/ShellSpecimen');
    }

    /**
     * The command client's specimen (TASK-009 scope item 8): a signed-in page
     * in the shell whose one command is the probe operation of the test
     * harness, so that the browser suite proves the client's states, panels
     * and block on the real pipeline (TEST_STRATEGY TS-22, TS-23).
     */
    public function command(): Response
    {
        return Inertia::render('Testing/CommandSpecimen');
    }
}
