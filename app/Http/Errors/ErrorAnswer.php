<?php

namespace App\Http\Errors;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The refusals that the application answers itself (TECHNICAL_RULES section 4;
 * ROUTE_CONTRACTS RT-07; UI_STATES section 3; TASK-007 scope items 5 and 9):
 * FORBIDDEN (403), NOT_FOUND (404), CSRF_FAILED (419), THROTTLED (429) and a
 * server error (500, 503). A request that changes something, or asks for JSON,
 * gets the error body of RT-07 — the code, its COPY_DECK message key and only
 * safe values; a page that is loaded or visited with GET gets the generic,
 * translated error page of its state (SEC-CH-10, TR-ERR-02): UXS-07, UXS-08,
 * UXS-10, UXS-12 or UXS-22. Neither ever holds internal detail, a trace or a
 * value of another scope. A server error has no code (UNCERTAIN, TR-ERR-01),
 * so its JSON body is empty.
 *
 * The error page frames itself in the shell for a signed-in user, whose
 * history it encrypts like every authenticated page (SEC-CH-04).
 */
final class ErrorAnswer
{
    /** The page's states, which the client maps to UXS-07, UXS-08, UXS-10, UXS-12 and UXS-22. */
    public const string FORBIDDEN = 'forbidden';

    public const string NOT_FOUND = 'not_found';

    public const string RELOAD = 'reload';

    public const string THROTTLED = 'throttled';

    public const string LOAD_FAILED = 'load_failed';

    /** The Inertia page of every error. */
    public const string PAGE = 'shell/Error';

    private const array CODES = [
        403 => ['FORBIDDEN', 'ux.state.forbidden', self::FORBIDDEN],
        404 => ['NOT_FOUND', 'ux.state.not_found', self::NOT_FOUND],
        419 => ['CSRF_FAILED', 'ux.state.reload', self::RELOAD],
        429 => ['THROTTLED', 'ux.state.throttled', self::THROTTLED],
    ];

    /**
     * The answer of this status for this request.
     *
     * @param  array<string, string|int>  $headers
     */
    public static function for(Request $request, int $status, array $headers = []): Response
    {
        $known = self::CODES[$status] ?? null;

        if (self::wantsBody($request)) {
            return $known === null
                ? new JsonResponse((object) [], $status, $headers)
                : self::body($status, $known[0], $known[1], $headers);
        }

        return self::page($request, $status, $known[2] ?? self::LOAD_FAILED, $headers);
    }

    /**
     * The error body of ROUTE_CONTRACTS RT-07.
     *
     * @param  array<string, string|int>  $headers
     */
    public static function body(int $status, string $code, string $messageKey, array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'outcome' => 'REJECTED',
            'code' => $code,
            'messageKey' => $messageKey,
            'params' => (object) [],
            'fields' => (object) [],
            'lines' => (object) [],
        ], $status, $headers);
    }

    /**
     * Whether the request gets the error body rather than the error page:
     * every request that is not a page loaded or visited with GET or HEAD,
     * the health route's, and every request that asks for JSON outside
     * Inertia's protocol.
     */
    public static function wantsBody(Request $request): bool
    {
        // The health route is read by machines and says nothing (RH-01).
        if ((! $request->isMethod('GET') && ! $request->isMethod('HEAD')) || $request->routeIs('health')) {
            return true;
        }

        return $request->expectsJson() && ! $request->header('X-Inertia');
    }

    /**
     * @param  array<string, string|int>  $headers
     */
    private static function page(Request $request, int $status, string $state, array $headers): Response
    {
        HandleInertiaRequests::prepare($request);

        if ($request->user() !== null) {
            Inertia::encryptHistory();
        }

        $retryAfter = $headers['Retry-After'] ?? null;
        $response = Inertia::render(self::PAGE, [
            'state' => $state,
            'retryAfter' => is_int($retryAfter) || (is_string($retryAfter) && ctype_digit($retryAfter)) ? (int) $retryAfter : null,
        ])->toResponse($request);

        $response->setStatusCode($status);

        foreach ($headers as $name => $value) {
            $response->headers->set($name, (string) $value);
        }

        return $response;
    }
}
