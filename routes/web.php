<?php

use App\Http\Controllers\Testing\SpecimenController;
use Illuminate\Support\Facades\Route;

// Web routes. TASK-001 adds none: every route of ROUTE_CONTRACTS is built by the
// Task that TRACEABILITY section 8 names; RN-01 and RH-01 by TASK-007.

// The design system's component specimen, for the browser tests (TASK-005):
// only in the local and testing environments, never in staging or production,
// and not a route of ROUTE_CONTRACTS.
if (SpecimenController::enabledIn((string) app()->environment())) {
    Route::get('/testing/specimen', SpecimenController::class)->name('testing.specimen');
}
