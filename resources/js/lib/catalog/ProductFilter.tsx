import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { LoadFailed, Loading, UnknownCode } from '@/components/ui/States';
import { CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, searchProducts, type ProductRow } from '@/lib/catalog/products';
import { t } from '@/lib/i18n';

// The filter "Produk" of a list: the shared Product Search in the context `stock` (RD-01; INTERACTION_CONTRACTS
// section 3.1), first of UXP-402 "Riwayat stok" (UXI-219; TASK-022), then of UXP-612 "Riwayat aktivitas" too
// (UXI-377; TASK-042), whose Owner holds CAP-14. Typing searches once the keys pause; the Enter that ends a code
// looks it up exactly and chooses its product, and never runs a command (BR-CAT-32); a newer read supersedes an
// older one (TR-PAGE-04). A choice applies the filter; an emptied field removes it.

const STOCK_CONTEXT = 'stock';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly ProductRow[]; readonly unknownCode: string | null }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string };

export function ProductFilter({ label, chosen, onChoose }: { label: string; chosen: { readonly name: string } | null; onChoose: (product: { readonly id: number } | null) => void }) {
    const [text, setText] = useState(chosen?.name ?? '');
    const [results, setResults] = useState<Results>({ kind: 'none' });
    const latest = useRef(0);
    const timer = useRef<number | null>(null);
    const field = useRef<HTMLInputElement | null>(null);

    const supersede = (): number => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        latest.current += 1;

        return latest.current;
    };

    useEffect(() => () => void supersede(), []);

    const search = (typed: string, exact: boolean): void => {
        const mine = supersede();

        setResults({ kind: 'searching' });
        void searchProducts({ context: STOCK_CONTEXT, q: typed, ...(exact ? { mode: 'exact' } : {}) }).then((answer) => {
            if (mine !== latest.current) {
                return;
            }

            if (answer.kind === 'read') {
                const first = answer.body.rows[0];

                if (exact && first !== undefined) {
                    setResults({ kind: 'none' });
                    onChoose(first);

                    return;
                }

                setResults({ kind: 'rows', rows: answer.body.rows, unknownCode: exact ? typed : null });
            } else if (answer.kind === 'invalid') {
                setResults({ kind: 'invalid', fields: answer.fields });
            } else {
                setResults({ kind: 'failed', text: typed });
            }
        });
    };

    const edited = (next: string): void => {
        supersede();
        setText(next);

        const typed = next.trim();

        if (typed === '') {
            setResults({ kind: 'none' });

            if (chosen !== null) {
                onChoose(null);
            }

            return;
        }

        timer.current = window.setTimeout(() => {
            timer.current = null;
            search(typed, false);
        }, TYPING_MS);
    };

    const invalidText = results.kind === 'invalid' ? catalogFieldMessage(results.fields.q) : undefined;

    return (
        <div className={'bk-stack'}>
            <CodeField label={label} hint={t('catalog.products.search_hint')} error={invalidText} value={text} onValueChange={edited} onEnter={() => (text.trim() === '' ? undefined : search(text.trim(), true))} inputRef={field} />
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => search(results.text, false)} />}
            {results.kind === 'rows' && results.unknownCode !== null && results.rows.length === 0 && <UnknownCode code={results.unknownCode} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((row) => (
                        <li key={row.id}>
                            <Button label={row.name} rowName={row.sku} variant={'secondary'} block={true} enterActivates={true} onClick={() => onChoose(row)}>
                                <span className={'bk-meta'}>{row.sku}</span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
