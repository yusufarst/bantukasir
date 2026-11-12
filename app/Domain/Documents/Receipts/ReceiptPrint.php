<?php

namespace App\Domain\Documents\Receipts;

/**
 * A committed print request of a receipt (RC-44): the size asked for, `58`, `80` or `A4`, and
 * whether it is a copy, every print request of the Sale after its first (DC-01; BR-DOC-04).
 */
final readonly class ReceiptPrint
{
    public function __construct(public bool $copy, public string $size) {}
}
