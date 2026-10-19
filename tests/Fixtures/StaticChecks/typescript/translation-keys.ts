// Negative samples of the typed t() (ADR-005 item 5, TASK-003): an unknown
// key, a missing placeholder, an unexpected one and a missing value fail.
import { t } from '@/lib/i18n';

export const unknown = t('ux.format.month.smarch');
export const missing = t('ux.format.variance');
export const extra = t('ux.format.variance_short', { uang: 'Rp0' });
export const partial = t('ux.format.variance', { uang: 'Rp0' });
