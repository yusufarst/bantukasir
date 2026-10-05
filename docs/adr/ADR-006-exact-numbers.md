# ADR-006 — Exact numbers

```text
ID:               ADR-006
TITLE:            Exact numbers
STATUS:           ACCEPTED
DATE:             4 October 2026 (Asia/Jakarta), written in RF-3A (D-055)
DECISION SOURCE:  D-006 (Planner, exact decimal cost); AGENTS rule 7 (money is never floating point);
                  DOMAIN_MODEL BR-NUM-01 to BR-NUM-04
```

## Context

BantuKasir handles three kinds of number: money in whole rupiah (prices, line totals, Sale totals, payments, tendered cash, change, refunds, opening and physical cash), quantities with 0 to 3 decimals in a product's base unit, and cost values with six fractional digits (BR-NUM-01 to BR-NUM-03). No binary floating point may touch any of them, in storage, calculation, transport or display, and a value outside the supported range is rejected, never truncated (BR-NUM-04). PHP floats, JavaScript numbers and some framework casts silently use binary floating point.

## Decision

1. **Kinds and representations.**

   | Kind | Meaning | Database type class | PHP | JSON and browser |
   | --- | --- | --- | --- | --- |
   | Money | Whole rupiah (BR-NUM-01) | 64-bit integer (`bigint`) | `Brick\Math\BigInteger`, or a money value object built on it | Canonical integer string, for example `"75000"` |
   | Quantity | Exact, 0 to 3 decimals as the product's precision allows (BR-NUM-02) | `numeric` with a fixed scale | `Brick\Math\BigDecimal`, in a quantity value object that carries the product's precision | Canonical decimal string, for example `"1.5"` |
   | Cost | Exact, six fractional digits (BR-NUM-03) | `numeric` with scale 6 | `Brick\Math\BigDecimal` with scale 6, in a cost value object that also carries KNOWN or UNKNOWN | Canonical decimal string, or the explicit UNKNOWN marker; never `0` for UNKNOWN (BR-COST-03) |

   The precision and scale of each column, the check constraints and the column list are [SCHEMA](../04-architecture/SCHEMA.md) (RF-3B), within the ranges of [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 11. This ADR fixes the type classes only.
2. **Canonical strings.** A canonical number string has no thousands separator, uses `.` as the decimal point, has no exponent, no leading `+`, no leading zeros other than a single `0` before the point, and no more fractional digits than its kind allows. Inputs that are not canonical are refused by validation, never repaired by guessing. Money, quantity and cost never appear as JSON numbers, in requests, Inertia props or responses.
3. **Validation before arithmetic.** String length, scale and magnitude are checked before any decimal operation or database write (legacy P4 §Angka dan waktu exact). A value with more fractional digits than allowed is **refused**, because PostgreSQL `numeric` would round it silently to the column's scale; a value larger than the column allows would raise an error in the database, but it is refused earlier with a clear validation result.
4. **Arithmetic.** All authoritative calculation happens on the server with Brick Math. A line total is quantity × unit price, rounded half-up once to whole rupiah (`RoundingMode::HALF_UP`); the Sale total is the sum of line totals (BR-NUM-01, BR-SALE-06). Results are range-checked before they are stored. Cost arithmetic aggregates before rounding (BR-NUM-03). The rounding rule for six-decimal cost computation and for rupiah display of cost figures — half-up, once, at the end of each computation — is [ADR-008](ADR-008-cost-allocation-representation.md) section 5 (T-17 e, RF-3B).
5. **Forbidden.** Database `real`, `double precision` and `money` types for these values; PHP `float` and `(float)` casts; Eloquent `float`, `double` and `real` casts; and the `decimal:<n>` cast unless the pinned framework version is verified to keep it free of float conversion (a project cast to Brick types is preferred). In TypeScript, `Number()`, `parseFloat`, unary `+`, and `Intl.NumberFormat` on a `number` are forbidden for these values; formatting works on the canonical string (ADR-005).
6. **Browser previews.** The browser may show previews (a line total, the cart total, change for cash) computed from canonical strings with scaled `BigInt` arithmetic or a vetted decimal library chosen by the foundation Task. A preview is never authoritative: the server recomputes every price, total and change and refuses a mismatched payable total instead of correcting it silently (BR-DOM-06, BR-SALE-05).
7. **Ranges.** The supported ranges for every kind are set once in TECHNICAL_RULES section 11 (BR-NUM-04). They are generous enough never to constrain a plausible shop operation.

## Alternatives considered

| Alternative | Why not chosen |
| --- | --- |
| Floats with rounding at display | Forbidden by AGENTS rule 7 and D-006; binary floats cannot represent most decimal fractions exactly. |
| Money as JSON numbers (exact for integers below 2^53 in JavaScript) | Safe only by accident of magnitude; one rule for all three kinds (strings) is simpler to review and test (REQ-COST-02). |
| Storing money as `numeric` | Possible, but money is whole rupiah only; an integer type states that invariant directly. |

## Consequences

- Every input and output path needs value objects and canonical-string validation; tests cover boundary, maximum and overflow values before any lock is taken (legacy P6 §Batas angka).
- PostgreSQL's silent rounding at a column's scale can never decide a stored value, because the application refuses excess scale first.
- Report aggregates are summed in PostgreSQL exact types or Brick Math; they are formatted from strings.

## Framework conformance

```text
ADR REQUIRED:      YES (records D-006 number handling for every later Task)
DEVIATION:         NONE — AICWDF has no conflicting rule; the project rule (AGENTS rule 7) is stricter than default practice
OWNER APPROVAL:    NOT REQUIRED — D-006 is a planner decision in force; AGENTS rule 7 is project governance
REASON:            exact money, quantity and cost are the core integrity requirement
RISK:              a library or framework cast that converts to float unnoticed; mitigated by the forbidden list and tests
MIGRATION IMPACT:  none — no schema exists; column precision is SCHEMA section 3.2 (RF-3B)
ROLLBACK IMPACT:   none
```

## Verified sources

| Source | Checked | Fact used |
| --- | --- | --- |
| https://www.postgresql.org/docs/current/datatype-numeric.html | 4 October 2026 | `numeric` is exact and recommended for monetary amounts; `real` and `double precision` are inexact; a value exceeding a `numeric` column's scale is rounded, a value exceeding its precision raises an error; `bigint` holds 64-bit integers. |
| https://github.com/brick/math | 4 October 2026 | Brick Math provides immutable, arbitrary-precision `BigInteger`, `BigDecimal` and `BigRational` with an explicit `RoundingMode`; MIT licence; requires a recent PHP 8 version (observed fact, not a pin). |

## Related

- [DOMAIN_MODEL](../02-domain/DOMAIN_MODEL.md) section 7; [COSTING](../02-domain/COSTING.md) section 4; [TECHNICAL_RULES](../06-api-performance/TECHNICAL_RULES.md) section 11.
- Resolved in RF-3B: T-17 e and T-16 ([ADR-008](ADR-008-cost-allocation-representation.md)); column types and bounds in [SCHEMA](../04-architecture/SCHEMA.md) section 3.2.
- Superseded legacy content: `docs/04-architecture/legacy/P4-ARCHITECTURE.md` §Angka dan waktu exact; the numeric part of `docs/04-architecture/legacy/02-ARCHITECTURE.md` §Payment extension boundary dan schema delivery; `docs/06-api-performance/legacy/P6-CONCURRENCY-API.md` §Batas angka, input dan query (number representation).
