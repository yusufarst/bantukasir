<?php

/*
 * The route of the probe operation (TASK-008 scope item 9), only in the
 * testing environment (routes/web.php): the middleware of every signed-in
 * command route (ROUTE_CONTRACTS RT-04) with the gate of CAP-04 and the
 * bucket `command`, then the pipeline of the command platform.
 */

use Illuminate\Support\Facades\Route;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Commands\ProbeController;

Probe::register();

Route::middleware(['auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt', 'capability:CAP-04', 'throttle:command'])
    ->post(Probe::PATH, ProbeController::class)
    ->name('testing.probe');
