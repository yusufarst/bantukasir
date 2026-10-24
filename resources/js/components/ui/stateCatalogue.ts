// The catalogue of the states of UI_STATES section 2 (UXS-01 to UXS-50) as the
// design system presents them (DESIGN_SYSTEM section 9; TASK-005 scope item
// 5): each state's copy keys of COPY_DECK section 12, its tone, how urgently
// it is announced (UXA-17) and the component that presents it. Pages place
// the states where UI_STATES section 6 says; a body that COPY_DECK leaves to
// the page, or that has placeholders, is given by the page.
import type { TranslationKey } from '@/types/translation-keys';
import type { Urgency } from './Notice';
import type { Tone } from './Status';

export type StatePresentation =
    /** A region or figure that is loading: skeleton and the loading text (UXS-01). */
    | 'loading'
    /** A centred message in the content area (UXS-02, UXS-03). */
    | 'empty'
    /** A notice with its title, body and action (DESIGN_SYSTEM 8.6). */
    | 'notice'
    /** The recovery panel of an uncertain or held request (UXS-14, UXS-15). */
    | 'recovery'
    /** A mark in place of a value, never zero or blank (UXS-24, UXS-26, UXS-32, UXS-43). */
    | 'mark'
    /** A control's own state: the busy button (UXS-06) or the disabled reason (UXS-13). */
    | 'control'
    /** A dialog of UXA-05 (UXS-11, UXS-23, UXS-45). */
    | 'dialog';

export interface StateEntry {
    readonly title: TranslationKey;
    /** The body when COPY_DECK gives one without placeholders. */
    readonly body?: TranslationKey;
    readonly tone: Tone;
    readonly urgency: Urgency;
    readonly presentation: StatePresentation;
    /** The state's other texts: bodies with placeholders and named texts, which the page fills. */
    readonly texts?: readonly TranslationKey[];
}

export type StateId =
    | 'UXS-01' | 'UXS-02' | 'UXS-03' | 'UXS-04' | 'UXS-05' | 'UXS-06' | 'UXS-07' | 'UXS-08' | 'UXS-09' | 'UXS-10'
    | 'UXS-11' | 'UXS-12' | 'UXS-13' | 'UXS-14' | 'UXS-15' | 'UXS-15-epoch' | 'UXS-16' | 'UXS-17' | 'UXS-18' | 'UXS-19'
    | 'UXS-19-unresolved' | 'UXS-20' | 'UXS-21' | 'UXS-22' | 'UXS-23' | 'UXS-23-absolute' | 'UXS-24' | 'UXS-25' | 'UXS-26' | 'UXS-27'
    | 'UXS-28' | 'UXS-29' | 'UXS-30-till' | 'UXS-30-close' | 'UXS-30-refund' | 'UXS-31' | 'UXS-31-reversal' | 'UXS-32-product' | 'UXS-32-category' | 'UXS-32-label'
    | 'UXS-33' | 'UXS-34' | 'UXS-35-money' | 'UXS-35-goods' | 'UXS-36' | 'UXS-37-sku' | 'UXS-37-barcode' | 'UXS-38' | 'UXS-39' | 'UXS-40-cost'
    | 'UXS-40-reversal' | 'UXS-41' | 'UXS-42-sign-in' | 'UXS-42-confirmation' | 'UXS-42-current-password' | 'UXS-43' | 'UXS-44' | 'UXS-45' | 'UXS-46' | 'UXS-47'
    | 'UXS-48' | 'UXS-49' | 'UXS-50';

/** Every state, and each variant that COPY_DECK section 12 words separately. */
export const STATE_CATALOGUE: Readonly<Record<StateId, StateEntry>> = {
    'UXS-01': { title: 'ux.state.loading.title', body: 'ux.state.loading.body', tone: 'neutral', urgency: 'status', presentation: 'loading' },
    'UXS-02': { title: 'ux.state.empty.title', tone: 'neutral', urgency: 'none', presentation: 'empty' },
    'UXS-03': { title: 'ux.state.no_results.title', body: 'ux.state.no_results.body', tone: 'neutral', urgency: 'status', presentation: 'empty' },
    'UXS-04': { title: 'ux.state.committed.title', tone: 'success', urgency: 'status', presentation: 'notice' },
    'UXS-05': { title: 'ux.state.validation.title', body: 'ux.state.validation.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-06': { title: 'ux.state.submitting.title', tone: 'neutral', urgency: 'status', presentation: 'control', texts: ['ux.state.submitting.still'] },
    'UXS-07': { title: 'ux.state.forbidden.title', body: 'ux.state.forbidden.body', tone: 'neutral', urgency: 'none', presentation: 'notice' },
    'UXS-08': { title: 'ux.state.not_found.title', body: 'ux.state.not_found.body', tone: 'neutral', urgency: 'none', presentation: 'notice' },
    'UXS-09': { title: 'ux.state.signed_out.title', body: 'ux.state.signed_out.body', tone: 'info', urgency: 'status', presentation: 'notice' },
    'UXS-10': { title: 'ux.state.reload.title', body: 'ux.state.reload.body', tone: 'warning', urgency: 'alert', presentation: 'notice' },
    'UXS-11': { title: 'ux.state.recent_auth.title', body: 'ux.state.recent_auth.body', tone: 'neutral', urgency: 'none', presentation: 'dialog', texts: ['ux.state.recent_auth.confirm', 'ux.state.recent_auth.password'] },
    'UXS-12': { title: 'ux.state.throttled.title', tone: 'warning', urgency: 'alert', presentation: 'notice', texts: ['ux.state.throttled.body'] },
    'UXS-13': { title: 'ux.state.disabled.title', tone: 'neutral', urgency: 'none', presentation: 'control' },
    'UXS-14': { title: 'ux.state.uncertain.title', body: 'ux.state.uncertain.body', tone: 'warning', urgency: 'alert', presentation: 'recovery' },
    'UXS-15': { title: 'ux.state.held.idempotency_conflict.title', body: 'ux.state.held.idempotency_conflict.body', tone: 'warning', urgency: 'alert', presentation: 'recovery' },
    'UXS-15-epoch': { title: 'ux.state.held.epoch_stale.title', body: 'ux.state.held.epoch_stale.body', tone: 'warning', urgency: 'alert', presentation: 'recovery' },
    'UXS-16': { title: 'ux.state.abandoned.title', body: 'ux.state.abandoned.body', tone: 'neutral', urgency: 'alert', presentation: 'notice' },
    'UXS-17': { title: 'ux.state.stale_version.title', body: 'ux.state.stale_version.body', tone: 'warning', urgency: 'alert', presentation: 'notice' },
    'UXS-18': { title: 'ux.state.price_changed.title', tone: 'warning', urgency: 'alert', presentation: 'notice', texts: ['ux.state.price_changed.body', 'ux.state.price_changed.line', 'ux.state.price_changed.total'] },
    'UXS-19': { title: 'ux.state.recount.title', body: 'ux.state.recount.body', tone: 'warning', urgency: 'alert', presentation: 'notice' },
    'UXS-19-unresolved': { title: 'ux.state.recount.unresolved_checkouts.title', body: 'ux.state.recount.unresolved_checkouts.body', tone: 'warning', urgency: 'alert', presentation: 'notice' },
    'UXS-20': { title: 'ux.state.offline.title', body: 'ux.state.offline.body', tone: 'warning', urgency: 'alert', presentation: 'notice' },
    'UXS-21': { title: 'ux.state.stale_read.title', tone: 'warning', urgency: 'none', presentation: 'notice', texts: ['ux.state.stale_read.body'] },
    'UXS-22': { title: 'ux.state.load_failed.title', body: 'ux.state.load_failed.body', tone: 'danger', urgency: 'status', presentation: 'notice' },
    'UXS-23': { title: 'ux.state.session_warning.title', tone: 'warning', urgency: 'alert', presentation: 'dialog', texts: ['ux.state.session_warning.body', 'ux.state.session_warning.stay'] },
    'UXS-23-absolute': { title: 'ux.state.session_warning.absolute.title', tone: 'warning', urgency: 'alert', presentation: 'notice', texts: ['ux.state.session_warning.absolute.body'] },
    'UXS-24': { title: 'ux.state.cost_unknown.title', tone: 'pending', urgency: 'none', presentation: 'mark' },
    'UXS-25': { title: 'ux.state.cost_incomplete.title', body: 'ux.state.cost_incomplete.body', tone: 'pending', urgency: 'none', presentation: 'notice' },
    'UXS-26': { title: 'ux.state.owner_corrected.title', tone: 'info', urgency: 'none', presentation: 'mark' },
    'UXS-27': { title: 'ux.state.onboarding.title', body: 'ux.state.onboarding.body', tone: 'pending', urgency: 'none', presentation: 'notice' },
    'UXS-28': { title: 'ux.state.blocked.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.blocked.body', 'ux.state.blocked.staff', 'ux.state.blocked.open_incident'] },
    'UXS-29': { title: 'ux.state.no_session.title', body: 'ux.state.no_session.body', tone: 'info', urgency: 'none', presentation: 'notice' },
    'UXS-30-till': { title: 'ux.state.session_closed.till.title', body: 'ux.state.session_closed.till.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-30-close': { title: 'ux.state.session_closed.close.title', body: 'ux.state.session_closed.close.body', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.session_closed.open_report'] },
    'UXS-30-refund': { title: 'ux.state.session_closed.refund.title', body: 'ux.state.session_closed.refund.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-31': { title: 'ux.state.insufficient_stock.title', body: 'ux.state.insufficient_stock.body', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.insufficient_stock.line'] },
    'UXS-31-reversal': { title: 'ux.state.insufficient_stock.reversal.title', body: 'ux.state.insufficient_stock.reversal.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-32-product': { title: 'ux.state.inactive.product.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.inactive.product.body'] },
    'UXS-32-category': { title: 'ux.state.inactive.category.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.inactive.category.body'] },
    'UXS-32-label': { title: 'ux.state.inactive.label', tone: 'neutral', urgency: 'none', presentation: 'mark' },
    'UXS-33': { title: 'ux.state.unknown_code.title', tone: 'warning', urgency: 'status', presentation: 'notice', texts: ['ux.state.unknown_code.body'] },
    'UXS-34': { title: 'ux.state.tender_low.title', tone: 'warning', urgency: 'none', presentation: 'notice', texts: ['ux.state.tender_low.body'] },
    'UXS-35-money': { title: 'ux.state.refund_bound.money.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.refund_bound.money.body'] },
    'UXS-35-goods': { title: 'ux.state.refund_bound.goods.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.refund_bound.goods.body'] },
    'UXS-36': { title: 'ux.state.drawer_short.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.drawer_short.body'] },
    'UXS-37-sku': { title: 'ux.state.duplicate_code.sku.title', body: 'ux.state.duplicate_code.sku.body', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.duplicate_code.open'] },
    'UXS-37-barcode': { title: 'ux.state.duplicate_code.barcode.title', body: 'ux.state.duplicate_code.barcode.body', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.duplicate_code.open'] },
    'UXS-38': { title: 'ux.state.barcode_ineligible.title', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-39': { title: 'ux.state.csv_invalid.title', tone: 'danger', urgency: 'alert', presentation: 'notice', texts: ['ux.state.csv_invalid.body'] },
    'UXS-40-cost': { title: 'ux.state.replay_rejected.cost.title', body: 'ux.state.replay_rejected.cost.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-40-reversal': { title: 'ux.state.replay_rejected.reversal.title', body: 'ux.state.replay_rejected.reversal.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-41': { title: 'ux.state.link_invalid.title', body: 'ux.state.link_invalid.body', tone: 'danger', urgency: 'none', presentation: 'notice' },
    'UXS-42-sign-in': { title: 'ux.state.signin_failed.sign_in.title', body: 'ux.state.signin_failed.sign_in.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-42-confirmation': { title: 'ux.state.signin_failed.confirmation.title', body: 'ux.state.signin_failed.confirmation.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-42-current-password': { title: 'ux.state.signin_failed.current_password.title', body: 'ux.state.signin_failed.current_password.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-43': { title: 'ux.state.unverified.label', tone: 'neutral', urgency: 'none', presentation: 'mark' },
    'UXS-44': { title: 'ux.state.print_failed.title', body: 'ux.state.print_failed.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-45': { title: 'ux.state.unsaved.title', body: 'ux.state.unsaved.body', tone: 'warning', urgency: 'none', presentation: 'dialog', texts: ['ux.state.unsaved.body_receiving', 'ux.state.unsaved.body_import'] },
    'UXS-46': { title: 'ux.state.method_not_allowed.title', body: 'ux.state.method_not_allowed.body', tone: 'danger', urgency: 'alert', presentation: 'notice' },
    'UXS-47': { title: 'ux.state.similar_product.title', body: 'ux.state.similar_product.body', tone: 'warning', urgency: 'status', presentation: 'notice' },
    'UXS-48': { title: 'ux.state.no_register.title', body: 'ux.state.no_register.body', tone: 'warning', urgency: 'none', presentation: 'notice' },
    'UXS-49': { title: 'ux.state.link_gone.title', body: 'ux.state.link_gone.body', tone: 'neutral', urgency: 'none', presentation: 'notice' },
    'UXS-50': { title: 'ux.state.onboarding_held.title', body: 'ux.state.onboarding_held.body', tone: 'pending', urgency: 'none', presentation: 'notice' },
};
