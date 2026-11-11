<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\GuardedProduct;
use App\Domain\Catalog\Products;
use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Closure;
use LogicException;

/**
 * The guards of every ordinary stock posting (TECHNICAL_RULES section 7.2, "Receiving
 * confirmation, manual issue, adjustment"; BR-INV-06, BR-INV-11; G-10, G-11), which the
 * posting Action calls in the order of TR §7.1: the onboarding freeze under its share lock
 * (step 2.3), then the products under their update locks, ascending (step 6), then the
 * checks under those locks (TR-VAL-08).
 *
 * The checks stand in the order of INTERACTION_CONTRACTS RC-07 — a product that does not
 * exist, active, not blocked, the version the user reviewed — each refusal naming every
 * line it concerns in `lines`, so that the page can show them all (section 1.6).
 */
final class PostingGuard
{
    public function __construct(
        private readonly OnboardingState $onboarding,
        private readonly Products $products,
        private readonly PostingBlocks $blocks,
    ) {}

    /**
     * Step 2.3: refuses a posting while onboarding is on (G-10; BR-INV-24). The state becomes
     * ACTIVE only through TASK-035.
     *
     * @throws CommandRefused ONBOARDING_FREEZE
     */
    public function requireOnboardingOff(): void
    {
        $state = $this->onboarding->lockShared() ?? throw new LogicException('The bootstrap inserts the onboarding state (SEC-OP-01).');

        if ($state === OnboardingState::ACTIVE) {
            throw CommandRefused::of(ErrorCode::OnboardingFreeze);
        }
    }

    /**
     * Step 6: the products under their update locks, ascending.
     *
     * @param  list<int>  $ids  each once
     * @return array<int, GuardedProduct>
     */
    public function lockProducts(array $ids): array
    {
        return $this->products->lockForPosting($ids);
    }

    /**
     * The checks under the product guard.
     *
     * @param  array<int, int>  $lineProducts  the product of each line, by the line's index in the request
     * @param  array<int, GuardedProduct>  $held  what lockProducts() answered
     * @param  array<int, int>  $expected  the version of each product as the user reviewed it
     *
     * @throws CommandRefused NOT_FOUND, PRODUCT_INACTIVE, POSTING_BLOCKED or STALE_VERSION
     */
    public function requirePostable(array $lineProducts, array $held, array $expected): void
    {
        $this->requireActiveAndUnblocked($lineProducts, $held);

        self::refuseLines(ErrorCode::StaleVersion, $lineProducts, static fn (int $product): bool => ($expected[$product] ?? null) !== $held[$product]->version);
    }

    /**
     * The checks under the product guard that every posting makes, a Sale's too (TECHNICAL_RULES
     * section 7.2, "Sale checkout"; G-11), whose own reviewed state is its price revision rather
     * than the product's version (BR-SALE-05): each product exists, is active and is not blocked.
     *
     * @param  array<int, int>  $lineProducts  the product of each line, by the line's index in the request
     * @param  array<int, GuardedProduct>  $held  the products under their update locks
     *
     * @throws CommandRefused NOT_FOUND, PRODUCT_INACTIVE or POSTING_BLOCKED
     */
    public function requireActiveAndUnblocked(array $lineProducts, array $held): void
    {
        foreach ($lineProducts as $product) {
            if (! array_key_exists($product, $held)) {
                throw CommandRefused::of(ErrorCode::NotFound);
            }
        }

        self::refuseLines(ErrorCode::ProductInactive, $lineProducts, static fn (int $product): bool => ! $held[$product]->active);

        $blocked = $this->blocks->blockedAmong(array_keys($held));
        self::refuseLines(ErrorCode::PostingBlocked, $lineProducts, static fn (int $product): bool => in_array($product, $blocked, true));
    }

    /**
     * The refusal of this code, naming every line whose product the test holds for, if any.
     *
     * @param  array<int, int>  $lineProducts
     * @param  Closure(int): bool  $refused
     *
     * @throws CommandRefused
     */
    private static function refuseLines(ErrorCode $code, array $lineProducts, Closure $refused): void
    {
        $lines = [];

        foreach ($lineProducts as $index => $product) {
            if ($refused($product)) {
                $lines[$index] = ['productId' => $product];
            }
        }

        if ($lines !== []) {
            throw new CommandRefused($code, lines: $lines);
        }
    }
}
