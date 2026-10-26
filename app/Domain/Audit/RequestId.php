<?php

namespace App\Domain\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The identifier of a request, which the security log and the log lines name
 * (SECURITY_MODEL SEC-CH-08; SCHEMA section 5.4, `request_id`): a random UUID
 * made by the application the first time it is asked for, and kept on the
 * request. A client's own identifier is never trusted.
 */
final class RequestId
{
    private const string ATTRIBUTE = 'bantukasir.request_id';

    public static function of(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set(self::ATTRIBUTE, $id);
        }

        return $id;
    }
}
