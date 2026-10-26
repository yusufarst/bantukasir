import type { StateId } from '@/components/ui/stateCatalogue';
import { formatDuration } from '@/lib/format';
import { t } from '@/lib/i18n';

// The answers of the authentication endpoints that are not the page's own
// result (ROUTE_CONTRACTS RT-09; TECHNICAL_RULES section 4; UI_STATES sections
// 3 and 5; TASK-006): THROTTLED with its retry time (UXS-12), CSRF_FAILED
// (UXS-10), and every other failure to get an answer — a lost connection, a
// timeout or a server error — which the page shows as its own lost answer.

/** The part of Inertia's HTTP exception that the pages read. */
export interface HttpAnswer {
    readonly status: number;
    readonly headers: Readonly<Record<string, string>>;
}

export type AuthProblem = { readonly kind: 'throttled'; readonly seconds: bigint } | { readonly kind: 'reload' } | { readonly kind: 'lost' };

/** The states of the authentication pages (UI_STATES section 6). */
export const AUTH_STATES = {
    signedOut: 'UXS-09',
    reload: 'UXS-10',
    throttled: 'UXS-12',
    offline: 'UXS-20',
    linkInvalid: 'UXS-41',
    signInFailed: 'UXS-42-sign-in',
    currentPasswordRefused: 'UXS-42-current-password',
} as const satisfies Readonly<Record<string, StateId>>;

/** The retry time of a throttled answer, in whole seconds; 60 when the header is missing. */
function retryAfter(headers: Readonly<Record<string, string>>): bigint {
    const value = headers['retry-after'] ?? headers['Retry-After'];

    return value !== undefined && /^[0-9]{1,6}$/.test(value) ? BigInt(value) : 60n;
}

export function problemOf(answer: HttpAnswer): AuthProblem {
    if (answer.status === 429) {
        return { kind: 'throttled', seconds: retryAfter(answer.headers) };
    }

    if (answer.status === 419) {
        return { kind: 'reload' };
    }

    return { kind: 'lost' };
}

/** The body of UXS-12 with the retry time: "Belum ada yang diproses. Coba lagi dalam 60 detik." */
export function throttledBody(seconds: bigint): string {
    return t('ux.state.throttled.body', { durasi: formatDuration(seconds, 'second') });
}
