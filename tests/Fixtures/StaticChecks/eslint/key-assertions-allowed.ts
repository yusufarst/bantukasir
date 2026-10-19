// Compliant samples of the rule against asserted translation keys (TASK-003
// finding A-06): a key typed TranslationKey without an assertion, for example
// a value of a typed map, which the typecheck proves exists; satisfies; and
// assertions to other types. Nothing here is refused.
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

type Status = 'even' | 'over' | 'short';

const labels: Readonly<Record<Status, TranslationKey>> = {
    even: 'ux.format.variance_even',
    over: 'ux.format.variance_over',
    short: 'ux.format.variance_short',
};

const months = ['ux.format.month.january', 'ux.format.month.february'] as const satisfies readonly TranslationKey[];

declare const status: Status;
declare const element: unknown;

export const a = t(labels[status]);
export const b = months.map((month) => t(month));
export const c = t('ux.format.month.october');
export function label(key: TranslationKey): string {
    return t(key);
}
export const d = element as HTMLElement | null;
export const e = { mode: 'full' } as const;
