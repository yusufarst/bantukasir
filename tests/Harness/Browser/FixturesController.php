<?php

namespace Tests\Harness\Browser;

use App\Database\EnvironmentMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Harness\Fixtures\TokoContoh;

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
}
