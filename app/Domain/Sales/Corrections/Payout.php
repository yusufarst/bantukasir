<?php

namespace App\Domain\Sales\Corrections;

use LogicException;

/**
 * The payout of a refund (SCHEMA section 5.9, `refunds`; BR-RFD-05, BR-RFD-06; D-035, D-045, D-087): its
 * method, CASH or BANK_TRANSFER; for CASH exactly one source, the drawer of one OPEN session or the
 * Owner's own funds, and for BANK_TRANSFER none; the session of a drawer-funded payout; and its reference
 * — the payout actually made, which the Owner types, or for a drawer-funded refund `<Sale number>/<k>`,
 * which the server generates (TASK-034). No rule ties the method to the Sale's own (BR-RFD-05).
 */
final readonly class Payout
{
    public const string CASH = 'CASH';

    public const string BANK_TRANSFER = 'BANK_TRANSFER';

    public const string DRAWER = 'DRAWER';

    public const string OWNER_FUNDS = 'OWNER_FUNDS';

    /** The methods of a payout, the server's allowlist (BR-RFD-05; TR-VAL-04). */
    public const array METHODS = [self::CASH, self::BANK_TRANSFER];

    private function __construct(
        public string $method,
        public ?string $fundingSource,
        public ?int $cashSessionId,
        public string $reference,
    ) {}

    /**
     * The payout that the Owner made from the Owner's own funds in cash, or by bank transfer, with the
     * reference the Owner typed (RC-06; BR-RFD-05, BR-RFD-14): no session is touched (D-035).
     */
    public static function made(string $method, string $reference): self
    {
        return match ($method) {
            self::CASH => new self(self::CASH, self::OWNER_FUNDS, null, $reference),
            self::BANK_TRANSFER => new self(self::BANK_TRANSFER, null, null, $reference),
            default => throw new LogicException('A payout method was validated before the Action (BR-RFD-05).'),
        };
    }

    /**
     * The payout in cash from the drawer of the selected OPEN session, with the reference that the server
     * generated under the Sale's lock (RC-05; BR-RFD-05, BR-RFD-07; D-087; TASK-034).
     */
    public static function fromDrawer(int $cashSessionId, string $reference): self
    {
        return new self(self::CASH, self::DRAWER, $cashSessionId, $reference);
    }
}
