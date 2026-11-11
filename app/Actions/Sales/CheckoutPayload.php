<?php

namespace App\Actions\Sales;

use App\Support\Numbers\Money;
use LogicException;

/**
 * RC-01's payload and reviewed price revisions as the Action reads them from an envelope that its
 * FormRequest validated (INTERACTION_CONTRACTS RC-01): the session, the lines in the order of the
 * cart, each with the revision at the same index of `expected.lines`, and the payment — its method,
 * the tendered cash of CASH, the optional reference of BANK_TRANSFER and the optional customer name,
 * an empty text read as none. The method is judged by PaymentMethods, the one place of its rules
 * (ARC-30).
 */
final readonly class CheckoutPayload
{
    /**
     * @param  list<CheckoutLine>  $lines
     */
    private function __construct(
        public int $sessionId,
        public array $lines,
        public string $method,
        public ?Money $tendered,
        public ?string $reference,
        public ?string $customerName,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $expected
     */
    public static function of(array $payload, array $expected): self
    {
        $session = $payload['sessionId'] ?? null;
        $lines = $payload['lines'] ?? null;
        $reviewed = $expected['lines'] ?? null;
        $payment = $payload['payment'] ?? null;

        if (! is_int($session) || ! is_array($lines) || ! is_array($reviewed) || count($lines) !== count($reviewed) || ! is_array($payment)) {
            throw new LogicException('The envelope was validated before the Action.');
        }

        $reviewed = array_values($reviewed);
        $read = [];

        foreach (array_values($lines) as $index => $line) {
            $values = is_array($line) ? $line : [];
            $seen = $reviewed[$index] ?? null;
            $revision = is_array($seen) ? ($seen['priceRevisionId'] ?? null) : null;
            $product = $values['productId'] ?? null;
            $quantity = $values['quantity'] ?? null;

            if (! is_int($product) || ! is_string($quantity) || ! is_int($revision)) {
                throw new LogicException('A line of the envelope was validated before the Action.');
            }

            $read[] = new CheckoutLine($product, $quantity, $revision);
        }

        $method = $payment['method'] ?? null;
        $tendered = $payment['tendered'] ?? null;
        $reference = $payment['reference'] ?? null;
        $customer = $payment['customerName'] ?? null;

        if (! is_string($method) || ($tendered !== null && ! is_string($tendered)) || ($reference !== null && ! is_string($reference)) || ($customer !== null && ! is_string($customer))) {
            throw new LogicException('The payment of the envelope was validated before the Action.');
        }

        return new self(
            $session,
            $read,
            $method,
            $tendered === null || $tendered === '' ? null : Money::of($tendered),
            $reference === null || $reference === '' ? null : $reference,
            $customer === null || $customer === '' ? null : $customer,
        );
    }
}
