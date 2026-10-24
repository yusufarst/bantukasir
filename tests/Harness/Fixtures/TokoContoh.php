<?php

namespace Tests\Harness\Fixtures;

use App\Database\DatabaseRefused;
use App\Database\EnvironmentMarker;
use Illuminate\Database\ConnectionInterface;

/**
 * The fixture builder of the synthetic shop "Toko Contoh" (KEY_SCREENS
 * section 5; EXECUTION_CONTEXT section 10; TASK-004). It runs only where the
 * database's marker and the configured environment name are both test or
 * local (ENVIRONMENTS ENV-04), never on any other: it lives in the test
 * harness, which the release build does not contain.
 *
 * Its people and shop are those of the previews, all invented. Fixtures are
 * created through the Actions, never by inserting rows (TEST_STRATEGY
 * section 3), so each Task that delivers an Action extends build() with the
 * records it creates; TASK-004 delivers none, and build() creates nothing yet.
 */
final class TokoContoh
{
    public const string SHOP = 'Toko Contoh';

    /**
     * The people of KEY_SCREENS section 5 with their roles (D-032).
     */
    public const array PEOPLE = [
        'Hendra' => ['SUPER_ADMIN'],
        'Agus' => ['OPERATIONS_ADMIN'],
        'Rina' => ['CASHIER'],
        'Dewi' => ['CASHIER', 'OPERATIONS_ADMIN'],
    ];

    /**
     * The day around which the previews' numbers and dates lie.
     */
    public const string AROUND = '2026-10-17';

    /**
     * Builds the shop on the database of this connection, after the marker
     * check, and returns the marker it was built on.
     *
     * @throws DatabaseRefused on any database whose marker is not test or local
     */
    public static function build(ConnectionInterface $connection): string
    {
        return EnvironmentMarker::require($connection, ['test', 'local']);
    }
}
