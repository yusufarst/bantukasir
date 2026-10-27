<?php

/*
|--------------------------------------------------------------------------
| Security baseline
|--------------------------------------------------------------------------
|
| The settings of SECURITY_MODEL section 12 that differ per environment
| (ENVIRONMENTS sections 10 and 11; TASK-007). .env.example holds their
| placeholders; the local environment of compose.yaml sets its own.
|
| - trusted_hosts: the host names the application answers (SEC-HDR-05); by
|   default the host of the application's address only.
| - trusted_proxies: the proxy hops in front of the application, addresses or
|   CIDR ranges (SEC-HDR-05, ADR-003 item 10); none by default, never every
|   address.
| - hsts: Strict-Transport-Security over HTTPS, turned on by the operator once
|   HTTPS is verified on the target; includeSubDomains only when every
|   subdomain is verified (SEC-HDR-02).
|
*/

$list = static fn (mixed $value): array => array_values(array_filter(array_map(trim(...), explode(',', is_string($value) ? $value : '')), static fn (string $item): bool => $item !== ''));

$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

return [
    'trusted_hosts' => $list(env('APP_TRUSTED_HOSTS')) ?: (is_string($applicationHost) && $applicationHost !== '' ? [$applicationHost] : []),

    'trusted_proxies' => $list(env('APP_TRUSTED_PROXIES')),

    'hsts' => [
        'enabled' => (bool) env('APP_HSTS', false),
        'subdomains' => (bool) env('APP_HSTS_SUBDOMAINS', false),
    ],
];
