// Compliant samples of the JSX literal rule as TASK-005 extends it for the
// design system's components: the geometry and presentation attributes of
// SVG, the attributes of inputs and shortcuts that never hold text, data
// attributes, and the component props whose type is a union of fixed values.
// Nothing here is refused. Never run.
declare const ok: boolean;
declare function t(key: string): string;
declare function Button(props: Record<string, unknown>): null;
declare function Notice(props: Record<string, unknown>): null;

export const A = () => <svg viewBox={'0 0 24 24'} focusable={false}><path d={'M4 7h16'} strokeWidth={'1.75'} /></svg>;
export const B = () => <circle cx={'4'} cy={'4'} r={'4'} />;
export const C = () => <input enterKeyHint={'search'} spellCheck={false} aria-keyshortcuts={'F2'} />;
export const D = () => <div data-state={'loading'} data-tone={ok ? 'danger' : 'neutral'} />;
export const E = () => <Button label={t('ux.action.review')} variant={'primary'} size={'lg'} icon={'refresh'} shortcut={'confirm'} />;
export const F = () => <Notice tone={'warning'} urgency={'alert'} kind={'uncertain'} width={'form'} align={'end'} level={'2'} />;
export const G = () => <th scope={'row'} />;
