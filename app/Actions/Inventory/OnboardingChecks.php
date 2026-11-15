<?php

namespace App\Actions\Inventory;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use App\Domain\Inventory\Onboarding\OnboardingStatus;
use App\Support\Formatting\Format;
use App\Support\Validation\QuantityRule;
use LogicException;

/**
 * The checks that the onboarding and opening commands share (INTERACTION_CONTRACTS RC-11 to RC-14 and
 * RC-48; TECHNICAL_RULES section 7.2, "Opening stock" and "Onboarding set or release"; D-071, D-072;
 * G-26): the onboarding version that the Owner reviewed, from `expected.onboardingVersion`, which its
 * FormRequest validated; and, under the lock of the onboarding state, that it is still that version and
 * open for entries — ACTIVE, never released and not held for a restore reconciliation — else
 * STALE_VERSION with nothing written.
 */
final class OnboardingChecks
{
    /**
     * The onboarding version as the Owner reviewed it.
     *
     * @param  array<array-key, mixed>  $expected
     */
    public static function version(array $expected): int
    {
        $version = $expected['onboardingVersion'] ?? null;

        return is_int($version) ? $version : throw new LogicException('The onboarding version reviewed was validated before the Action.');
    }

    /**
     * RC-11, RC-13, RC-14 and RC-48: onboarding open for entries at the version reviewed.
     *
     * @param  array<array-key, mixed>  $expected
     *
     * @throws CommandRefused STALE_VERSION
     */
    public static function requireOpen(OnboardingStatus $status, array $expected): void
    {
        if ($status->version !== self::version($expected) || ! $status->isOpen()) {
            throw CommandRefused::of(ErrorCode::StaleVersion);
        }
    }

    /**
     * A quantity's problem against its product's precision, as the refusal at its field gives it
     * (BR-INV-06; TR-VAL-08), or null; zero is admitted where the field admits it.
     *
     * @return list<array{key: string, params: array<string, string|int>}>|null
     */
    public static function quantityProblem(string $quantity, int $precision, string $path, bool $zeroAllowed): ?array
    {
        $problem = (new QuantityRule($precision, $zeroAllowed))->problem($quantity, $path);

        return $problem === null ? null : [['key' => 'ux.validation.'.$problem, 'params' => $problem === 'quantity_decimals' ? ['desimal' => Format::count($precision)] : []]];
    }

    /**
     * VALIDATION_FAILED with one key at one field.
     */
    public static function refusedAt(string $path, string $key): CommandRefused
    {
        return new CommandRefused(ErrorCode::ValidationFailed, fields: [$path => [['key' => $key, 'params' => []]]]);
    }
}
