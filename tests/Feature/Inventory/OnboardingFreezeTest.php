<?php

namespace Tests\Feature\Inventory;

use App\Domain\Commands\CommandRefused;
use App\Domain\Commands\ErrorCode;
use Closure;
use Illuminate\Database\Connection;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * The onboarding freeze against every posting family (AC-035-01; G-10; BR-INV-11, BR-INV-24; INTERACTION_CONTRACTS
 * RC-01, RC-05 to RC-10; TECHNICAL_RULES section 7.2; TASK-035 scope item 6; suites OPEN01, INV03): after a real
 * start of onboarding through RC-12, a receiving, a manual issue, an adjustment, a checkout, a saleable return —
 * paid from the Owner's funds or from the drawer — and a reversal of a RECEIPT or a MANUAL_ISSUE are each refused
 * ONBOARDING_FREEZE with nothing written, while an OPENING may be reversed (D-087); after the release through RC-13,
 * every family posts again.
 */
final class OnboardingFreezeTest extends DatabaseTestCase
{
    use CommandRequests;

    /** The tables that a refused posting leaves as they were. */
    private const array FACTS = [
        'stock_movements', 'stock_balances', 'receiving_documents', 'manual_issues', 'adjustments', 'reversals', 'sales', 'sale_lines', 'payments', 'sale_corrections',
        'acquisition_cost_evidence', 'issue_cost_allocations', 'return_cost_allocations', 'inventory_valuations', 'audit_events',
    ];

    private int $pen = 0;

    private ?Connection $watcher = null;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->pen = TokoContoh::product('PEN-01', ['categoryId' => TokoContoh::category(), 'name' => 'Pulpen Hitam']);
    }

    public function test_every_posting_family_is_refused_while_onboarding_is_active_and_posts_again_after_the_release(): void
    {
        // Before the start: 10 pens received, 1 issued, a Sale of 2 in an OPEN session.
        $receipt = TokoContoh::movementOf(TokoContoh::stock($this->pen, '10', '10000'));
        $issue = TokoContoh::movementOf(TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]), 'MANUAL_ISSUE');
        $session = TokoContoh::openSession('Hendra', TokoContoh::register());
        $sale = TokoContoh::sell('Hendra', $session, [['productId' => $this->pen, 'quantity' => '2']]);
        $line = TokoContoh::saleLinesOf($sale)[0] ?? 0;

        // The real start (RC-12), with the Owner's recent authentication.
        $this->as('Hendra');
        $this->postJson('/user/confirm-password', ['password' => $this->people['Hendra']['password']])->assertStatus(201);
        $this->postJson('/commands/onboarding.start', $this->envelope([], ['operation' => 'onboarding.start', 'expected' => ['onboardingVersion' => 1]]))->assertStatus(201);
        $before = $this->facts();

        // The route answers the freeze too, with its state (UXS-27).
        $this->postJson('/commands/receiving.confirm', $this->envelope(
            ['reference' => null, 'documentDate' => null, 'lines' => [['productId' => $this->pen, 'quantity' => '1', 'cost' => ['status' => 'KNOWN', 'amount' => '1000']]]],
            ['operation' => 'receiving.confirm', 'expected' => ['products' => [['productId' => $this->pen, 'version' => 1]]]],
        ))->assertStatus(422)->assertJsonPath('code', 'ONBOARDING_FREEZE');

        foreach ([
            'receiving' => fn (): int => TokoContoh::stock($this->pen, '1', '1000'),
            'manual issue' => fn (): int => TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]),
            'adjustment' => fn (): int => TokoContoh::adjust([['productId' => $this->pen, 'direction' => 'DECREASE', 'quantity' => '1']]),
            'checkout' => fn (): int => TokoContoh::sell('Hendra', $session, [['productId' => $this->pen, 'quantity' => '1']]),
            'saleable return' => static fn (): int => TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]),
            'saleable return paid from the drawer' => static fn (): int => TokoContoh::refundFromDrawer($sale, $session, [['saleLineId' => $line, 'refundAmount' => '7500', 'returnedSaleable' => '1']]),
            'reversal of a RECEIPT' => static fn (): int => TokoContoh::reverse($receipt),
            'reversal of a MANUAL_ISSUE' => static fn (): int => TokoContoh::reverse($issue),
        ] as $family => $posting) {
            $this->assertFrozen($posting, $family);
        }

        $this->assertSame($before, $this->facts());

        // An OPENING may be reversed while onboarding is active, as the correction of an entry (D-087).
        $book = TokoContoh::product('BUKU-01', ['categoryId' => TokoContoh::category('Buku'), 'name' => 'Buku Tulis']);
        TokoContoh::reverse(TokoContoh::openingStock($book, '3'));
        $this->assertSame(['OPENING', 'REVERSAL'], $this->watcher()->table('stock_movements')->where('product_id', $book)->orderBy('product_sequence')->pluck('movement_type')->all());

        // After the release (RC-13) every family posts again.
        TokoContoh::releaseOnboarding();
        TokoContoh::stock($this->pen, '5', '5000');
        TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]);
        TokoContoh::adjust([['productId' => $this->pen, 'direction' => 'DECREASE', 'quantity' => '1']]);
        TokoContoh::sell('Hendra', $session, [['productId' => $this->pen, 'quantity' => '1']]);
        TokoContoh::correct($sale, [['saleLineId' => $line, 'returnedSaleable' => '1']]);
        TokoContoh::reverse(TokoContoh::movementOf(TokoContoh::stock($this->pen, '3', '3000')));
        TokoContoh::reverse(TokoContoh::movementOf(TokoContoh::issue([['productId' => $this->pen, 'quantity' => '1']]), 'MANUAL_ISSUE'));

        // 10 − 1 − 2 before; + 5 − 1 − 1 − 1 + 1 + 3 − 3 − 1 + 1 after.
        $this->assertSame('10.000', $this->watcher()->table('stock_balances')->where('product_id', $this->pen)->value('on_hand'));
    }

    /**
     * The posting is refused ONBOARDING_FREEZE (G-10).
     *
     * @param  Closure(): int  $posting
     */
    private function assertFrozen(Closure $posting, string $family): void
    {
        try {
            $posting();
            $this->fail($family.' posted while onboarding is active.');
        } catch (CommandRefused $refusal) {
            $this->assertSame(ErrorCode::OnboardingFreeze, $refusal->error, $family);
        }
    }

    /** One independent session for every read of the test. */
    private function watcher(): Connection
    {
        return $this->watcher ??= $this->observer();
    }

    /**
     * The facts of every posting family, by their number of rows.
     *
     * @return array<string, int>
     */
    private function facts(): array
    {
        $counts = [];

        foreach (self::FACTS as $table) {
            $counts[$table] = $this->watcher()->table($table)->count();
        }

        return $counts;
    }
}
