# 15 â€” Goods and service profitability

Owns management-reporting semantics, costing and completeness. This is not full accounting.

17 owns orders/payments/fulfillment; 06 owns physical stock; 04 owns private financial access.

## Keep these concepts separate

| UI metric | Meaning |
| --- | --- |
| Nilai Pesanan | Effective confirmed order value after authorized revisions |
| Pembayaran Masuk | Customer money recorded in the selected period; includes DP/partial/final payments |
| Sisa Tagihan | Commercial outstanding amount on open orders; management metric, not a full accounting AR ledger |
| Pendapatan Terealisasi | Value recognized from fulfilled GOODS and completed SERVICE obligations |
| HPP Barang | Matched cost of fulfilled GOODS using MWA |
| Biaya Langsung Jasa | Verified direct service costs |
| Laba Kotor | Recognized revenue minus matched HPP/direct service cost when complete |

**DP/payment received is not automatically revenue. Revenue is not automatically cash received.**

Opening float, paid-in/out and cashier variance are cash-control facts, not revenue/profit.

## Recognition baseline

### GOODS

Recognize goods revenue when verified handover/fulfillment occurs. The linked inventory ISSUE and fulfillment quantity are the physical evidence. Booking, reservation, invoice/receipt rendering or DP alone is insufficient.

Partial fulfillment recognizes only the fulfilled quantity/value.

### SERVICE

Recognize service revenue on verified `ServiceCompletion` for the relevant service line/quantity. Booking, schedule, milestone progress and payment alone do not recognize revenue.

If the business later needs separately priced/accepted milestones to recognize revenue independently, define that policy explicitly before implementation. Core does not infer it from percent progress.

## Goods costing

Use perpetual moving weighted average per interchangeable GOODS SKU across company locations.

```text
Receipt q with verified cost c: Q' = Q + q; V' = V + c
Average A = V / Q
Fulfillment issue q: HPP allocation = q * A
```

Transfers do not change company Q/V. Unknown cost blocks downstream complete HPP for that SKU; do not continue with a guessed old average.

Keep exact decimal arithmetic. Physical posting remains valid when financial cost evidence is incomplete.

## Service direct cost

ServiceCostEvidence may include verified direct labor, subcontract, consumed materials and other direct job cost with source/reference and version.

Blank is unknown, not zero. Explicit zero requires evidence/reason.

Company-stock material consumed for a service uses a real inventory issue and unique cost allocation so the same material cost is not counted twice.

Service refund does not automatically erase labor/material already incurred.

## Payments and outstanding

PaymentRecord is append-only.

For an order:
`outstanding = effective order total - net applied customer payments`

Refund/credit handling must keep signs and linkage explicit. A revision that would make outstanding negative must either include an explicit refund/credit disposition or be rejected for review.

Cash tender/change may be recorded for a cash payment, but only the applied amount reduces outstanding.

## Refunds and returns

Original commercial/financial facts remain immutable. Corrections append linked events.

- money refund without goods return: reduce commercial payment/revenue as applicable; no stock receipt;
- verified saleable goods return: linked RETURN RECEIPT and historical-cost recovery;
- service refund: reduce service revenue; incurred direct cost remains unless separately recovered/evidenced;
- price credit: revenue correction only, no stock movement.

Never use an inventory reversal to bypass commercial limits.

## Reports and periods

Periods use Asia/Jakarta business boundaries converted server-side to UTC:
- Hari Ini
- 7 Hari
- Bulan Ini
- Bulan Lalu
- Rentang Tanggal

Owner views may show:
- order value;
- payments received;
- outstanding balance;
- recognized goods/service revenue;
- goods HPP;
- direct service cost;
- goods/service/combined gross profit;
- gross margin where applicable.

Current LOW/OUT and active jobs remain current-state facts and are not period-filtered unless the specific view says so.

## Completeness

A report snapshot records scope, watermark, evidence versions, calculatedAt and completeness.

Presentation:
- feature unavailable â†’ hide or **Belum Aktif**;
- revenue known but cost incomplete â†’ show revenue plus **Data biaya belum lengkap**, no fake gross;
- complete â†’ show actual HPP/cost/gross;
- failed/stale query â†’ error/stale state, never zero.

No hidden exclusion of missing-cost or loss-making transactions.

## Net profit

Net profit is outside Core. A truthful net-profit feature needs operating expenses, payroll, rent/utilities, depreciation, financing/interest, tax, non-sale losses/variances, liabilities/accruals and period-closing policy.

Do not label gross profit or cash surplus as **Laba Bersih**.
