<?php

namespace App\Http\Controllers\Testing;

use App\Http\ContentSecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The component specimen of the design system (TASK-005 scope item 8): every
 * component of DESIGN_SYSTEM section 8 and the state presentations, with
 * synthetic data, for the browser tests and their accessibility, width,
 * zoom, contrast and CSP checks. It exists only in the local and testing
 * environments — routes/web.php registers it nowhere else — and is not a
 * route of ROUTE_CONTRACTS: it reads and writes nothing and shows no business
 * data. Its response carries the content security policy of SEC-HDR-03.
 */
final class SpecimenController
{
    /** The environments whose route table holds the specimen; never staging or production. */
    public const ENVIRONMENTS = ['local', 'testing'];

    public static function enabledIn(string $environment): bool
    {
        return in_array($environment, self::ENVIRONMENTS, true);
    }

    public function __invoke(Request $request): Response
    {
        $nonce = Vite::useCspNonce();
        $response = Inertia::render('Testing/Specimen')->toResponse($request);
        $response->headers->set('Content-Security-Policy', ContentSecurityPolicy::header($nonce));

        return $response;
    }
}
