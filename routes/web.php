<?php

use App\Domain\Commands\Operations;
use App\Http\Controllers\Access\AccountController;
use App\Http\Controllers\Access\AnonymizeAccountController;
use App\Http\Controllers\Access\CreateStaffAccountController;
use App\Http\Controllers\Access\DeactivateAccountController;
use App\Http\Controllers\Access\HomeRedirectController;
use App\Http\Controllers\Access\IssuePasswordLinkController;
use App\Http\Controllers\Access\OwnPasswordController;
use App\Http\Controllers\Access\PasswordLinkController;
use App\Http\Controllers\Access\ReactivateAccountController;
use App\Http\Controllers\Access\RevokePasswordLinkController;
use App\Http\Controllers\Access\SessionActivityController;
use App\Http\Controllers\Access\SettingsController;
use App\Http\Controllers\Access\ShellDataController;
use App\Http\Controllers\Access\SignInController;
use App\Http\Controllers\Access\UpdateOwnerDetailsController;
use App\Http\Controllers\Access\UpdateStaffAccountController;
use App\Http\Controllers\Access\UsersController;
use App\Http\Controllers\Catalog\AddBarcodeController;
use App\Http\Controllers\Catalog\BarcodeCorrectionController;
use App\Http\Controllers\Catalog\CategoriesController;
use App\Http\Controllers\Catalog\ConfirmCsvImportController;
use App\Http\Controllers\Catalog\CorrectBarcodeMisassignmentController;
use App\Http\Controllers\Catalog\CreateCategoryController;
use App\Http\Controllers\Catalog\CreateProductController;
use App\Http\Controllers\Catalog\DeactivateCategoryController;
use App\Http\Controllers\Catalog\DeactivateProductController;
use App\Http\Controllers\Catalog\GenerateBarcodeController;
use App\Http\Controllers\Catalog\ImportCancelController;
use App\Http\Controllers\Catalog\ImportController;
use App\Http\Controllers\Catalog\ImportPreviewController;
use App\Http\Controllers\Catalog\LabelsController;
use App\Http\Controllers\Catalog\ProductsController;
use App\Http\Controllers\Catalog\ReactivateCategoryController;
use App\Http\Controllers\Catalog\ReactivateProductController;
use App\Http\Controllers\Catalog\RemoveProductPhotoController;
use App\Http\Controllers\Catalog\RenameCategoryController;
use App\Http\Controllers\Catalog\RetireBarcodeController;
use App\Http\Controllers\Catalog\SearchController;
use App\Http\Controllers\Catalog\SearchFirstController;
use App\Http\Controllers\Catalog\SetProductPhotoController;
use App\Http\Controllers\Catalog\TokenLookupController;
use App\Http\Controllers\Catalog\UpdateProductController;
use App\Http\Controllers\Commands\RequestsController;
use App\Http\Controllers\Commands\RequestsDataController;
use App\Http\Controllers\Commands\ResolveIntentController;
use App\Http\Controllers\Configuration\ChangeBusinessProfileController;
use App\Http\Controllers\Configuration\ProfileController;
use App\Http\Controllers\Documents\LabelPrintController;
use App\Http\Controllers\Documents\RecordLabelPrintController;
use App\Http\Controllers\Inventory\ConfirmManualIssueController;
use App\Http\Controllers\Inventory\ConfirmReceivingController;
use App\Http\Controllers\Inventory\HistoryController;
use App\Http\Controllers\Inventory\IssuesController;
use App\Http\Controllers\Inventory\MovementsController;
use App\Http\Controllers\Inventory\ReceivingsController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockDataController;
use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Testing\SpecimenController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;

// Web routes. Every route of ROUTE_CONTRACTS is built by the Task that
// TRACEABILITY section 7 names, with its gate (RT-05) and the middleware of
// RT-04; tests/Feature/Access/RouteInventoryTest.php lists them all, and every
// Task adds its own there. RH-01, the health route, is registered outside the
// web group (bootstrap/app.php).

// The pre-authentication routes (RP-001, RA-01, RP-002, RA-06; TASK-006): no
// gate, guest only, the bucket of every request before sign-in (TASK-007) and
// their own buckets. RA-01 to RA-04 are Fortify's controllers and RA-05 the
// application's on Fortify's updater (ADR-004, config/fortify.php).
Route::middleware(['guest', 'throttle:presignin'])->group(function (): void {
    Route::get('/login', [SignInController::class, 'show'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:signin')->name('login.store');
});

// The link page sends no referrer, also when a signed-in visitor is sent on
// (review finding R-10): `no-referrer` wraps `guest`.
Route::middleware(['no-referrer', 'guest', 'throttle:presignin'])->group(function (): void {
    Route::get('/set-password', [PasswordLinkController::class, 'show'])->name('password.link');
    Route::post('/set-password', [PasswordLinkController::class, 'store'])->middleware('throttle:link')->name('password.link.store');
});

// Every signed-in route: authentication, the active account and the limits of
// the sign-in session, the bucket of every signed-in request, its capability
// gate, answers never stored, and its history entries encrypted (RT-04 to
// RT-06; SECURITY_MODEL SEC-AZ-01, SEC-CH-04; TASK-007). CSRF is the web
// group's, ordered after the sign-in session (bootstrap/app.php). RA-05 is the
// application's controller on Fortify's updater, which leaves a one-time link
// alone (OwnPasswordController; review finding R-02); RA-03 takes only its
// allowlisted field (ConfirmationInput; review finding R-04); RA-02 and RA-07
// take no field at all (input.allow).
Route::middleware(['auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt'])->group(function (): void {
    // CAP-01: sign in and out, view one's own account, change one's own
    // password — every role (SECURITY_MODEL section 6). RN-01 and the sign-in
    // session's own endpoints are "Signed in" in ROUTE_CONTRACTS, which every
    // role is through CAP-01.
    Route::middleware('capability:CAP-01')->group(function (): void {
        Route::get('/', HomeRedirectController::class)->name('home.redirect');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware(['input.allow', 'signout.record'])->name('logout');
        Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware(['throttle:confirm', 'confirm.input'])->name('password.confirm.store');
        Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])->name('password.confirmation');
        Route::put('/user/password', [OwnPasswordController::class, 'update'])->name('user-password.update');
        Route::post('/session/activity', SessionActivityController::class)->middleware('input.allow')->name('session.activity');

        Route::get('/account', [AccountController::class, 'show'])->name('account.show');
        Route::get('/account/password', [AccountController::class, 'password'])->name('account.password');

        // RD-03, the shell's indicators (TASK-009): a background read that
        // takes no parameter (RT-08, RT-10).
        Route::get('/data/shell', ShellDataController::class)->middleware('input.allow')->name('data.shell');
    });

    // CAP-34: the status check of an uncertain intent, RC-50, in the bucket
    // of every command (RT-09), and the unresolved request receipts, RD-04
    // (TASK-008); the page of unresolved requests, RP-006, built in the
    // browser from the stored references and RD-04 (TASK-009). Who may check
    // which intent, and scope=all for the Owner only, are checked by the
    // Action and the FormRequest.
    Route::middleware('capability:CAP-34')->group(function (): void {
        Route::post('/commands/resolve', ResolveIntentController::class)->middleware('throttle:command')->name('commands.resolve');
        Route::get('/data/command-requests', RequestsDataController::class)->name('data.requests');
        Route::get('/requests', [RequestsController::class, 'index'])->middleware('input.allow')->name('requests.index');
    });

    // The Owner's settings (TASK-010): RP-601, the index, for any of the
    // capabilities of its entries; RP-607 and RP-608, the BusinessProfile, and
    // RC-32, its change, with recent authentication, in the bucket of every
    // command and, with a logo, the bucket `logo` (App\Http\Auth\Limits).
    Route::get('/settings', [SettingsController::class, 'index'])->middleware(['capability:CAP-25,CAP-30,CAP-31,CAP-32', 'input.allow'])->name('settings.index');

    Route::middleware('capability:CAP-30')->group(function (): void {
        Route::get('/settings/business-profile', [ProfileController::class, 'show'])->middleware('input.allow')->name('profile.show');
        Route::get('/settings/business-profile/edit', [ProfileController::class, 'edit'])->middleware('input.allow')->name('profile.edit');
        Route::post('/commands/business_profile.change', ChangeBusinessProfileController::class)
            ->middleware(['throttle:command', 'throttle:logo', 'password.confirm'])
            ->name('commands.business_profile.change');
    });

    // User management (TASK-011): RP-005 and RP-602 to RP-606, and RC-35 to
    // RC-42, each with its capabilities of ROUTE_CONTRACTS, in the bucket of
    // every command, with recent authentication; RC-35 and RC-41 also in the
    // bucket `link_issue`. No route reaches CAP-33.
    Route::get('/account/details', [AccountController::class, 'details'])->middleware(['capability:CAP-27', 'input.allow'])->name('account.details');
    Route::get('/settings/users', [UsersController::class, 'index'])->middleware(['capability:CAP-25', 'input.allow:status,role'])->name('users.index');
    Route::get('/settings/users/new', [UsersController::class, 'create'])->middleware(['capability:CAP-25', 'input.allow'])->name('users.create');
    Route::get('/settings/users/{user}', [UsersController::class, 'show'])->middleware(['capability:CAP-27,CAP-28', 'input.allow:from_page'])->name('users.show');
    Route::get('/settings/users/{user}/edit', [UsersController::class, 'edit'])->middleware(['capability:CAP-27', 'input.allow'])->name('users.edit');
    Route::get('/settings/users/{user}/link', [UsersController::class, 'link'])->middleware(['capability:CAP-25,CAP-26', 'input.allow'])->name('users.link');

    Route::post('/commands/user.create', CreateStaffAccountController::class)->middleware(['capability:CAP-25', 'throttle:command', 'throttle:link_issue', 'password.confirm'])->name('commands.user.create');
    Route::post('/commands/user.update', UpdateStaffAccountController::class)->middleware(['capability:CAP-27', 'throttle:command', 'password.confirm'])->name('commands.user.update');
    Route::post('/commands/owner.update_details', UpdateOwnerDetailsController::class)->middleware(['capability:CAP-27', 'throttle:command', 'password.confirm'])->name('commands.owner.update_details');
    Route::post('/commands/user.deactivate', DeactivateAccountController::class)->middleware(['capability:CAP-28', 'throttle:command', 'password.confirm'])->name('commands.user.deactivate');
    Route::post('/commands/user.reactivate', ReactivateAccountController::class)->middleware(['capability:CAP-28', 'throttle:command', 'password.confirm'])->name('commands.user.reactivate');
    Route::post('/commands/user.anonymize', AnonymizeAccountController::class)->middleware(['capability:CAP-29', 'throttle:command', 'password.confirm'])->name('commands.user.anonymize');
    Route::post('/commands/user.issue_link', IssuePasswordLinkController::class)->middleware(['capability:CAP-25,CAP-26', 'throttle:command', 'throttle:link_issue', 'password.confirm'])->name('commands.user.issue_link');
    Route::post('/commands/user.revoke_link', RevokePasswordLinkController::class)->middleware(['capability:CAP-26', 'throttle:command', 'password.confirm'])->name('commands.user.revoke_link');

    // The catalogue (TASK-015): RD-01, the shared Product Search, for CAP-02 and
    // then the capability of its context, which its FormRequest checks; RD-06,
    // the search-first check; and RC-17 to RC-24, in the bucket of every command.
    // A price change needs CAP-05 too, which its Action checks. CAP-02 is
    // restricted for a cashier, which opens no gate: TASK-028 adds what the till
    // needs to this route.
    Route::get('/data/products/search', SearchController::class)->middleware(['capability:CAP-02', 'throttle:search'])->name('data.products.search');

    // The CSV import (TASK-019): RP-308 before RP-302, whose path would take it; RR-01
    // in the bucket `csv`; RR-02, which takes only its path; and RC-31.
    Route::middleware('capability:CAP-10')->group(function (): void {
        Route::get('/products/import', [ImportController::class, 'show'])->middleware('input.allow')->name('products.import');
        Route::post('/products/import/preview', ImportPreviewController::class)->middleware('throttle:csv')->name('products.import.preview');
        Route::post('/products/import/{import}/cancel', ImportCancelController::class)->middleware('input.allow')->name('products.import.cancel');
        Route::post('/commands/csv_import.confirm', ConfirmCsvImportController::class)->middleware('throttle:command')->name('commands.csv_import.confirm');
    });

    Route::middleware('capability:CAP-04')->group(function (): void {
        Route::get('/data/products/check', SearchFirstController::class)->middleware('throttle:search')->name('data.products.check');
        Route::post('/commands/product.create', CreateProductController::class)->middleware('throttle:command')->name('commands.product.create');
        Route::post('/commands/product.update', UpdateProductController::class)->middleware('throttle:command')->name('commands.product.update');
        Route::post('/commands/product.deactivate', DeactivateProductController::class)->middleware('throttle:command')->name('commands.product.deactivate');
        Route::post('/commands/product.reactivate', ReactivateProductController::class)->middleware('throttle:command')->name('commands.product.reactivate');
        Route::post('/commands/category.create', CreateCategoryController::class)->middleware('throttle:command')->name('commands.category.create');
        Route::post('/commands/category.rename', RenameCategoryController::class)->middleware('throttle:command')->name('commands.category.rename');
        Route::post('/commands/category.deactivate', DeactivateCategoryController::class)->middleware('throttle:command')->name('commands.category.deactivate');
        Route::post('/commands/category.reactivate', ReactivateCategoryController::class)->middleware('throttle:command')->name('commands.category.reactivate');

        // The catalogue pages (TASK-017): RP-301 to RP-304 and RP-307, each taking
        // only the parameters of its row of ROUTE_CONTRACTS.
        Route::get('/products', [ProductsController::class, 'index'])->middleware('input.allow:q,category,brand,status,page,import')->name('products.index');
        Route::get('/products/new', [ProductsController::class, 'create'])->middleware('input.allow:code')->name('products.create');
        Route::get('/products/{product}', [ProductsController::class, 'show'])->middleware('input.allow')->name('products.show');
        Route::get('/products/{product}/edit', [ProductsController::class, 'edit'])->middleware('input.allow')->name('products.edit');
        Route::get('/categories', [CategoriesController::class, 'index'])->middleware('input.allow:status')->name('categories.index');
    });

    // The barcodes (TASK-016): RC-27 to RC-29, in the bucket of every command. RC-17's
    // barcode needs CAP-06 too, which its Action checks.
    Route::middleware('capability:CAP-06')->group(function (): void {
        Route::post('/commands/barcode.add', AddBarcodeController::class)->middleware('throttle:command')->name('commands.barcode.add');
        Route::post('/commands/barcode.generate', GenerateBarcodeController::class)->middleware('throttle:command')->name('commands.barcode.generate');
        Route::post('/commands/barcode.retire', RetireBarcodeController::class)->middleware('throttle:command')->name('commands.barcode.retire');
    });

    // The misassignment correction (TASK-020), the Owner's alone: RP-305; RD-05 in the
    // bucket `search`; RC-30 in the bucket of every command, with recent authentication.
    Route::middleware('capability:CAP-07')->group(function (): void {
        Route::get('/products/{product}/barcode-correction', [BarcodeCorrectionController::class, 'create'])->middleware('input.allow')->name('products.barcode_correction');
        Route::get('/data/barcode-tokens/lookup', TokenLookupController::class)->middleware('throttle:search')->name('data.barcodes.lookup');
        Route::post('/commands/barcode.correct_misassignment', CorrectBarcodeMisassignmentController::class)->middleware(['throttle:command', 'password.confirm'])->name('commands.barcode.correct_misassignment');
    });

    // The product photo (TASK-018): RC-25, with its upload in the bucket `photo`, and
    // RC-26, in the bucket of every command.
    Route::middleware('capability:CAP-09')->group(function (): void {
        Route::post('/commands/product_photo.set', SetProductPhotoController::class)->middleware(['throttle:command', 'throttle:photo'])->name('commands.product_photo.set');
        Route::post('/commands/product_photo.remove', RemoveProductPhotoController::class)->middleware('throttle:command')->name('commands.product_photo.remove');
    });

    // The barcode labels (TASK-018): RP-306, RC-47 in the bucket `print`, and the print
    // view RP-306P, a read that takes only its path (TR-HTTP-02).
    Route::middleware('capability:CAP-08')->group(function (): void {
        Route::get('/products/{product}/labels', [LabelsController::class, 'create'])->middleware('input.allow')->name('products.labels');
        Route::get('/products/{product}/labels/print/{printRequest}', [LabelPrintController::class, 'show'])->middleware('input.allow')->name('products.labels.print');
        Route::post('/commands/document.print_labels', RecordLabelPrintController::class)->middleware(['throttle:command', 'throttle:print'])->name('commands.document.print_labels');
    });

    // The stock ledger (TASK-021): RC-07, in the bucket of every command.
    Route::post('/commands/receiving.confirm', ConfirmReceivingController::class)->middleware(['capability:CAP-11', 'throttle:command'])->name('commands.receiving.confirm');

    // The stock pages (TASK-022): RP-401 to RP-403 and RD-02 for CAP-14, which a cashier holds
    // only at checkout, a restricted cell that opens no gate; each page takes only the
    // parameters of its row of ROUTE_CONTRACTS, and RD-02 refuses any other in its FormRequest.
    Route::middleware('capability:CAP-14')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->middleware('input.allow:q,state,category,after,perPage')->name('stock.index');
        Route::get('/stock/history', [HistoryController::class, 'index'])->middleware('input.allow:product,type,from,to,after,before,perPage,from_page')->name('stock.history');
        Route::get('/stock/movements/{movement}', [MovementsController::class, 'show'])->middleware('input.allow')->name('stock.movement');
        Route::get('/data/stock', StockDataController::class)->name('data.stock');
    });

    // The receiving pages (TASK-022): RP-404 to RP-406 for CAP-11; RP-405 before RP-406, whose
    // path would take it.
    Route::middleware('capability:CAP-11')->group(function (): void {
        Route::get('/stock/receivings', [ReceivingsController::class, 'index'])->middleware('input.allow:from,to,q,after,before,perPage')->name('receivings.index');
        Route::get('/stock/receivings/new', [ReceivingsController::class, 'create'])->middleware('input.allow')->name('receivings.create');
        Route::get('/stock/receivings/{document}', [ReceivingsController::class, 'show'])->middleware('input.allow')->name('receivings.show');
    });

    // The manual issue (TASK-023): RC-08 in the bucket of every command, and RP-407 to RP-409
    // for CAP-13; RP-408 before RP-409, whose path would take it.
    Route::post('/commands/manual_issue.confirm', ConfirmManualIssueController::class)->middleware(['capability:CAP-13', 'throttle:command'])->name('commands.manual_issue.confirm');

    Route::middleware('capability:CAP-13')->group(function (): void {
        Route::get('/stock/issues', [IssuesController::class, 'index'])->middleware('input.allow:from,to,reason,after,before,perPage')->name('issues.index');
        Route::get('/stock/issues/new', [IssuesController::class, 'create'])->middleware('input.allow')->name('issues.create');
        Route::get('/stock/issues/{issue}', [IssuesController::class, 'show'])->middleware('input.allow')->name('issues.show');
    });
});

// RD-07, a stored image (TASK-010): signed in, with the middleware of every
// signed-in route but the answer's caching, which it chooses itself — private,
// for a day, immutable (INTERACTION_CONTRACTS section 3.7; SEC-CH-06). Every
// role may see a logo (CAP-01); a product photo needs CAP-03, which the
// controller checks.
Route::middleware(['auth', 'signin.session', 'throttle:authenticated', 'history.encrypt', 'capability:CAP-01', 'input.allow:variant'])
    ->get('/media/{file}', MediaController::class)
    ->name('media.show');

// The probe operation of the command platform (TASK-008 scope item 9): only
// in the testing environment and, for the browser suite, the local one
// (TASK-009), and only where the test harness exists, which the release
// build does not contain.
if (app()->environment(Operations::PROBE_ENVIRONMENTS) && is_file(base_path('tests/Harness/Commands/routes.php'))) {
    require base_path('tests/Harness/Commands/routes.php');
}

// The design system's component specimen and the shell's, for the browser
// tests (TASK-005, TASK-007): only in the local and testing environments,
// never in staging or production, and not routes of ROUTE_CONTRACTS.
if (SpecimenController::enabledIn((string) app()->environment())) {
    Route::get('/testing/specimen', SpecimenController::class)->name('testing.specimen');
    Route::get('/testing/shell-specimen', [SpecimenController::class, 'shell'])->name('testing.shell_specimen');
    Route::get('/testing/command-specimen', [SpecimenController::class, 'command'])->middleware(['auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt', 'capability:CAP-01'])->name('testing.command_specimen');

    // The fixtures of the browser tests, from the test harness (TASK-006): only
    // where the harness exists, which the release build does not contain.
    if (is_file(base_path('tests/Harness/Browser/routes.php'))) {
        require base_path('tests/Harness/Browser/routes.php');
    }
}
