<?php

namespace Tests\Unit\Commands;

use App\Domain\Commands\Fingerprint;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The fingerprint of ADR-007 section 1 (TASK-008 scope item 2): SHA-256 of a
 * canonical serialization of the operation, its version, the actor, the
 * expected versions and the payload; independent of the order of an object's
 * keys, of the idempotency key, the intent, the epoch and every time, which
 * it never sees; a file by the SHA-256 of its content only; no floating
 * point.
 */
final class FingerprintTest extends TestCase
{
    public function test_the_fingerprint_is_sha256_of_the_canonical_form(): void
    {
        $fingerprint = Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), ['b' => 2, 'a' => 1], ['lines' => [['quantity' => '1.5', 'productId' => 3]], 'method' => 'CASH']);

        $this->assertSame(
            hash('sha256', '{"actor":"user:7","expected":{"a":1,"b":2},"operation":"sale.checkout","payload":{"lines":[{"productId":3,"quantity":"1.5"}],"method":"CASH"},"version":1}'),
            $fingerprint,
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fingerprint);
    }

    public function test_it_depends_on_every_part_it_covers_and_not_on_key_order(): void
    {
        $base = Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['a' => '1', 'b' => ['x' => true, 'y' => null]]);

        $this->assertSame($base, Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['b' => ['y' => null, 'x' => true], 'a' => '1']));
        $this->assertNotSame($base, Fingerprint::of('sale.checkout', 1, Fingerprint::user(8), [], ['a' => '1', 'b' => ['x' => true, 'y' => null]]));
        $this->assertNotSame($base, Fingerprint::of('cash_session.open', 1, Fingerprint::user(7), [], ['a' => '1', 'b' => ['x' => true, 'y' => null]]));
        $this->assertNotSame($base, Fingerprint::of('sale.checkout', 2, Fingerprint::user(7), [], ['a' => '1', 'b' => ['x' => true, 'y' => null]]));
        $this->assertNotSame($base, Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), ['v' => 1], ['a' => '1', 'b' => ['x' => true, 'y' => null]]));
        $this->assertNotSame($base, Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['a' => '1.0', 'b' => ['x' => true, 'y' => null]]));
        $this->assertNotSame($base, Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['a' => 1, 'b' => ['x' => true, 'y' => null]]));

        // A list keeps its order.
        $this->assertNotSame(
            Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['lines' => ['1', '2']]),
            Fingerprint::of('sale.checkout', 1, Fingerprint::user(7), [], ['lines' => ['2', '1']]),
        );
    }

    public function test_a_file_stands_for_its_content_and_a_float_is_refused(): void
    {
        $first = UploadedFile::fake()->createWithContent('photo.jpg', 'same bytes');
        $renamed = UploadedFile::fake()->createWithContent('other-name.jpg', 'same bytes');
        $other = UploadedFile::fake()->createWithContent('photo.jpg', 'other bytes');

        $of = static fn (UploadedFile $file): string => Fingerprint::of('product_photo.set', 1, Fingerprint::user(1), [], ['photo' => $file]);

        $this->assertSame($of($first), $of($renamed));
        $this->assertNotSame($of($first), $of($other));
        $this->assertSame(Fingerprint::of('product_photo.set', 1, Fingerprint::user(1), [], ['photo' => ['sha256' => hash('sha256', 'same bytes')]]), $of($first));

        $this->assertThrows(fn () => Fingerprint::canonical(['price' => 1.5]), InvalidArgumentException::class);
    }
}
