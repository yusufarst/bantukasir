<?php

namespace Tests\Harness\Browser;

use App\Actions\Commands\ResolveIntent;
use App\Database\EnvironmentMarker;
use App\Domain\Access\Accounts;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\Operations;
use App\Domain\Commands\RequestReceipts;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Harness\Commands\Probe;
use Tests\Harness\Fixtures\TokoContoh;
use Tests\Harness\TestDatabase;

/**
 * The fixtures of the browser tests (TEST_STRATEGY sections 2 and 3, L5;
 * TASK-006): the running local environment serves them only in the local
 * and testing environments, from the test harness, which the release build
 * does not contain (routes.php beside this file), and only on a database
 * whose marker and environment name are local or test (ENVIRONMENTS ENV-04).
 * Each answer gives synthetic test values only, made at run time: the people
 * of Toko Contoh with a new password, or a new one-time link for one of them.
 * They are not routes of ROUTE_CONTRACTS.
 */
final class FixturesController
{
    /**
     * Gives the named people of Toko Contoh a new password — creating their
     * accounts on first use and revoking their sign-in sessions — and empties
     * the rate limiter's counters, so that every browser test starts alike.
     */
    public function reset(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        $people = $request->input('people');

        if (! is_array($people) || $people === [] || array_filter($people, fn (mixed $person): bool => ! is_string($person) || ! array_key_exists($person, TokoContoh::PEOPLE)) !== []) {
            throw new InvalidArgumentException('The browser fixtures name people of Toko Contoh.');
        }

        Cache::store()->flush();
        $accounts = [];

        // The shop is installed before its people: its name, initials and
        // accent in the shell and the title (TASK-010).
        TokoContoh::shop();

        foreach ($people as $person) {
            /** @var string $person */
            $accounts[$person] = TokoContoh::person($person);
        }

        return new JsonResponse(['accounts' => $accounts]);
    }

    /**
     * A new one-time link for one person of Toko Contoh.
     */
    public function link(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        $person = $request->input('person');

        if (! is_string($person) || ! array_key_exists($person, TokoContoh::PEOPLE)) {
            throw new InvalidArgumentException('The browser fixtures name a person of Toko Contoh.');
        }

        return new JsonResponse(TokoContoh::link($person));
    }

    /**
     * The command platform as the browser suite of TASK-009 needs it: the
     * probe's tables on the local database, created once and kept; the
     * installation's recovery epoch, issued if absent (until the bootstrap of
     * TASK-010 does); and no unresolved request left from an earlier test for
     * the named people of Toko Contoh — each of their PENDING or HELD
     * receipts is resolved by the Owner's status check, RC-50's Action, which
     * writes its fence or settles it as committed (ADR-007 section 4). Its
     * answer is the epoch.
     */
    public function commands(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        $people = self::people($request->input('people'));
        Probe::ensureTables(DB::connection(TestDatabase::MIGRATION));
        $epoch = TokoContoh::recoveryEpoch();
        $owner = self::account('Hendra');

        foreach ($people as $person) {
            $receipts = DB::table('command_request_receipts')
                ->where('actor_user_id', self::account($person)->id)
                ->whereIn('outcome', ['PENDING', 'HELD'])
                ->distinct()
                ->get(['operation', DB::raw('source_intent_id::text as intent')]);

            foreach ($receipts as $receipt) {
                try {
                    app(ResolveIntent::class)($owner, (string) $receipt->operation, (string) $receipt->intent, $epoch, CarbonImmutable::now('UTC'));
                } catch (CommandRefused) {
                    // An intent that a check cannot settle stays listed; the
                    // test that needs none fails on it and says so.
                }
            }
        }

        return new JsonResponse(['epoch' => $epoch]);
    }

    /**
     * A PENDING request receipt for one person of Toko Contoh, of a real
     * operation with a new intent and key in the current epoch, written by
     * the Commands module's own service (EXECUTION_CONTEXT EXC-45) as the
     * pipeline writes it when a request reaches the server and then gets no
     * conclusive answer: a request of another device that this browser never
     * saw (TEST_STRATEGY TS-23).
     */
    public function receipt(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        [$person] = self::people([$request->input('person')]);
        $operation = $request->input('operation');

        if (! is_string($operation) || ! Operations::submittedByUsers($operation)) {
            throw new InvalidArgumentException('The browser fixtures name an operation that users submit.');
        }

        $intent = (string) Str::uuid();
        $key = (string) Str::uuid();
        $receivedAt = CarbonImmutable::now('UTC');
        app(RequestReceipts::class)->record(self::account($person)->id, $operation, $intent, $key, TokoContoh::recoveryEpoch(), $receivedAt->format('Y-m-d H:i:s.uP'));

        return new JsonResponse(['sourceIntentId' => $intent, 'idempotencyKey' => $key, 'receivedAt' => $receivedAt->format('Y-m-d\TH:i:s.u\Z')]);
    }

    /**
     * The till of the browser suite (TASK-029): the Owner's own OPEN cash session — opened through RC-02's
     * Action on a new register when he holds none, which tests/Browser/cash/ opens first on a fresh
     * database — and three new products of their own category, each with a barcode and stock received
     * through the Actions (EXECUTION_CONTEXT EXC-45): one counted whole, one sold by the metre with one
     * decimal, and one with two units only. Every name, SKU and code is new, because the local database
     * keeps its products. With `reprice`, the Owner's new price of one product through RC-18's Action
     * instead, the price change that a cart then reviews (UXS-18).
     */
    public function till(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        $reprice = $request->input('reprice');

        if (is_array($reprice)) {
            $product = $reprice['productId'] ?? null;
            $price = $reprice['price'] ?? null;

            if (! is_int($product) || ! is_string($price) || preg_match('/^[1-9][0-9]{0,8}$/D', $price) !== 1) {
                throw new InvalidArgumentException('A new price names a product and whole rupiah.');
            }

            TokoContoh::reprice($product, $price);

            return new JsonResponse(['repriced' => $product]);
        }

        $owner = self::account('Hendra');

        if (! DB::table('cash_sessions')->where('responsible_user_id', $owner->id)->where('status', 'OPEN')->exists()) {
            TokoContoh::openSession('Hendra', TokoContoh::register('T-'.strtoupper(Str::random(10)), 'Laci uji'));
        }

        $suffix = strtoupper(Str::random(8));
        $category = TokoContoh::category('Uji kasir '.$suffix);
        $products = [];

        foreach ([
            ['sku' => 'PG-'.$suffix, 'name' => 'Pulpen gel hitam '.$suffix, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'sellingPrice' => '4500', 'stock' => '50'],
            ['sku' => 'KK-'.$suffix, 'name' => 'Kertas kado per meter '.$suffix, 'baseUnit' => 'm', 'quantityPrecision' => 1, 'sellingPrice' => '7000', 'stock' => '20'],
            ['sku' => 'BT-'.$suffix, 'name' => 'Buku tulis '.$suffix, 'baseUnit' => 'pcs', 'quantityPrecision' => 0, 'sellingPrice' => '6000', 'stock' => '2'],
        ] as $fields) {
            $id = TokoContoh::product($fields['sku'], ['categoryId' => $category, 'name' => $fields['name'], 'baseUnit' => $fields['baseUnit'], 'quantityPrecision' => $fields['quantityPrecision'], 'sellingPrice' => $fields['sellingPrice']]);
            $code = '899'.(string) random_int(1000000000, 9999999999);
            TokoContoh::barcode($id, $code);
            TokoContoh::stock($id, $fields['stock'], '1000');
            $products[] = ['id' => $id, 'sku' => $fields['sku'], 'name' => $fields['name'], 'code' => $code, 'unit' => $fields['baseUnit'], 'price' => $fields['sellingPrice']];
        }

        return new JsonResponse(['products' => $products]);
    }

    /**
     * Turns the application's real maintenance mode on or off (RELEASE_PROCESS
     * RB-20 step 7; TASK-014), on the local or test database only. The browser
     * suite runs the tests that use it after every other test, alone
     * (playwright.config.ts), because the mode is the whole application's.
     */
    public function maintenance(Request $request): JsonResponse
    {
        EnvironmentMarker::require(DB::connection(), ['local', 'test']);

        if ($request->boolean('on')) {
            app()->maintenanceMode()->activate(['status' => 503]);
        } else {
            app()->maintenanceMode()->deactivate();
        }

        return new JsonResponse(['on' => app()->maintenanceMode()->active()]);
    }

    /**
     * The person's existing account, read without changing it — its password
     * and sign-in sessions stay as they are — or, on the first use of the
     * fixtures, created by TokoContoh::person().
     */
    private static function account(string $person): User
    {
        $account = app(Accounts::class)->findByEmail(TokoContoh::email($person));

        if ($account === null) {
            TokoContoh::person($person);
            $account = app(Accounts::class)->findByEmail(TokoContoh::email($person));
        }

        return $account instanceof User ? $account : throw new InvalidArgumentException(sprintf('The account of %s exists.', $person));
    }

    /**
     * @return non-empty-list<string>
     */
    private static function people(mixed $people): array
    {
        if (! is_array($people) || $people === [] || array_filter($people, fn (mixed $person): bool => ! is_string($person) || ! array_key_exists($person, TokoContoh::PEOPLE)) !== []) {
            throw new InvalidArgumentException('The browser fixtures name people of Toko Contoh.');
        }

        /** @var non-empty-list<string> */
        return array_values($people);
    }
}
