<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;

/**
 * Maintenance mode (RELEASE_PROCESS RB-20 step 7 and section 6; INTERACTION_CONTRACTS
 * section 2; TASK-014 scope item 5). A global middleware, so that it answers before
 * any route runs: a command leaves no receipt, no fact and no session, and its client
 * reads the 503 as UNCERTAIN and resolves it afterwards.
 *
 * The framework's bypass takes its secret from the file that `artisan down` writes;
 * this one takes it from the configuration of the application container, so that the
 * operator's way in for the checks of RB-21 needs nothing typed in the window. A
 * request for the secret's path gets the framework's signed cookie, valid for 12
 * hours, and the cookie passes this check only: every check of the route still
 * applies, so the bypass authorizes nothing. Unset, nobody passes.
 */
final class MaintenanceWindow extends PreventRequestsDuringMaintenance
{
    /**
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $secret = config('app.maintenance.bypass_secret');

        if (is_string($secret) && $secret !== '' && $this->app->maintenanceMode()->active() && ! $this->inExceptArray($request)) {
            if (hash_equals($secret, $request->path())) {
                return $this->bypassResponse($secret);
            }

            if ($this->hasValidBypassCookie($request, ['secret' => $secret])) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
