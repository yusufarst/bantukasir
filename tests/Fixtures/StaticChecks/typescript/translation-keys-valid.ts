// Compliant sample of the typed t() (TASK-003): a key of lang/id with exactly
// its placeholders.
import { t } from '@/lib/i18n';

export const month = t('ux.format.month.october');
export const variance = t('ux.format.variance', { uang: 'Rp0', keterangan: 'sesuai' });
