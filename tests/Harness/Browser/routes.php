<?php

use Illuminate\Support\Facades\Route;
use Tests\Harness\Browser\FixturesController;

// The fixtures of the browser tests (TASK-006), registered by routes/web.php
// only in the local and testing environments and only where the test harness
// exists; not routes of ROUTE_CONTRACTS.
Route::post('/testing/browser/reset', [FixturesController::class, 'reset'])->name('testing.browser.reset');
Route::post('/testing/browser/link', [FixturesController::class, 'link'])->name('testing.browser.link');
