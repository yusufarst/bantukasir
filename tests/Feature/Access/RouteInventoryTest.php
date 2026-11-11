<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Access\OwnPasswordController;
use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\Harness\Http\RouteInventory;
use Tests\TestCase;

/**
 * The route inventory (AC-007-01, AC-006-01; ROUTE_CONTRACTS RT-01, RT-04,
 * RT-05, section 8; SECURITY_MODEL SEC-AZ-01, SEC-AUTH-01, SEC-AUTH-07;
 * TECHNICAL_RULES TR-HTTP-02, TR-HTTP-04; suites AUTH01, SEC01, LINK01; THR-01,
 * THR-02, THR-03; TASK-006, TASK-007). Every route is listed here with its
 * route of ROUTE_CONTRACTS, its gate and the middleware it names; the rules of
 * Tests\Harness\Http\RouteInventory hold for every one of them — a route of V1
 * only, no DELETE, a gate on every signed-in route, RT-04's order — and the
 * check finds an ungated route. Fortify exposes exactly the features of
 * ADR-004 item 1, and no mail transport exists. Every later Task adds its
 * routes to ROUTES.
 */
final class RouteInventoryTest extends TestCase
{
    /** The middleware every signed-in route names (RT-04 to RT-06; TASK-007). */
    private const array SIGNED_IN = ['web', 'auth', 'signin.session', 'throttle:authenticated', 'cache.headers:no_store;private', 'history.encrypt'];

    /**
     * Every route: its name, its route of ROUTE_CONTRACTS, its gate — `guest`
     * before sign-in, `none` for the health route, otherwise the capabilities
     * of its `capability:` middleware — and the middleware it names.
     *
     * @var array<string, array{string, string, string, list<string>}>
     */
    private const array ROUTES = [
        'GET /' => ['home.redirect', 'RN-01', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /account' => ['account.show', 'RP-003', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /account/password' => ['account.password', 'RP-004', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'GET /login' => ['login', 'RP-001', 'guest', ['web', 'guest', 'throttle:presignin']],
        'GET /set-password' => ['password.link', 'RP-002', 'guest', ['web', 'no-referrer', 'guest', 'throttle:presignin']],
        'GET /up' => ['health', 'RH-01', 'none', ['throttle:presignin']],
        'GET /user/confirmed-password-status' => ['password.confirmation', 'RA-04', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        'POST /login' => ['login.store', 'RA-01', 'guest', ['web', 'guest', 'throttle:presignin', 'throttle:signin']],
        'POST /logout' => ['logout', 'RA-02', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow', 'signout.record']],
        'POST /session/activity' => ['session.activity', 'RA-07', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow']],
        'POST /set-password' => ['password.link.store', 'RA-06', 'guest', ['web', 'no-referrer', 'guest', 'throttle:presignin', 'throttle:link']],
        'POST /user/confirm-password' => ['password.confirm.store', 'RA-03', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'throttle:confirm', 'confirm.input']],
        'PUT /user/password' => ['user-password.update', 'RA-05', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01']],
        // TASK-008: the status check and the unresolved request receipts.
        'POST /commands/resolve' => ['commands.resolve', 'RC-50', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34', 'throttle:command']],
        'GET /data/command-requests' => ['data.requests', 'RD-04', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34']],
        // TASK-009: the shell's indicators and the page of unresolved requests.
        'GET /data/shell' => ['data.shell', 'RD-03', 'CAP-01', [...self::SIGNED_IN, 'capability:CAP-01', 'input.allow']],
        'GET /requests' => ['requests.index', 'RP-006', 'CAP-34', [...self::SIGNED_IN, 'capability:CAP-34', 'input.allow']],
        // TASK-010: the settings index, the BusinessProfile pages and RC-32,
        // and RD-07, which chooses its own caching.
        'GET /settings' => ['settings.index', 'RP-601', 'CAP-25,CAP-30,CAP-31,CAP-32', [...self::SIGNED_IN, 'capability:CAP-25,CAP-30,CAP-31,CAP-32', 'input.allow']],
        'GET /settings/business-profile' => ['profile.show', 'RP-607', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'input.allow']],
        'GET /settings/business-profile/edit' => ['profile.edit', 'RP-608', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'input.allow']],
        'POST /commands/business_profile.change' => ['commands.business_profile.change', 'RC-32', 'CAP-30', [...self::SIGNED_IN, 'capability:CAP-30', 'throttle:command', 'throttle:logo', 'password.confirm']],
        'GET /media/{file}' => ['media.show', 'RD-07', 'CAP-01', ['web', 'auth', 'signin.session', 'throttle:authenticated', 'history.encrypt', 'capability:CAP-01', 'input.allow:variant']],
        // TASK-011: the user pages and RC-35 to RC-42.
        'GET /account/details' => ['account.details', 'RP-005', 'CAP-27', [...self::SIGNED_IN, 'capability:CAP-27', 'input.allow']],
        'GET /settings/users' => ['users.index', 'RP-602', 'CAP-25', [...self::SIGNED_IN, 'capability:CAP-25', 'input.allow:status,role']],
        'GET /settings/users/new' => ['users.create', 'RP-603', 'CAP-25', [...self::SIGNED_IN, 'capability:CAP-25', 'input.allow']],
        'GET /settings/users/{user}' => ['users.show', 'RP-604', 'CAP-27,CAP-28', [...self::SIGNED_IN, 'capability:CAP-27,CAP-28', 'input.allow:from_page']],
        'GET /settings/users/{user}/edit' => ['users.edit', 'RP-605', 'CAP-27', [...self::SIGNED_IN, 'capability:CAP-27', 'input.allow']],
        'GET /settings/users/{user}/link' => ['users.link', 'RP-606', 'CAP-25,CAP-26', [...self::SIGNED_IN, 'capability:CAP-25,CAP-26', 'input.allow']],
        'POST /commands/user.create' => ['commands.user.create', 'RC-35', 'CAP-25', [...self::SIGNED_IN, 'capability:CAP-25', 'throttle:command', 'throttle:link_issue', 'password.confirm']],
        'POST /commands/user.update' => ['commands.user.update', 'RC-36', 'CAP-27', [...self::SIGNED_IN, 'capability:CAP-27', 'throttle:command', 'password.confirm']],
        'POST /commands/owner.update_details' => ['commands.owner.update_details', 'RC-37', 'CAP-27', [...self::SIGNED_IN, 'capability:CAP-27', 'throttle:command', 'password.confirm']],
        'POST /commands/user.deactivate' => ['commands.user.deactivate', 'RC-38', 'CAP-28', [...self::SIGNED_IN, 'capability:CAP-28', 'throttle:command', 'password.confirm']],
        'POST /commands/user.reactivate' => ['commands.user.reactivate', 'RC-39', 'CAP-28', [...self::SIGNED_IN, 'capability:CAP-28', 'throttle:command', 'password.confirm']],
        'POST /commands/user.anonymize' => ['commands.user.anonymize', 'RC-40', 'CAP-29', [...self::SIGNED_IN, 'capability:CAP-29', 'throttle:command', 'password.confirm']],
        'POST /commands/user.issue_link' => ['commands.user.issue_link', 'RC-41', 'CAP-25,CAP-26', [...self::SIGNED_IN, 'capability:CAP-25,CAP-26', 'throttle:command', 'throttle:link_issue', 'password.confirm']],
        'POST /commands/user.revoke_link' => ['commands.user.revoke_link', 'RC-42', 'CAP-26', [...self::SIGNED_IN, 'capability:CAP-26', 'throttle:command', 'password.confirm']],
        // TASK-015: Product Search, the search-first check and RC-17 to RC-24.
        'GET /data/products/search' => ['data.products.search', 'RD-01', 'CAP-02,CAP-21', [...self::SIGNED_IN, 'capability:CAP-02,CAP-21', 'throttle:search']],
        'GET /data/products/check' => ['data.products.check', 'RD-06', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:search']],
        'POST /commands/product.create' => ['commands.product.create', 'RC-17', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/product.update' => ['commands.product.update', 'RC-18', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/product.deactivate' => ['commands.product.deactivate', 'RC-19', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/product.reactivate' => ['commands.product.reactivate', 'RC-20', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/category.create' => ['commands.category.create', 'RC-21', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/category.rename' => ['commands.category.rename', 'RC-22', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/category.deactivate' => ['commands.category.deactivate', 'RC-23', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        'POST /commands/category.reactivate' => ['commands.category.reactivate', 'RC-24', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'throttle:command']],
        // TASK-016: RC-27 to RC-29.
        'POST /commands/barcode.add' => ['commands.barcode.add', 'RC-27', 'CAP-06', [...self::SIGNED_IN, 'capability:CAP-06', 'throttle:command']],
        'POST /commands/barcode.generate' => ['commands.barcode.generate', 'RC-28', 'CAP-06', [...self::SIGNED_IN, 'capability:CAP-06', 'throttle:command']],
        'POST /commands/barcode.retire' => ['commands.barcode.retire', 'RC-29', 'CAP-06', [...self::SIGNED_IN, 'capability:CAP-06', 'throttle:command']],
        // TASK-017: the catalogue pages.
        'GET /products' => ['products.index', 'RP-301', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'input.allow:q,category,brand,status,page,import']],
        'GET /products/new' => ['products.create', 'RP-303', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'input.allow:code']],
        'GET /products/{product}' => ['products.show', 'RP-302', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'input.allow']],
        'GET /products/{product}/edit' => ['products.edit', 'RP-304', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'input.allow']],
        'GET /categories' => ['categories.index', 'RP-307', 'CAP-04', [...self::SIGNED_IN, 'capability:CAP-04', 'input.allow:status']],
        // TASK-018: RC-25, RC-26, RC-47, RP-306 and RP-306P.
        'POST /commands/product_photo.set' => ['commands.product_photo.set', 'RC-25', 'CAP-09', [...self::SIGNED_IN, 'capability:CAP-09', 'throttle:command', 'throttle:photo']],
        'POST /commands/product_photo.remove' => ['commands.product_photo.remove', 'RC-26', 'CAP-09', [...self::SIGNED_IN, 'capability:CAP-09', 'throttle:command']],
        'POST /commands/document.print_labels' => ['commands.document.print_labels', 'RC-47', 'CAP-08', [...self::SIGNED_IN, 'capability:CAP-08', 'throttle:command', 'throttle:print']],
        'GET /products/{product}/labels' => ['products.labels', 'RP-306', 'CAP-08', [...self::SIGNED_IN, 'capability:CAP-08', 'input.allow']],
        'GET /products/{product}/labels/print/{printRequest}' => ['products.labels.print', 'RP-306P', 'CAP-08', [...self::SIGNED_IN, 'capability:CAP-08', 'input.allow']],
        // TASK-019: RP-308, RR-01, RR-02 and RC-31.
        'GET /products/import' => ['products.import', 'RP-308', 'CAP-10', [...self::SIGNED_IN, 'capability:CAP-10', 'input.allow']],
        'POST /products/import/preview' => ['products.import.preview', 'RR-01', 'CAP-10', [...self::SIGNED_IN, 'capability:CAP-10', 'throttle:csv']],
        'POST /products/import/{import}/cancel' => ['products.import.cancel', 'RR-02', 'CAP-10', [...self::SIGNED_IN, 'capability:CAP-10', 'input.allow']],
        'POST /commands/csv_import.confirm' => ['commands.csv_import.confirm', 'RC-31', 'CAP-10', [...self::SIGNED_IN, 'capability:CAP-10', 'throttle:command']],
        // TASK-020: RP-305, RD-05 and RC-30.
        'GET /products/{product}/barcode-correction' => ['products.barcode_correction', 'RP-305', 'CAP-07', [...self::SIGNED_IN, 'capability:CAP-07', 'input.allow']],
        'GET /data/barcode-tokens/lookup' => ['data.barcodes.lookup', 'RD-05', 'CAP-07', [...self::SIGNED_IN, 'capability:CAP-07', 'throttle:search']],
        'POST /commands/barcode.correct_misassignment' => ['commands.barcode.correct_misassignment', 'RC-30', 'CAP-07', [...self::SIGNED_IN, 'capability:CAP-07', 'throttle:command', 'password.confirm']],
        // TASK-021: RC-07.
        'POST /commands/receiving.confirm' => ['commands.receiving.confirm', 'RC-07', 'CAP-11', [...self::SIGNED_IN, 'capability:CAP-11', 'throttle:command']],
        // TASK-022: the stock pages, RD-02 and the receiving pages.
        'GET /stock' => ['stock.index', 'RP-401', 'CAP-14', [...self::SIGNED_IN, 'capability:CAP-14', 'input.allow:q,state,category,after,perPage']],
        'GET /stock/history' => ['stock.history', 'RP-402', 'CAP-14', [...self::SIGNED_IN, 'capability:CAP-14', 'input.allow:product,type,from,to,after,before,perPage,from_page']],
        'GET /stock/movements/{movement}' => ['stock.movement', 'RP-403', 'CAP-14', [...self::SIGNED_IN, 'capability:CAP-14', 'input.allow']],
        'GET /data/stock' => ['data.stock', 'RD-02', 'CAP-14', [...self::SIGNED_IN, 'capability:CAP-14']],
        'GET /stock/receivings' => ['receivings.index', 'RP-404', 'CAP-11', [...self::SIGNED_IN, 'capability:CAP-11', 'input.allow:from,to,q,after,before,perPage']],
        'GET /stock/receivings/new' => ['receivings.create', 'RP-405', 'CAP-11', [...self::SIGNED_IN, 'capability:CAP-11', 'input.allow']],
        'GET /stock/receivings/{document}' => ['receivings.show', 'RP-406', 'CAP-11', [...self::SIGNED_IN, 'capability:CAP-11', 'input.allow']],
        // TASK-023: RC-08 and the manual issue pages.
        'POST /commands/manual_issue.confirm' => ['commands.manual_issue.confirm', 'RC-08', 'CAP-13', [...self::SIGNED_IN, 'capability:CAP-13', 'throttle:command']],
        'GET /stock/issues' => ['issues.index', 'RP-407', 'CAP-13', [...self::SIGNED_IN, 'capability:CAP-13', 'input.allow:from,to,reason,after,before,perPage']],
        'GET /stock/issues/new' => ['issues.create', 'RP-408', 'CAP-13', [...self::SIGNED_IN, 'capability:CAP-13', 'input.allow']],
        'GET /stock/issues/{issue}' => ['issues.show', 'RP-409', 'CAP-13', [...self::SIGNED_IN, 'capability:CAP-13', 'input.allow']],
        // TASK-024: RC-09, with recent authentication, and RP-410.
        'POST /commands/adjustment.confirm' => ['commands.adjustment.confirm', 'RC-09', 'CAP-16', [...self::SIGNED_IN, 'capability:CAP-16', 'throttle:command', 'password.confirm']],
        'GET /stock/adjustments/new' => ['adjustments.create', 'RP-410', 'CAP-16', [...self::SIGNED_IN, 'capability:CAP-16', 'input.allow:product,from_page']],
        // TASK-025: RC-15, with recent authentication, and RP-503 to RP-505.
        'POST /commands/cost_correction.confirm' => ['commands.cost_correction.confirm', 'RC-15', 'CAP-19', [...self::SIGNED_IN, 'capability:CAP-19', 'throttle:command', 'password.confirm']],
        'GET /costs/{evidence}/correct' => ['costs.correct', 'RP-504', 'CAP-19', [...self::SIGNED_IN, 'capability:CAP-19', 'input.allow:from_page']],
        'GET /reports/costs-to-complete' => ['reports.costs', 'RP-503', 'CAP-20', [...self::SIGNED_IN, 'capability:CAP-20', 'input.allow:product,after,before,perPage']],
        'GET /products/{product}/cost-history' => ['products.cost_history', 'RP-505', 'CAP-20', [...self::SIGNED_IN, 'capability:CAP-20', 'input.allow:from_page']],
        // TASK-026: RC-10, with recent authentication, and RP-411.
        'POST /commands/reversal.confirm' => ['commands.reversal.confirm', 'RC-10', 'CAP-17', [...self::SIGNED_IN, 'capability:CAP-17', 'throttle:command', 'password.confirm']],
        'GET /stock/movements/{movement}/reverse' => ['stock.reverse', 'RP-411', 'CAP-17', [...self::SIGNED_IN, 'capability:CAP-17', 'input.allow:from_page']],
        // TASK-027: the cash registers, RC-33 and RC-34 with recent authentication; RP-104 and RC-02.
        'GET /settings/cash-registers' => ['registers.index', 'RP-609', 'CAP-31', [...self::SIGNED_IN, 'capability:CAP-31', 'input.allow']],
        'GET /settings/cash-registers/new' => ['registers.create', 'RP-610', 'CAP-31', [...self::SIGNED_IN, 'capability:CAP-31', 'input.allow']],
        'GET /settings/cash-registers/{register}/edit' => ['registers.edit', 'RP-610', 'CAP-31', [...self::SIGNED_IN, 'capability:CAP-31', 'input.allow']],
        'POST /commands/cash_register.create' => ['commands.cash_register.create', 'RC-33', 'CAP-31', [...self::SIGNED_IN, 'capability:CAP-31', 'throttle:command', 'password.confirm']],
        'POST /commands/cash_register.update' => ['commands.cash_register.update', 'RC-34', 'CAP-31', [...self::SIGNED_IN, 'capability:CAP-31', 'throttle:command', 'password.confirm']],
        'GET /till/open-session' => ['till.open', 'RP-104', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'input.allow']],
        'POST /commands/cash_session.open' => ['commands.cash_session.open', 'RC-02', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'throttle:command']],
        // TASK-028: RC-01, without recent authentication.
        'POST /commands/sale.checkout' => ['commands.sale.checkout', 'RC-01', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'throttle:command']],
        // TASK-029: the till, its payment view and the receipt, each taking only its path.
        'GET /till' => ['till.show', 'RP-101', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'input.allow']],
        'GET /till/payment' => ['till.payment', 'RP-102', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'input.allow']],
        'GET /sales/{sale}/receipt' => ['sales.receipt', 'RP-103', 'CAP-21,CAP-22', [...self::SIGNED_IN, 'capability:CAP-21,CAP-22', 'input.allow']],
        // TASK-030: RC-03 and RP-105 for CAP-21, without recent authentication; RP-205 and RP-206 for
        // CAP-21 on its own sessions and CAP-22 on every one.
        'GET /till/close-session' => ['till.close', 'RP-105', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'input.allow']],
        'POST /commands/cash_session.close' => ['commands.cash_session.close', 'RC-03', 'CAP-21', [...self::SIGNED_IN, 'capability:CAP-21', 'throttle:command']],
        'GET /cash-sessions' => ['sessions.index', 'RP-205', 'CAP-21,CAP-22', [...self::SIGNED_IN, 'capability:CAP-21,CAP-22', 'input.allow:status,from,to,register,responsible,after,before,perPage']],
        'GET /cash-sessions/{session}' => ['sessions.show', 'RP-206', 'CAP-21,CAP-22', [...self::SIGNED_IN, 'capability:CAP-21,CAP-22', 'input.allow']],
    ];

    public function test_the_routes_are_exactly_those_listed_each_with_its_gate(): void
    {
        $routes = RouteInventory::routes(app(Router::class));

        // The test-only routes of the local and testing environments: the
        // specimens (TASK-005, TASK-007) and the browser fixtures (TASK-006).
        foreach (RouteInventory::TESTING as $testing) {
            $this->assertArrayHasKey($testing, $routes);
            unset($routes[$testing]);
        }

        $actual = [];
        $contracts = RouteInventory::contracts();

        foreach ($routes as $key => $route) {
            $declared = array_map(strval(...), $route->gatherMiddleware());
            $gates = array_values(array_filter($declared, static fn (string $middleware): bool => str_starts_with($middleware, 'capability:')));
            $expected = self::ROUTES[$key] ?? null;
            $this->assertNotNull($expected, $key.' is not listed in the inventory.');
            $this->assertArrayHasKey($key, $contracts, $expected[1].' is in ROUTE_CONTRACTS.');
            $gate = $gates === [] ? (in_array('guest', $declared, true) ? 'guest' : 'none') : substr($gates[0], strlen('capability:'));

            $actual[$key] = [(string) $route->getName(), $expected[1], $gate, $declared];
        }

        ksort($actual);
        $expected = self::ROUTES;
        ksort($expected);
        $this->assertSame($expected, $actual);
    }

    public function test_every_route_keeps_the_rules_of_the_inventory(): void
    {
        $this->assertSame([], RouteInventory::violations(app(Router::class)));
    }

    public function test_the_inventory_finds_an_ungated_route_and_one_outside_v1(): void
    {
        // AC-007-01: a route signed in but without a gate, a route that no
        // contract names, a DELETE route and a gate naming no capability are
        // each found.
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->get('/account/harness-ungated', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])->get('/harness/outside-v1', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-01'])->delete('/account', static fn (): string => '');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-99'])->post('/commands/sale.checkout', static fn (): string => '');
        app(Router::class)->getRoutes()->refreshNameLookups();

        $violations = implode("\n", RouteInventory::violations(app(Router::class)));

        $this->assertStringContainsString('GET /account/harness-ungated: 0 capability gates, not one (RT-05)', $violations);
        $this->assertStringContainsString('GET /account/harness-ungated: its history entries not encrypted (SEC-CH-04)', $violations);
        $this->assertStringContainsString('GET /account/harness-ungated: not a route of ROUTE_CONTRACTS', $violations);
        $this->assertStringContainsString('GET /harness/outside-v1: not a route of ROUTE_CONTRACTS', $violations);
        $this->assertStringContainsString('GET /harness/outside-v1: its history entries not encrypted (SEC-CH-04)', $violations);
        $this->assertStringContainsString('DELETE /account: a DELETE route (TR-HTTP-04)', $violations);
        $this->assertStringContainsString('POST /commands/sale.checkout: the gate names CAP-99, not a capability', $violations);
        $this->assertStringNotContainsString('GET /account/password', $violations);
    }

    public function test_the_routes_of_route_contracts_are_read_from_the_document(): void
    {
        $contracts = RouteInventory::contracts();

        $this->assertSame('home.redirect', $contracts['GET /'] ?? null);
        $this->assertSame('login.store', $contracts['POST /login'] ?? null);
        $this->assertSame('health', $contracts['GET /up'] ?? null);
        $this->assertSame('registers.edit', $contracts['GET /settings/cash-registers/{register}/edit'] ?? null);
        $this->assertSame('data.products.search', $contracts['GET /data/products/search'] ?? null);
        $this->assertArrayHasKey('POST /commands/sale.checkout', $contracts);
        $this->assertArrayHasKey('POST /commands/resolve', $contracts);
        // The 60 routable pages, RP-610 with two paths, three print views and
        // RN-01; 7 reads; 7 authentication endpoints; 3 requests; 48 commands
        // and the resolution (ROUTE_CONTRACTS section 8).
        $this->assertCount(60 + 1 + 3 + 1 + 7 + 7 + 3 + 49, $contracts);
    }

    public function test_no_route_of_a_disabled_feature_exists(): void
    {
        $paths = [];

        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            $paths[] = $route->uri();
            $this->assertNotContains('DELETE', $route->methods(), $route->uri());
        }

        // A fragment of a disabled feature's path is refused at the start of any segment, so that
        // Fortify's `register` is caught and RP-609's `settings/cash-registers` is not (TASK-027).
        foreach (['register', 'forgot-password', 'reset-password', 'email/verify', 'email', 'verify', 'user/two-factor-recovery-codes', 'two-factor-challenge', 'user/passkeys', 'user/profile-information'] as $fortify) {
            $this->assertTrue(self::disabledFeature($fortify), $fortify);
        }

        $this->assertFalse(self::disabledFeature('settings/cash-registers'));
        $this->assertFalse(self::disabledFeature('commands/cash_register.create'));

        foreach ($paths as $path) {
            $this->assertFalse(self::disabledFeature($path), $path);
        }

        $this->assertSame([Features::updatePasswords()], config('fortify.features'));
        $this->assertFalse(config('fortify.views'));
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Features::enabled(Features::resetPasswords()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
        $this->assertFalse(Features::enabled(Features::twoFactorAuthentication()));
        $this->assertFalse(Features::enabled(Features::passkeys()));
        $this->assertFalse(Features::enabled(Features::updateProfileInformation()));
        $this->assertTrue(Features::enabled(Features::updatePasswords()));
    }

    /**
     * Whether a path names a disabled feature of Fortify: one of its fragments at the start of a
     * segment of the path.
     */
    private static function disabledFeature(string $path): bool
    {
        foreach (['register', 'forgot-password', 'reset-password', 'email', 'verify', 'two-factor', 'passkey', 'profile-information', 'recovery-codes'] as $fragment) {
            foreach (explode('/', $path) as $segment) {
                if (str_starts_with($segment, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function test_no_mail_transport_is_configured_and_nothing_can_be_sent(): void
    {
        $this->assertSame('none', config('mail.default'));
        $mailers = config('mail.mailers');
        $this->assertIsArray($mailers);
        $this->assertSame([], array_filter($mailers), 'A mailer has a configuration: no mail transport is configured (D-049).');

        $this->expectExceptionMessage('Mailer [none] is not defined.');
        Mail::raw('-', fn () => null);
    }

    public function test_ra_05_runs_the_applications_controller_which_leaves_a_link_alone(): void
    {
        // Fortify's PasswordController deletes the user's one-time link after
        // the update (review finding R-02); RA-05 never runs it.
        $route = app(Router::class)->getRoutes()->getByName('user-password.update');

        $this->assertNotNull($route);
        $this->assertSame(OwnPasswordController::class.'@update', $route->getActionName());
    }

    public function test_the_password_broker_never_sends_a_link_by_email(): void
    {
        $user = new User;

        $this->expectExceptionMessage('The application never sends email');
        $user->sendPasswordResetNotification('token');
    }
}
