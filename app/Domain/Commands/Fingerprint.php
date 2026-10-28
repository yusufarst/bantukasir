<?php

namespace App\Domain\Commands;

use InvalidArgumentException;
use SplFileInfo;

/**
 * The fingerprint of ADR-007 section 1: SHA-256 over a canonical
 * serialization of the operation, its version, the actor and the
 * client-supplied payload with its expected versions, after the FormRequest's
 * normalization, which depends only on those values. The idempotency key,
 * the intent, the epoch and every time are left out.
 *
 * The serialization is JSON with the keys of every object in byte order,
 * lists in their order, texts unescaped (numbers travel as canonical strings,
 * ADR-006), integers, booleans and null; a floating-point value is refused
 * (BR-NUM-04). An uploaded file is represented by the SHA-256 of its content,
 * never by its name or storage key ({"sha256": …}). An empty object and an
 * empty list read the same, as PHP decodes both to an empty array.
 */
final class Fingerprint
{
    /**
     * The fingerprint as 64 lowercase hexadecimal digits.
     *
     * @param  array<array-key, mixed>  $expected
     * @param  array<array-key, mixed>  $payload
     */
    public static function of(string $operation, int $version, string $actor, array $expected, array $payload): string
    {
        return hash('sha256', self::canonical([
            'actor' => $actor,
            'expected' => $expected,
            'operation' => $operation,
            'payload' => $payload,
            'version' => $version,
        ]));
    }

    /**
     * The actor's part of the fingerprint: a user by internal identity.
     */
    public static function user(int $userId): string
    {
        return 'user:'.$userId;
    }

    public static function canonical(mixed $value): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof SplFileInfo) {
            $hash = $value->getRealPath() === false ? false : hash_file('sha256', $value->getRealPath());

            if ($hash === false) {
                throw new InvalidArgumentException('An uploaded file of the payload cannot be read.');
            }

            return ['sha256' => $hash];
        }

        if (is_float($value) || is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('The canonical payload holds only texts, integers, booleans, null, lists and objects.');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = self::normalize($item);
        }

        ksort($object, SORT_STRING);

        return $object;
    }
}
