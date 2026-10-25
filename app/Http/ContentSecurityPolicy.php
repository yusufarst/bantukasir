<?php

namespace App\Http;

/**
 * The baseline content security policy of SECURITY_MODEL SEC-HDR-03, with the
 * per-request nonce that the generated script and style tags carry. TASK-005
 * proves the design system's components under it on the component specimen;
 * TASK-007 applies it to every response (its scope item 5).
 */
final class ContentSecurityPolicy
{
    /**
     * The header value for one request's nonce. No unsafe-inline and no
     * unsafe-eval; fonts and every other resource come from the application's
     * own origin.
     */
    public static function header(string $nonce): string
    {
        if (preg_match('/^[A-Za-z0-9+\/=_-]{16,}$/', $nonce) !== 1) {
            throw new \InvalidArgumentException('A CSP nonce is a random token of at least 16 base64 characters.');
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
