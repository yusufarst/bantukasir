<?php

namespace App\Domain\Inventory\Onboarding;

use App\Domain\Inventory\OnboardingState;

/**
 * The installation's onboarding state as a command or a page reads it (SCHEMA section 5.7,
 * `onboarding_state`, `onboarding_releases`, `onboarding_restore_holds`; BR-INV-24; D-071, D-072;
 * G-26): the state, its version — the expected version of the onboarding and opening commands, which
 * every change of the state and every restore hold moves — whether a restore hold waits for its
 * reconciliation, and whether the durable release record exists.
 */
final readonly class OnboardingStatus
{
    public function __construct(
        public string $state,
        public int $version,
        public bool $held,
        public bool $released,
    ) {}

    /**
     * Whether onboarding may start now: NOT_STARTED, never released and not held (RC-12; G-26).
     */
    public function canStart(): bool
    {
        return $this->state === OnboardingState::NOT_STARTED && ! $this->released && ! $this->held;
    }

    /**
     * Whether entries, their corrections and the release may be made now: ACTIVE, never released and
     * not held (RC-11, RC-13, RC-14, RC-48; D-071, D-072).
     */
    public function isOpen(): bool
    {
        return $this->state === OnboardingState::ACTIVE && ! $this->released && ! $this->held;
    }
}
