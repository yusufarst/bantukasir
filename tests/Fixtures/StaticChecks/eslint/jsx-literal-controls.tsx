// Compliant samples of the JSX literal rule (TASK-003 finding A-08): keys of
// t(), the allowlisted attributes that never hold text, object keys, and the
// operands of a comparison, which yield a boolean and are never shown.
// Nothing here is refused.
declare const ok: boolean;
declare const status: string;
declare const labels: Record<string, string>;
declare function t(key: string, replacements?: Record<string, string>): string;
declare function cn(...classes: string[]): string;

export const A = () => <span>{t('sample.greeting.title')}</span>;
export const B = () => <span>{ok ? t('sample.greeting.title') : t('sample.greeting.other')}</span>;
export const C = () => <div className={'grid gap-2'} />;
export const D = () => <div className={cn('btn', ok ? 'btn-primary' : 'btn-ghost')} />;
export const E = () => <input type={ok ? 'text' : 'search'} id={'harga'} name={'harga'} />;
export const F = () => <div role={'status'} key={'row-1'} />;
export const G = () => <span>{status === 'paid' ? t('sample.greeting.title') : null}</span>;
export const H = () => <span>{status !== 'open' && t('sample.greeting.title')}</span>;
export const I = () => <span>{labels['title']}</span>;
export const J = () => <div style={{ color: 'red' }} />;
export const K = () => <input {...{ className: 'field', type: 'text' }} />;
export const L = () => <input autoComplete={'off'} inputMode={'numeric'} />;
export const M = () => <a href={'/stok'} rel={'noreferrer'} target={'_blank'} />;
export const N = () => <label htmlFor={'harga'}>{t('sample.greeting.title')}</label>;
export const O = () => <span data-testid={'total'}>{t('sample.greeting.title')}</span>;
