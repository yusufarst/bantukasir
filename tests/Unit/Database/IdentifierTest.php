<?php

namespace Tests\Unit\Database;

use App\Database\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The quoting of a name written into SQL (TASK-004; TR-VAL-07): only the
 * identifiers of this schema pass, double-quoted; anything that could leave
 * the quotes, change the meaning or exceed PostgreSQL's 63 bytes is refused.
 */
final class IdentifierTest extends TestCase
{
    public function test_a_name_of_the_schema_is_double_quoted(): void
    {
        $this->assertSame('"cache_locks"', Identifier::quote('cache_locks'));
        $this->assertSame('"bk_runtime"', Identifier::quote('bk_runtime'));
        $this->assertSame('"_x9"', Identifier::quote('_x9'));
        $this->assertSame('"'.str_repeat('a', 63).'"', Identifier::quote(str_repeat('a', 63)));
    }

    public function test_anything_else_is_refused(): void
    {
        foreach (['', 'Cache', 'cache-locks', 'cache locks', '9cache', 'cache"; drop table x; --', "cache\n", 'cäche', str_repeat('a', 64), 'public.cache', '"cache"'] as $name) {
            try {
                Identifier::quote($name);
                $this->fail('Accepted: '.json_encode($name));
            } catch (InvalidArgumentException $refusal) {
                $this->assertStringStartsWith('Not an identifier of this schema', $refusal->getMessage());
            }
        }
    }
}
