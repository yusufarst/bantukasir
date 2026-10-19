// Negative samples of the JSX literal rule (ADR-005 items 1 and 5, AC-003-06)
// from the class-A adversarial review of TASK-003, finding A-08: string
// literals with a letter inside a JSX expression container or a spread
// attribute. Each line that ends with a tag is refused. The seventh form of
// the review, a literal bound to a variable and rendered later, is a limit of
// lint that code review covers (TASK-003 evidence, the readings). Never run.
declare const ok: boolean;
declare const count: number;
declare function t(key: string, replacements?: Record<string, string>): string;
declare function cn(...classes: string[]): string;

export const A = () => <span>{ok ? 'Ya' : 'Tidak'}</span>; // J01 a conditional
export const B = () => <span>{ok && 'Tersimpan'}</span>; // J02 a logical and
export const C = () => <span>{'Simpan' + ''}</span>; // J03 a concatenation
export const D = () => <span>{['Simpan']}</span>; // J04 an array
export const E = () => <input title={ok ? 'Judul' : 'Lain'} />; // J05 a conditional attribute
export const F = () => <input {...{ placeholder: 'Cari produk' }} />; // J06 a spread attribute
export const G = () => <span>{ok || 'Kosong'}</span>; // J07 a logical or
export const H = () => <span>{ok ?? 'Kosong'}</span>; // J08 a nullish default
export const I = () => <span>{t('sample.greeting.title', { nama: 'Budi' })}</span>; // J09 a replacement value of t()
export const J = () => <span>{String('Teks')}</span>; // J10 a literal passed through a call
export const K = () => <input aria-label={ok ? 'Tutup' : 'Buka'} />; // J11 a conditional aria-label
export const L = () => <span>{count > 1 ? 'Banyak' : 'Satu'}</span>; // J12 a conditional on a count
export const M = () => <input {...(ok ? { title: 'Judul' } : {})} />; // J13 a conditional spread attribute
export const N = () => <span>{'Ünïcödé'}</span>; // J14 a literal with letters beyond ASCII
export const O = () => <span>{ok ? `Ya` : `Tidak`}</span>; // J15 template literals in a conditional
export const P = () => <div className={cn('btn', ok ? 'Simpan' : 'Batal')} title={cn('Judul')} />; // J16 a literal in a text attribute through a call
