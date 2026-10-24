<?php

namespace Tests\Feature\Database;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Tests\Harness\DatabaseTestCase;

/**
 * The tables of the database cache store, class E (SCHEMA section 5.11,
 * ADR-002 item 3; the migration 2026_10_24_000001_create_cache_tables): the
 * store works as the runtime role for what V1 keeps there — rate-limiter
 * counters and scheduler locks (TR-RUN-02) — and its grants are all four of
 * SCHEMA section 10, which an expiring counter and a released lock need. The
 * constraint family for both tables (their keys and NOT NULL columns) and the
 * retention family of class E (an expired entry is removed, and nothing else;
 * KEEP01) complete the families of TEST_STRATEGY section 5 for them; they have
 * no deferred key, and the privilege family is PrivilegeFamilyTest's.
 */
final class CacheTablesTest extends DatabaseTestCase
{
    public function test_the_database_store_counts_expires_and_forgets_as_the_runtime_role(): void
    {
        $store = Cache::store('database');

        $this->assertTrue($store->add('harness:limiter', 0, 60));
        $this->assertSame(1, $store->increment('harness:limiter'));
        $this->assertSame(2, $store->increment('harness:limiter'));
        $this->assertSame(2, $store->get('harness:limiter'));
        $this->assertSame(1, $this->observer()->table('cache')->where('key', 'like', '%harness:limiter')->count());
        $this->assertTrue($store->forget('harness:limiter'));
        $this->assertSame(0, $this->observer()->table('cache')->count());
    }

    public function test_the_database_store_holds_and_releases_a_lock_as_the_runtime_role(): void
    {
        $store = Cache::store('database')->getStore();
        $this->assertInstanceOf(LockProvider::class, $store);
        $lock = $store->lock('harness:schedule', 60);
        $other = $store->lock('harness:schedule', 60);

        $this->assertTrue($lock->get());
        $this->assertFalse($other->get());
        $this->assertSame(1, $this->observer()->table('cache_locks')->count());
        $this->assertTrue($lock->release());
        $this->assertTrue($other->get());
        $other->release();
        $this->assertSame(0, $this->observer()->table('cache_locks')->count());
    }

    public function test_the_constraints_of_the_cache_tables_refuse_a_violating_row_of_the_runtime_role(): void
    {
        $this->runtime()->insert("insert into cache (key, value, expiration) values ('harness', 'a', 1)");
        $this->runtime()->insert("insert into cache_locks (key, owner, expiration) values ('harness', 'o', 1)");

        $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into cache (key, value, expiration) values ('harness', 'b', 2)"), '23505', 'cache_pkey');
        $this->assertWriteRefused(fn (Connection $c) => $c->insert("insert into cache_locks (key, owner, expiration) values ('harness', 'p', 2)"), '23505', 'cache_locks_pkey');

        foreach (['cache' => ['key', 'value', 'expiration'], 'cache_locks' => ['key', 'owner', 'expiration']] as $table => $columns) {
            foreach ($columns as $column) {
                $row = ['key' => 'null-'.$column, 'value' => 'v', 'owner' => 'o', 'expiration' => 1];
                $row[$column] = null;
                $values = array_intersect_key($row, array_flip($columns));

                $this->assertWriteRefused(fn (Connection $c) => $c->table($table)->insert($values), '23502');
            }
        }

        $this->assertSame(1, $this->observer()->table('cache')->count());
        $this->assertSame(1, $this->observer()->table('cache_locks')->count());
    }

    public function test_the_store_removes_an_expired_entry_and_nothing_else(): void
    {
        $now = time();
        $prefix = config('cache.prefix');
        $this->assertIsString($prefix);
        $this->runtime()->table('cache')->insert([
            ['key' => $prefix.'harness:expired', 'value' => serialize(1), 'expiration' => $now - 10],
            ['key' => $prefix.'harness:live', 'value' => serialize(2), 'expiration' => $now + 600],
        ]);

        $this->assertRemovedOnly('cache', fn (array $row): bool => $row['key'] === $prefix.'harness:expired', function (): void {
            $this->assertNull(Cache::store('database')->get('harness:expired'));
            $this->assertSame(2, Cache::store('database')->get('harness:live'));
        }, 'key');
    }

    public function test_the_store_uses_the_runtime_connection(): void
    {
        $this->assertNull(config('cache.stores.database.connection'));
        $this->assertSame('pgsql', config('database.default'));
    }
}
