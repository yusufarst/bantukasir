<?php

namespace App\Domain\Commands;

/**
 * The 32 error codes of TECHNICAL_RULES section 4, each with its HTTP status
 * and the message key of COPY_DECK section 13.1 (ROUTE_CONTRACTS RT-07;
 * UI_STATES section 3; TASK-008 scope item 7). The code is a technical
 * identifier; the words the shop sees are the message key's.
 *
 * Where COPY_DECK gives a code one key per page or command (RECOUNT_REQUIRED,
 * INSUFFICIENT_STOCK, SESSION_NOT_OPEN, BARCODE_NOT_ELIGIBLE,
 * REPLAY_REJECTED), messageKey() is the first one and a refusal names
 * another of variants() (CommandRefused).
 *
 * The client's state comes from the code (INTERACTION_CONTRACTS section 1.3):
 * the body's `outcome` is HELD for IDEMPOTENCY_CONFLICT and EPOCH_STALE, and
 * REJECTED for every other refusal; SERVICE_BUSY and THROTTLED ran nothing
 * that the client may treat as a failure (TR-ERR-01).
 */
enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case CsrfFailed = 'CSRF_FAILED';
    case RecentAuthRequired = 'RECENT_AUTH_REQUIRED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case EpochStale = 'EPOCH_STALE';
    case IntentAbandoned = 'INTENT_ABANDONED';
    case StaleVersion = 'STALE_VERSION';
    case PriceChanged = 'PRICE_CHANGED';
    case RecountRequired = 'RECOUNT_REQUIRED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case ProductInactive = 'PRODUCT_INACTIVE';
    case CategoryInactive = 'CATEGORY_INACTIVE';
    case NoOpenSession = 'NO_OPEN_SESSION';
    case SessionNotOpen = 'SESSION_NOT_OPEN';
    case OnboardingFreeze = 'ONBOARDING_FREEZE';
    case PostingBlocked = 'POSTING_BLOCKED';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case TenderInsufficient = 'TENDER_INSUFFICIENT';
    case RefundBoundExceeded = 'REFUND_BOUND_EXCEEDED';
    case ReturnBoundExceeded = 'RETURN_BOUND_EXCEEDED';
    case DrawerCashInsufficient = 'DRAWER_CASH_INSUFFICIENT';
    case BarcodeNotEligible = 'BARCODE_NOT_ELIGIBLE';
    case DuplicateSku = 'DUPLICATE_SKU';
    case DuplicateBarcode = 'DUPLICATE_BARCODE';
    case CsvInvalid = 'CSV_INVALID';
    case ReplayRejected = 'REPLAY_REJECTED';
    case LinkInvalid = 'LINK_INVALID';
    case Throttled = 'THROTTLED';
    case ServiceBusy = 'SERVICE_BUSY';

    /**
     * The status of TECHNICAL_RULES section 4.
     */
    public function status(): int
    {
        return match ($this) {
            self::Unauthenticated => 401,
            self::CsrfFailed => 419,
            self::RecentAuthRequired => 423,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::IdempotencyConflict, self::EpochStale, self::IntentAbandoned,
            self::StaleVersion, self::PriceChanged, self::RecountRequired => 409,
            self::Throttled => 429,
            self::ServiceBusy => 503,
            default => 422,
        };
    }

    /**
     * Every message key of COPY_DECK section 13.1 for the code, the first
     * being the default.
     *
     * @return non-empty-list<string>
     */
    public function variants(): array
    {
        return match ($this) {
            self::Unauthenticated => ['ux.state.signed_out'],
            self::CsrfFailed => ['ux.state.reload'],
            self::RecentAuthRequired => ['ux.state.recent_auth'],
            self::Forbidden => ['ux.state.forbidden'],
            self::NotFound => ['ux.state.not_found'],
            self::IdempotencyConflict => ['ux.state.held.idempotency_conflict'],
            self::EpochStale => ['ux.state.held.epoch_stale'],
            self::IntentAbandoned => ['ux.state.abandoned'],
            self::StaleVersion => ['ux.state.stale_version'],
            self::PriceChanged => ['ux.state.price_changed'],
            self::RecountRequired => ['ux.state.recount', 'ux.state.recount.unresolved_checkouts'],
            self::ValidationFailed => ['ux.state.validation'],
            self::InsufficientStock => ['ux.state.insufficient_stock', 'ux.state.insufficient_stock.reversal'],
            self::ProductInactive => ['ux.state.inactive.product'],
            self::CategoryInactive => ['ux.state.inactive.category'],
            self::NoOpenSession => ['ux.state.no_session'],
            self::SessionNotOpen => ['ux.state.session_closed.till', 'ux.state.session_closed.close', 'ux.state.session_closed.refund'],
            self::OnboardingFreeze => ['ux.state.onboarding'],
            self::PostingBlocked => ['ux.state.blocked'],
            self::MethodNotAllowed => ['ux.state.method_not_allowed'],
            self::TenderInsufficient => ['ux.state.tender_low'],
            self::RefundBoundExceeded => ['ux.state.refund_bound.money'],
            self::ReturnBoundExceeded => ['ux.state.refund_bound.goods'],
            self::DrawerCashInsufficient => ['ux.state.drawer_short'],
            self::BarcodeNotEligible => ['ux.state.barcode_ineligible'],
            self::DuplicateSku => ['ux.state.duplicate_code.sku'],
            self::DuplicateBarcode => ['ux.state.duplicate_code.barcode'],
            self::CsvInvalid => ['ux.state.csv_invalid'],
            self::ReplayRejected => ['ux.state.replay_rejected.cost', 'ux.state.replay_rejected.reversal'],
            self::LinkInvalid => ['ux.state.link_invalid'],
            self::Throttled => ['ux.state.throttled'],
            self::ServiceBusy => ['ux.state.uncertain'],
        };
    }

    public function messageKey(): string
    {
        return $this->variants()[0];
    }

    /**
     * The body's `outcome` (ROUTE_CONTRACTS RT-07): HELD, which the client
     * never repeats, or REJECTED.
     */
    public function bodyOutcome(): string
    {
        return $this === self::IdempotencyConflict || $this === self::EpochStale ? 'HELD' : 'REJECTED';
    }
}
