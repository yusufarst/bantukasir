<?php

namespace Tests\Unit\Sales;

use App\Domain\Commands\CommandRefused;
use App\Domain\Sales\PaymentMethods;
use App\Domain\Sales\SaleNumber;
use App\Support\Numbers\Money;
use App\Support\Numbers\NumberOutOfRange;
use App\Support\Numbers\Quantity;
use App\Support\Time\BusinessDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The payment of a Sale, its line totals and its number, with no database (AC-028-03, AC-028-06;
 * SALES_CASH BR-PAY-01 to BR-PAY-07, BR-SALE-06, BR-DOC-01; ARCHITECTURE ARC-30 to ARC-33; SCHEMA
 * section 6, SCH-22; D-036, D-062, D-077; suites POS02, PAYEXT01 and FIN01; TASK-028 section 7, L1):
 * the server's list of methods, exactly CASH and BANK_TRANSFER; the example of L-17, Rp75.000 with
 * Rp100.000 tendered; an exact tender; a tender short of the total refused; a transfer of the exact
 * total with no tendered cash; every other method, STATIC_QRIS included, refused; the line total
 * half-up once; and the number of a Sale by its business date and counter.
 */
final class PaymentMethodsTest extends TestCase
{
    public function test_the_list_holds_cash_and_bank_transfer_alone(): void
    {
        // BR-PAY-07, ARC-30: an explicit list, compared exactly.
        $this->assertSame(['CASH', 'BANK_TRANSFER'], PaymentMethods::ALL);

        foreach (['CASH', 'BANK_TRANSFER'] as $method) {
            $this->assertTrue(PaymentMethods::allows($method), $method);
        }

        foreach (['STATIC_QRIS', 'QRIS', 'CARD', 'E_WALLET', 'cash', 'Cash', ' CASH', 'CASH,BANK_TRANSFER', 'MIXED', 'SPLIT', 'PARTIAL', 'DEPOSIT', 'CREDIT', '', null, 1, ['CASH']] as $method) {
            $this->assertFalse(PaymentMethods::allows($method), (string) json_encode($method));
        }
    }

    public function test_cash_gives_its_change_and_applies_the_total(): void
    {
        // AC-028-03, BR-PAY-02, the example of L-17: total Rp75.000, tendered Rp100.000.
        $payment = PaymentMethods::pay('CASH', Money::of('75000'), Money::of('100000'), null);

        $this->assertSame(['CASH', '75000', '100000', '25000', null], [$payment->method, $payment->applied->toCanonical(), $payment->tendered?->toCanonical(), $payment->change?->toCanonical(), $payment->reference]);

        // "Uang Pas": the exact total gives no change; a Rp0 Sale takes Rp0.
        $exact = PaymentMethods::pay('CASH', Money::of('75000'), Money::of('75000'), null);
        $this->assertSame(['75000', '0'], [$exact->applied->toCanonical(), $exact->change?->toCanonical()]);
        $this->assertSame('0', PaymentMethods::pay('CASH', Money::zero(), Money::zero(), null)->change?->toCanonical());
    }

    public function test_cash_short_of_the_total_is_refused(): void
    {
        // AC-028-03: Rp70.000 for Rp75.000, and one rupiah short.
        foreach (['70000', '74999', '0'] as $tendered) {
            try {
                PaymentMethods::pay('CASH', Money::of('75000'), Money::of($tendered), null);
                $this->fail('A short tender is refused: '.$tendered);
            } catch (CommandRefused $refusal) {
                $this->assertSame(['TENDER_INSUFFICIENT', 'ux.state.tender_low'], [$refusal->error->value, $refusal->messageKey]);
            }
        }
    }

    public function test_a_transfer_takes_the_exact_total_with_no_pending_state(): void
    {
        // BR-PAY-03, D-077: the exact total, an optional reference, no tendered cash, no change.
        $payment = PaymentMethods::pay('BANK_TRANSFER', Money::of('75000'), null, 'TRF-0001');
        $this->assertSame(['BANK_TRANSFER', '75000', null, null, 'TRF-0001'], [$payment->method, $payment->applied->toCanonical(), $payment->tendered, $payment->change, $payment->reference]);
        $this->assertNull(PaymentMethods::pay('BANK_TRANSFER', Money::of('75000'), null, null)->reference);

        // A payment holds its method and its amounts, and no status of any kind (BR-PAY-07).
        $this->assertSame(['method', 'applied', 'tendered', 'change', 'reference'], array_keys(get_object_vars($payment)));
    }

    public function test_every_other_method_is_refused_before_its_amounts_are_read(): void
    {
        // AC-028-03, BR-PAY-01, D-077: STATIC_QRIS is no V1 method; neither is anything else.
        foreach (['STATIC_QRIS', 'QRIS', 'CARD', 'cash', 'MIXED'] as $method) {
            try {
                PaymentMethods::pay($method, Money::of('75000'), Money::of('100000'), 'X');
                $this->fail('A method outside the list is refused: '.$method);
            } catch (CommandRefused $refusal) {
                $this->assertSame(['METHOD_NOT_ALLOWED', 'ux.state.method_not_allowed'], [$refusal->error->value, $refusal->messageKey]);
            }
        }
    }

    public function test_a_line_total_is_rounded_half_up_once_and_its_range_is_held(): void
    {
        // BR-SALE-06, FIN01: 1.5 units at Rp3.333 is Rp4.999,5, which rounds to Rp5.000; a whole
        // half-way line rounds up; the total is the sum of the line totals.
        $this->assertSame('5000', Money::lineTotal(Quantity::of('1.5', 3), Money::of('3333'))->toCanonical());
        $this->assertSame('5', Money::lineTotal(Quantity::of('0.5', 1), Money::of('9'))->toCanonical());
        $this->assertSame('4', Money::lineTotal(Quantity::of('0.25', 2), Money::of('17'))->toCanonical());
        $this->assertSame('60000', Money::sum(Money::lineTotal(Quantity::of('3', 0), Money::of('20000')))->toCanonical());

        $this->expectException(NumberOutOfRange::class);
        Money::lineTotal(Quantity::of('99999999.999', 3), Money::of('999999999999'));
    }

    public function test_a_sale_number_is_its_business_date_and_its_counter_padded_to_six_digits(): void
    {
        // SCH-22: never truncated; the counter starts at 1.
        $this->assertSame('20261108-000001', SaleNumber::of(BusinessDate::fromString('2026-11-08'), 1));
        $this->assertSame('20261231-999999', SaleNumber::of(BusinessDate::fromString('2026-12-31'), 999999));
        $this->assertSame('20270101-1234567', SaleNumber::of(BusinessDate::fromString('2027-01-01'), 1234567));

        $this->expectException(InvalidArgumentException::class);
        SaleNumber::of(BusinessDate::fromString('2026-11-08'), 0);
    }
}
