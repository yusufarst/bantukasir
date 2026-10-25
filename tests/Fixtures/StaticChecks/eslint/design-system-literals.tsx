// Negative samples of the JSX literal rule beside the allowlist of TASK-005:
// a text-bearing attribute or prop still refuses a literal. Each line that
// ends with a tag is refused. Never run.
declare const ok: boolean;
declare function Button(props: Record<string, unknown>): null;
declare function Field(props: Record<string, unknown>): null;

export const A = () => <Button label={'Simpan'} variant={'primary'} />; // D01 a label prop
export const B = () => <Field hint={ok ? 'Contoh' : 'Lain'} size={'lg'} />; // D02 a hint prop
export const C = () => <svg aria-label={'Grafik'} viewBox={'0 0 24 24'} />; // D03 an aria-label beside SVG attributes
export const D = () => <text x={'4'}>{'Rp0'}</text>; // D04 text inside an SVG element
export const E = () => <Button label={`Simpan ${ok}`} />; // D05 a template literal in a prop
export const F = () => <input placeholder={'Cari'} enterKeyHint={'search'} />; // D06 a placeholder
export const G = () => <span title={'Judul'} data-state={'ok'} />; // D07 a title beside a data attribute
