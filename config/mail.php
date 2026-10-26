<?php

/*
|--------------------------------------------------------------------------
| Mail (ADR-004 item 2; SECURITY_MODEL SEC-AUTH-01; D-049; TASK-006)
|--------------------------------------------------------------------------
|
| The application never sends email: the sign-in email is never verified,
| one-time links are handed over in person, and there is no password reset by
| email. No mail transport is configured in any environment. The framework
| merges its own mailers into this file's (Illuminate\Foundation\Bootstrap\
| LoadConfiguration), so each of them is named here without a configuration,
| which the mail manager reads as "not defined"; the default names none
| either. Any attempt to send therefore fails before it reaches a network, and
| tests/Feature/Access/RouteInventoryTest.php fails if a mailer gains a
| configuration, also one that a later framework version adds.
|
*/

return [

    'default' => 'none',

    'mailers' => [
        'smtp' => null,
        'ses' => null,
        'postmark' => null,
        'resend' => null,
        'sendmail' => null,
        'log' => null,
        'array' => null,
        'failover' => null,
        'roundrobin' => null,
    ],

    'from' => [
        'address' => null,
        'name' => null,
    ],

];
