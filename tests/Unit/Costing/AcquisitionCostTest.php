<?php

namespace Tests\Unit\Costing;

use App\Domain\Costing\AcquisitionCost;
use App\Support\Numbers\NumberOutOfRange;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The cost that a stock increase carries (AC-021-02; BR-COST-02 to BR-COST-04; INTERACTION_CONTRACTS
 * RC-07): KNOWN with its amount; UNKNOWN with none, never zero; an explicit zero only with its
 * evidence or reason.
 */
final class AcquisitionCostTest extends TestCase
{
    public function test_a_known_cost_has_its_amount_and_no_note(): void
    {
        $cost = AcquisitionCost::fromPayload(['status' => 'KNOWN', 'amount' => '1250.5']);

        $this->assertSame(['KNOWN', '1250.5', null, false], [$cost->status(), $cost->amount(), $cost->zeroNote, $cost->isExplicitZero()]);
    }

    public function test_an_unknown_cost_has_no_amount_and_is_never_a_zero(): void
    {
        $cost = AcquisitionCost::fromPayload(['status' => 'UNKNOWN']);

        $this->assertSame(['UNKNOWN', null, null, false], [$cost->status(), $cost->amount(), $cost->zeroNote, $cost->isExplicitZero()]);
        $this->assertFalse($cost->cost->isKnown());
    }

    public function test_an_explicit_zero_keeps_its_evidence_in_any_form_of_zero(): void
    {
        foreach (['0', '0.0', '0.000000'] as $zero) {
            $cost = AcquisitionCost::fromPayload(['status' => 'KNOWN', 'amount' => $zero, 'zeroEvidence' => 'Hadiah pemasok']);

            $this->assertSame(['KNOWN', '0', 'Hadiah pemasok', true], [$cost->status(), $cost->amount(), $cost->zeroNote, $cost->isExplicitZero()], $zero);
        }
    }

    public function test_an_explicit_zero_without_its_evidence_never_reaches_the_ledger(): void
    {
        foreach ([['status' => 'KNOWN', 'amount' => '0'], ['status' => 'KNOWN', 'amount' => '0', 'zeroEvidence' => null]] as $cost) {
            try {
                AcquisitionCost::fromPayload($cost);
                $this->fail('An explicit zero passed without its evidence.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_cost_of_any_other_shape_was_refused_before_the_action(): void
    {
        foreach ([[], ['status' => 'OTHER'], ['status' => 'KNOWN'], ['status' => 'KNOWN', 'amount' => 100]] as $cost) {
            try {
                AcquisitionCost::fromPayload($cost);
                $this->fail('A cost of another shape passed.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_cost_past_the_range_of_one_evidence_is_refused(): void
    {
        $this->assertSame('999999999999.999999', AcquisitionCost::fromPayload(['status' => 'KNOWN', 'amount' => '999999999999.999999'])->amount());

        $this->expectException(NumberOutOfRange::class);
        AcquisitionCost::fromPayload(['status' => 'KNOWN', 'amount' => '1000000000000']);
    }
}
