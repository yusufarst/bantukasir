<?php

namespace Tests\Feature\Access;

use App\Domain\Access\Capability;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Access\PermissionMatrix;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\Http\Browsers;
use Tests\Harness\Http\RouteInventory;

/**
 * The capability matrix, by direct request (AC-007-02; ROUTE_CONTRACTS RT-04,
 * RT-05, RT-06; SECURITY_MODEL SEC-AZ-01, SEC-AZ-02, SEC-AZ-05, SEC-AZ-07,
 * section 6; TECHNICAL_RULES TR-HTTP-02, TR-VAL-01, TR-VAL-03; EXECUTION_CONTEXT
 * section 10; suites AUTH01 and SEC01; THR-02, THR-03; TASK-007 scope items 1
 * and 2). Data-driven: every route present, for a visitor without a sign-in
 * session, each role and the combined account, answers what the matrix gives;
 * every gate of CAP-01 to CAP-34 opens exactly for the kinds of account that
 * SECURITY_MODEL section 6 allows, read from the document; a refusal is
 * FORBIDDEN and recorded; no GET writes; and a role, a user or an actor sent
 * in the body of any request is refused and changes nothing. Every later Task
 * adds its routes to ROUTES.
 */
final class CapabilityMatrixTest extends DatabaseTestCase
{
    use Browsers;

    /** The person of each kind of account (KEY_SCREENS section 5). */
    private const array PEOPLE = ['owner' => 'Hendra', 'ops' => 'Agus', 'cashier' => 'Rina', 'combined' => 'Dewi'];

    /**
     * Every route: its request, and the answer of each kind of account —
     * the status, and the location of a redirect.
     *
     * @var array<string, array{method: string, path: string, json: bool, answers: array<string, array{int, string|null}>}>
     */
    private const array ROUTES = [
        'RN-01' => ['method' => 'GET', 'path' => '/', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/account'], 'ops' => [302, '/stock'], 'cashier' => [302, '/till/open-session'], 'combined' => [302, '/stock'],
        ]],
        'RP-001' => ['method' => 'GET', 'path' => '/login', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [302, '/'], 'ops' => [302, '/'], 'cashier' => [302, '/'], 'combined' => [302, '/'],
        ]],
        'RP-002' => ['method' => 'GET', 'path' => '/set-password', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [302, '/#'], 'ops' => [302, '/#'], 'cashier' => [302, '/#'], 'combined' => [302, '/#'],
        ]],
        'RP-003' => ['method' => 'GET', 'path' => '/account', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RP-004' => ['method' => 'GET', 'path' => '/account/password', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RA-04' => ['method' => 'GET', 'path' => '/user/confirmed-password-status', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RA-07' => ['method' => 'POST', 'path' => '/session/activity', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [204, null], 'ops' => [204, null], 'cashier' => [204, null], 'combined' => [204, null],
        ]],
        'RA-02' => ['method' => 'POST', 'path' => '/logout', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/login'], 'ops' => [302, '/login'], 'cashier' => [302, '/login'], 'combined' => [302, '/login'],
        ]],
        'RH-01' => ['method' => 'GET', 'path' => '/up', 'json' => false, 'answers' => [
            'guest' => [200, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        // The routes that take a body, sent without one: a signed-in account
        // is sent away from the pre-authentication routes, and passes the
        // gate of the signed-in ones to the refusal of its missing fields.
        'RA-01' => ['method' => 'POST', 'path' => '/login', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/'], 'ops' => [302, '/'], 'cashier' => [302, '/'], 'combined' => [302, '/'],
        ]],
        'RA-06' => ['method' => 'POST', 'path' => '/set-password', 'json' => false, 'answers' => [
            'guest' => [302, '/set-password'], 'owner' => [302, '/#'], 'ops' => [302, '/#'], 'cashier' => [302, '/#'], 'combined' => [302, '/#'],
        ]],
        'RA-03' => ['method' => 'POST', 'path' => '/user/confirm-password', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        'RA-05' => ['method' => 'PUT', 'path' => '/user/password', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        // TASK-008: CAP-34 for every kind of account — the Owner any intent,
        // the others their own, which the Action checks.
        'RC-50' => ['method' => 'POST', 'path' => '/commands/resolve', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        'RD-04' => ['method' => 'GET', 'path' => '/data/command-requests', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        // TASK-009: RD-03 for every signed-in account (CAP-01), RP-006 for
        // every holder of CAP-34, which every role is.
        'RD-03' => ['method' => 'GET', 'path' => '/data/shell', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RP-006' => ['method' => 'GET', 'path' => '/requests', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        // TASK-010: the Owner's settings pages, RC-32 — which without a
        // recent confirmation answers 423 before anything runs — and RD-07,
        // which every role passes to NOT_FOUND for a file that does not exist.
        'RP-601' => ['method' => 'GET', 'path' => '/settings', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-607' => ['method' => 'GET', 'path' => '/settings/business-profile', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-608' => ['method' => 'GET', 'path' => '/settings/business-profile/edit', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-32' => ['method' => 'POST', 'path' => '/commands/business_profile.change', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RD-07' => ['method' => 'GET', 'path' => '/media/{file}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [404, null], 'combined' => [404, null],
        ]],
        // TASK-011: the Owner's user pages, the last three of an identifier that
        // does not exist, and RC-35 to RC-42, which without a recent confirmation
        // answer 423 before anything runs.
        'RP-005' => ['method' => 'GET', 'path' => '/account/details', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-602' => ['method' => 'GET', 'path' => '/settings/users', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-603' => ['method' => 'GET', 'path' => '/settings/users/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-604' => ['method' => 'GET', 'path' => '/settings/users/{user}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-605' => ['method' => 'GET', 'path' => '/settings/users/{user}/edit', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-606' => ['method' => 'GET', 'path' => '/settings/users/{user}/link', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-35' => ['method' => 'POST', 'path' => '/commands/user.create', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-36' => ['method' => 'POST', 'path' => '/commands/user.update', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-37' => ['method' => 'POST', 'path' => '/commands/owner.update_details', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-38' => ['method' => 'POST', 'path' => '/commands/user.deactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-39' => ['method' => 'POST', 'path' => '/commands/user.reactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-40' => ['method' => 'POST', 'path' => '/commands/user.anonymize', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-41' => ['method' => 'POST', 'path' => '/commands/user.issue_link', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-42' => ['method' => 'POST', 'path' => '/commands/user.revoke_link', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        // TASK-015: Product Search for CAP-02, which a cashier holds only in the
        // till's restricted form — the gate opens for CAP-21 too, whose context
        // `till` alone a cashier passes (TASK-028) — and the search-first check
        // and RC-17 to RC-24 for CAP-04; each sent without its parameters or
        // fields, every kind of account that passes the gate meets the refusal of
        // what is missing.
        'RD-01' => ['method' => 'GET', 'path' => '/data/products/search', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        'RD-06' => ['method' => 'GET', 'path' => '/data/products/check', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-17' => ['method' => 'POST', 'path' => '/commands/product.create', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-18' => ['method' => 'POST', 'path' => '/commands/product.update', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-19' => ['method' => 'POST', 'path' => '/commands/product.deactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-20' => ['method' => 'POST', 'path' => '/commands/product.reactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-21' => ['method' => 'POST', 'path' => '/commands/category.create', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-22' => ['method' => 'POST', 'path' => '/commands/category.rename', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-23' => ['method' => 'POST', 'path' => '/commands/category.deactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-24' => ['method' => 'POST', 'path' => '/commands/category.reactivate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        // TASK-016: RC-27 to RC-29 for CAP-06, each sent without its fields.
        'RC-27' => ['method' => 'POST', 'path' => '/commands/barcode.add', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-28' => ['method' => 'POST', 'path' => '/commands/barcode.generate', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-29' => ['method' => 'POST', 'path' => '/commands/barcode.retire', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        // TASK-017: the catalogue pages for CAP-04, the last two of an identifier that
        // does not exist.
        'RP-301' => ['method' => 'GET', 'path' => '/products', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-303' => ['method' => 'GET', 'path' => '/products/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-302' => ['method' => 'GET', 'path' => '/products/{product}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        'RP-304' => ['method' => 'GET', 'path' => '/products/{product}/edit', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        'RP-307' => ['method' => 'GET', 'path' => '/categories', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        // TASK-018: the photo for CAP-09 and the labels for CAP-08, of an identifier
        // that does not exist where the route names one.
        'RC-25' => ['method' => 'POST', 'path' => '/commands/product_photo.set', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-26' => ['method' => 'POST', 'path' => '/commands/product_photo.remove', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RC-47' => ['method' => 'POST', 'path' => '/commands/document.print_labels', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RP-306' => ['method' => 'GET', 'path' => '/products/{product}/labels', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        'RP-306P' => ['method' => 'GET', 'path' => '/products/{product}/labels/print/{printRequest}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        // TASK-019: the CSV import for CAP-10; RR-02 answers the same for an import that
        // does not exist.
        'RP-308' => ['method' => 'GET', 'path' => '/products/import', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RR-01' => ['method' => 'POST', 'path' => '/products/import/preview', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RR-02' => ['method' => 'POST', 'path' => '/products/import/{import}/cancel', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [204, null], 'ops' => [204, null], 'cashier' => [403, null], 'combined' => [204, null],
        ]],
        'RC-31' => ['method' => 'POST', 'path' => '/commands/csv_import.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        // TASK-020: the misassignment correction for CAP-07, the Owner's alone: RP-305 of an
        // identifier that does not exist, RD-05 without its token, and RC-30, whose recent
        // authentication comes before its fields.
        'RP-305' => ['method' => 'GET', 'path' => '/products/{product}/barcode-correction', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RD-05' => ['method' => 'GET', 'path' => '/data/barcode-tokens/lookup', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-30' => ['method' => 'POST', 'path' => '/commands/barcode.correct_misassignment', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        // TASK-021: the receiving confirmation for CAP-11, the Owner's and OPERATIONS_ADMIN's,
        // without recent authentication: an empty envelope reaches its fields.
        'RC-07' => ['method' => 'POST', 'path' => '/commands/receiving.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        // TASK-022: the stock pages and RD-02 for CAP-14, whose cashier's cell is the stock at
        // checkout, which opens no gate; the receiving pages for CAP-11; the detail pages of an
        // identifier that does not exist.
        'RP-401' => ['method' => 'GET', 'path' => '/stock', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-402' => ['method' => 'GET', 'path' => '/stock/history', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-403' => ['method' => 'GET', 'path' => '/stock/movements/{movement}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        'RD-02' => ['method' => 'GET', 'path' => '/data/stock', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-404' => ['method' => 'GET', 'path' => '/stock/receivings', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-405' => ['method' => 'GET', 'path' => '/stock/receivings/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-406' => ['method' => 'GET', 'path' => '/stock/receivings/{document}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        // TASK-023: the manual issue for CAP-13, the Owner's and OPERATIONS_ADMIN's, without
        // recent authentication: an empty envelope reaches its fields; the detail of an
        // identifier that does not exist.
        'RC-08' => ['method' => 'POST', 'path' => '/commands/manual_issue.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [422, null], 'cashier' => [403, null], 'combined' => [422, null],
        ]],
        'RP-407' => ['method' => 'GET', 'path' => '/stock/issues', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-408' => ['method' => 'GET', 'path' => '/stock/issues/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [200, null], 'cashier' => [403, null], 'combined' => [200, null],
        ]],
        'RP-409' => ['method' => 'GET', 'path' => '/stock/issues/{issue}', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [404, null], 'cashier' => [403, null], 'combined' => [404, null],
        ]],
        // TASK-024: the adjustment for CAP-16, the Owner's alone, with recent authentication; its
        // page, every other role and the combined account refused (AC-024-01).
        'RC-09' => ['method' => 'POST', 'path' => '/commands/adjustment.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-410' => ['method' => 'GET', 'path' => '/stock/adjustments/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        // TASK-025: the cost correction for CAP-19 and the cost pages for CAP-20, the Owner's alone,
        // RC-15 with recent authentication; every other role and the combined account refused
        // (AC-025-02, THR-19); a cost source or a product that does not exist.
        'RC-15' => ['method' => 'POST', 'path' => '/commands/cost_correction.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-503' => ['method' => 'GET', 'path' => '/reports/costs-to-complete', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-504' => ['method' => 'GET', 'path' => '/costs/{evidence}/correct', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-505' => ['method' => 'GET', 'path' => '/products/{product}/cost-history', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        // TASK-026: the reversal for CAP-17, the Owner's alone, RC-10 with recent authentication; every
        // other role and the combined account refused; a movement that does not exist.
        'RC-10' => ['method' => 'POST', 'path' => '/commands/reversal.confirm', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-411' => ['method' => 'GET', 'path' => '/stock/movements/{movement}/reverse', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        // TASK-027: the cash registers for CAP-31, the Owner's alone, RC-33 and RC-34 with recent
        // authentication, the form of a register that does not exist; RP-104 and RC-02 for CAP-21,
        // each holder on its own session, without recent authentication, Operations refused.
        'RP-609' => ['method' => 'GET', 'path' => '/settings/cash-registers', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-610' => ['method' => 'GET', 'path' => '/settings/cash-registers/new', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-610 edit' => ['method' => 'GET', 'path' => '/settings/cash-registers/{register}/edit', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-33' => ['method' => 'POST', 'path' => '/commands/cash_register.create', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RC-34' => ['method' => 'POST', 'path' => '/commands/cash_register.update', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [423, null], 'ops' => [403, null], 'cashier' => [403, null], 'combined' => [403, null],
        ]],
        'RP-104' => ['method' => 'GET', 'path' => '/till/open-session', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [200, null], 'ops' => [403, null], 'cashier' => [200, null], 'combined' => [200, null],
        ]],
        'RC-02' => ['method' => 'POST', 'path' => '/commands/cash_session.open', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [403, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        // TASK-028: the Sale checkout for CAP-21, each holder in its own OPEN session, without recent
        // authentication; an account with OPERATIONS_ADMIN only refused (AC-028-02).
        'RC-01' => ['method' => 'POST', 'path' => '/commands/sale.checkout', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [422, null], 'ops' => [403, null], 'cashier' => [422, null], 'combined' => [422, null],
        ]],
        // TASK-029: the till and its payment view for CAP-21, each holder in its own OPEN session —
        // without one, Buka sesi kas; the receipt of a Sale that does not exist, for its cashier
        // (CAP-21) and the Owner (CAP-22); Operations refused.
        'RP-101' => ['method' => 'GET', 'path' => '/till', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/till/open-session'], 'ops' => [403, null], 'cashier' => [302, '/till/open-session'], 'combined' => [302, '/till/open-session'],
        ]],
        'RP-102' => ['method' => 'GET', 'path' => '/till/payment', 'json' => false, 'answers' => [
            'guest' => [302, '/login'], 'owner' => [302, '/till/open-session'], 'ops' => [403, null], 'cashier' => [302, '/till/open-session'], 'combined' => [302, '/till/open-session'],
        ]],
        'RP-103' => ['method' => 'GET', 'path' => '/sales/{sale}/receipt', 'json' => true, 'answers' => [
            'guest' => [401, null], 'owner' => [404, null], 'ops' => [403, null], 'cashier' => [404, null], 'combined' => [404, null],
        ]],
    ];

    /** @var array<string, array{id: int, email: string, password: string}> */
    private array $accounts = [];

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The pages render the root view, whose assets the PHP tests never
        // read (Tests\TestCase).
        $this->withoutVite();
        $this->useDatabaseSessions();

        // The installation, so that the BusinessProfile pages have a revision
        // to show (TASK-010).
        TokoContoh::shop();

        foreach (self::PEOPLE as $actor => $person) {
            $this->accounts[$actor] = TokoContoh::person($person);
        }
    }

    public function test_the_matrix_holds_every_route_of_the_router(): void
    {
        $listed = array_map(static fn (array $route): string => $route['method'].' '.$route['path'], array_values(self::ROUTES));
        $served = array_values(array_diff(array_keys(RouteInventory::routes(app('router'))), RouteInventory::TESTING));

        sort($listed);
        sort($served);

        $this->assertSame($served, $listed, 'Every route of the router has its row in ROUTES (AC-007-02; EXECUTION_CONTEXT section 10).');

        foreach (self::ROUTES as $id => $route) {
            $this->assertSame(['guest', 'owner', 'ops', 'cashier', 'combined'], array_keys($route['answers']), $id.' answers every kind of account.');
        }
    }

    public function test_every_route_answers_each_kind_of_account_as_the_matrix_gives(): void
    {
        foreach (self::ROUTES as $id => $route) {
            foreach ($route['answers'] as $actor => [$status, $location]) {
                $this->as($actor);
                $response = $this->send($route['method'], $route['path'], $route['json']);

                $this->assertSame($status, $response->getStatusCode(), sprintf('%s for %s', $id, $actor));

                if ($location !== null) {
                    $this->assertSame($location === '/#' ? url('/').'/#' : url($location), $response->headers->get('Location'), sprintf('%s for %s', $id, $actor));
                }
            }
        }
    }

    public function test_every_gate_opens_exactly_for_the_kinds_of_account_of_security_model_section_6(): void
    {
        $matrix = PermissionMatrix::cells();

        foreach (Capability::cases() as $capability) {
            Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:'.$capability->value])
                ->get('/harness/gate/'.$capability->value, static fn (): string => 'opened');
        }

        foreach (self::PEOPLE as $actor => $person) {
            $session = $this->signInBrowser($this->accounts[$actor]['email'], $this->accounts[$actor]['password']);

            foreach (Capability::cases() as $capability) {
                $opens = in_array(PermissionMatrix::grantFor($matrix[$capability->value], $actor), ['full', 'own'], true);
                $refusals = $this->refusals();

                $this->browse($session);
                $response = $this->getJson('/harness/gate/'.$capability->value);

                if ($opens) {
                    $response->assertOk()->assertSeeText('opened');
                    $this->assertSame($refusals, $this->refusals());
                } else {
                    $response->assertStatus(403)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'FORBIDDEN', 'messageKey' => 'ux.state.forbidden', 'params' => [], 'fields' => [], 'lines' => []]);
                    $this->assertSame($refusals + 1, $this->refusals(), sprintf('%s for %s is recorded (SEC-LOG-03).', $capability->value, $actor));
                }
            }
        }
    }

    public function test_a_refused_page_is_the_page_of_uxs_07_and_a_gate_naming_several_capabilities_opens_on_any(): void
    {
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-22'])->get('/harness/owner-page', static fn (): string => 'opened');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated', 'capability:CAP-21,CAP-22'])->get('/harness/sales', static fn (): string => 'opened');

        $this->as('cashier');
        $page = $this->get('/harness/owner-page')->assertStatus(403)->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame(['shell/Error', 'forbidden'], [$page['component'], ((array) $page['props'])['state'] ?? null]);
        $this->assertTrue($page['encryptHistory'], 'The page of a signed-in user is encrypted in the history (SEC-CH-04).');

        $last = (array) $this->observer()->table('security_log_entries')->orderByDesc('id')->first();
        $this->assertSame(['AUTHORIZATION_REFUSED', 'USER', $this->accounts['cashier']['id'], 'REFUSED'], [$last['event_type'], $last['actor_kind'], $last['actor_user_id'], $last['outcome_code']]);

        $this->get('/harness/sales')->assertOk();
        $this->as('ops');
        $this->get('/harness/sales')->assertStatus(403);
        $this->as('owner');
        $this->get('/harness/sales')->assertOk();
    }

    public function test_a_signed_in_route_without_a_declared_capability_is_refused_at_run_time(): void
    {
        // SEC-AZ-01: refused, not only found by the route inventory (review
        // finding R-32). The route would answer its mark if it ran, a text that no
        // key of the page's dictionary holds.
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->get('/harness/ungated', static fn (): string => 'ungated-route-ran');
        Route::middleware(['web', 'auth', 'signin.session', 'throttle:authenticated'])->post('/harness/ungated', static fn (): string => 'ungated-route-ran');

        foreach (['owner', 'ops', 'cashier', 'combined'] as $actor) {
            $this->as($actor);
            $refusals = $this->refusals();

            $this->getJson('/harness/ungated')->assertStatus(403)->assertExactJson(['outcome' => 'REJECTED', 'code' => 'FORBIDDEN', 'messageKey' => 'ux.state.forbidden', 'params' => [], 'fields' => [], 'lines' => []]);
            $this->postJson('/harness/ungated')->assertStatus(403)->assertDontSeeText('ungated-route-ran');
            $this->get('/harness/ungated')->assertStatus(403)->assertDontSeeText('ungated-route-ran');
            $this->assertSame($refusals + 3, $this->refusals(), sprintf('Each refusal of %s is recorded (SEC-LOG-03).', $actor));
        }

        // Without a sign-in session, authentication refuses it first.
        $this->as('guest');
        $this->getJson('/harness/ungated')->assertStatus(401)->assertDontSeeText('ungated-route-ran');
        $this->postJson('/harness/ungated')->assertStatus(401)->assertDontSeeText('ungated-route-ran');
        $this->get('/harness/ungated')->assertRedirect('/login');
    }

    public function test_no_get_writes(): void
    {
        foreach (self::ROUTES as $id => $route) {
            if ($route['method'] !== 'GET') {
                continue;
            }

            foreach (array_keys($route['answers']) as $actor) {
                $this->as($actor);
                $before = $this->businessState();
                $this->send('GET', $route['path'], $route['json']);

                $this->assertSame($before, $this->businessState(), sprintf('%s for %s wrote (TR-HTTP-02).', $id, $actor));
            }
        }
    }

    public function test_a_role_a_user_or_an_actor_in_the_body_of_any_request_is_refused_and_changes_nothing(): void
    {
        $injections = [['role' => 'SUPER_ADMIN'], ['roles' => ['SUPER_ADMIN']], ['user_id' => '1'], ['actor_id' => '1'], ['responsible_user_id' => '1']];

        foreach (['owner', 'ops', 'cashier', 'combined'] as $actor) {
            foreach ($injections as $injection) {
                $this->as($actor);
                $before = $this->businessState();
                $new = TokoContoh::newPassword();
                $refused = [
                    'RA-02' => $this->postJson('/logout', $injection),
                    'RA-07' => $this->postJson('/session/activity', $injection),
                    'RA-03' => $this->postJson('/user/confirm-password', ['password' => $this->accounts[$actor]['password'], ...$injection]),
                    'RA-05' => $this->putJson('/user/password', ['current_password' => $this->accounts[$actor]['password'], 'password' => $new, 'password_confirmation' => $new, ...$injection]),
                    'RC-50' => $this->postJson('/commands/resolve', ['operation' => 'sale.checkout', 'sourceIntentId' => '7b0c1c53-5f0e-4c4e-9a43-2f1f6a7e9b10', 'recoveryEpoch' => str_repeat('ab', 32), ...$injection]),
                ];

                foreach ($refused as $id => $response) {
                    $this->assertSame(422, $response->getStatusCode(), sprintf('%s with %s for %s', $id, (string) json_encode($injection), $actor));
                }

                $this->assertSame($before, $this->businessState());
                // Still signed in: the refused sign-out ran nothing.
                $this->getJson('/user/confirmed-password-status')->assertOk()->assertExactJson(['confirmed' => false]);
            }
        }

        // Before sign-in: the sign-in and the link refuse a role in the body.
        $this->as('guest');
        $this->post('/login', ['email' => $this->accounts['owner']['email'], 'password' => $this->accounts['owner']['password'], 'role' => 'SUPER_ADMIN'])->assertRedirect();
        $this->assertGuest();
        $this->post('/set-password', ['user' => (string) $this->accounts['cashier']['id'], 'token' => 'x', 'password' => 'Aa1#aaaa', 'password_confirmation' => 'Aa1#aaaa', 'role' => 'SUPER_ADMIN'])->assertRedirect();
        $this->assertSame(['CASHIER'], $this->rolesOf($this->accounts['cashier']['id']));
    }

    /**
     * The next requests come from a new browser of this kind of account,
     * signed in, or without a sign-in session. The rate limiter's counters are
     * emptied first: this test signs the same people in far more often than
     * the sign-in buckets allow, which RateLimitsTest proves.
     */
    private function as(string $actor): void
    {
        Cache::store()->flush();

        if ($actor === 'guest') {
            $this->browse(null);

            return;
        }

        $this->browse($this->signInBrowser($this->accounts[$actor]['email'], $this->accounts[$actor]['password']));
    }

    /**
     * @return TestResponse<Response>
     */
    private function send(string $method, string $path, bool $json): TestResponse
    {
        if ($json) {
            return $this->json($method, $path);
        }

        return $method === 'GET' ? $this->get($path) : $this->post($path);
    }

    private function refusals(): int
    {
        return $this->watcher()->table('security_log_entries')->where('event_type', 'AUTHORIZATION_REFUSED')->count();
    }

    /** One independent session for every read of the test, so that the test opens one connection, not one per read. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The business state that no GET may change: every account, link and
     * audit event, as committed.
     *
     * @return array<string, mixed>
     */
    private function businessState(): array
    {
        $observer = $this->watcher();

        return [
            'users' => $observer->table('users')->orderBy('id')->get(['id', 'status', 'password', 'role_super_admin', 'role_operations_admin', 'role_cashier', 'version', 'updated_at'])->map(static fn (object $row): array => (array) $row)->all(),
            'links' => $observer->table('password_reset_tokens')->orderBy('email')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'audit' => $observer->table('audit_events')->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function rolesOf(int $id): array
    {
        $row = (array) DB::table('users')->where('id', $id)->first();
        $roles = [];

        foreach (['role_super_admin' => 'SUPER_ADMIN', 'role_operations_admin' => 'OPERATIONS_ADMIN', 'role_cashier' => 'CASHIER'] as $column => $role) {
            if (($row[$column] ?? false) === true) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
