import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Notice } from '@/components/ui/Notice';
import { Committed, EmptyState, LoadFailed, Loading, NoResults, StateMark, UnknownCode } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { CodeField } from '@/lib/catalog/Parts';
import { catalogFieldMessage, searchProducts, type ProductRow } from '@/lib/catalog/products';
import { costHistoryPage, WORKLIST_SHELL, worklistAddress, type CostValue } from '@/lib/costing/costs';
import { CorrectionButton, rowLinkName } from '@/lib/costing/Parts';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { typeWord, type MovementType } from '@/lib/inventory/stock';

// UXP-503 "Biaya perlu dilengkapi" (ROUTE_CONTRACTS RP-503, RD-01 `stock`; COPY_DECK section 10.4;
// INTERACTION_INVENTORY UXI-313 to UXI-316; NAVIGATION_CONTRACTS UXP-503; UI_STATES section 6;
// BR-COST-03, BR-COST-22, BR-COST-27, BR-COST-38; CAP-20; TASK-025 scope item 6). The Owner's
// worklist: every cost source whose cost is UNKNOWN, "Biaya perlu dilengkapi pemilik" (UXS-24), and
// every one whose reporting is marked incomplete after a refused correction, newest first, paged on
// the server with "Berikutnya" and "Sebelumnya" (UXI-316). The filter "Produk" (UXI-313) is the
// shared Product Search in the context `stock`; its Enter looks a scanned code up and never runs a
// command (BR-CAT-32). Each row leads to "Lengkapi biaya" or "Koreksi biaya" (UXI-314), disabled with
// its reason while a cost correction of the user is uncertain (UXS-13; NC-13), and to "Riwayat biaya"
// (UXI-315). The filter and the page stand in the address (NC-10).

const STOCK_CONTEXT = 'stock';

/** UXP-504 and UXP-505 return here. */
const OPENER = 'worklist';

/** The wait after the last key before a typed search runs. */
const TYPING_MS = 300;

/** A row's cost as "Lengkapi biaya" or "Koreksi biaya" reads it: UNKNOWN until completed. */
function costOf(row: WorklistRow): CostValue {
    return { status: row.unknown ? 'UNKNOWN' : 'KNOWN', amount: null };
}

interface WorklistRow {
    readonly evidenceId: number;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly sourceType: MovementType;
    readonly recordedAt: string;
    readonly unknown: boolean;
    readonly marked: boolean;
}

interface CostWorklistProps {
    readonly filters: { readonly product: number | null; readonly perPage: number };
    readonly product: { readonly id: number; readonly name: string; readonly sku: string } | null;
    readonly worklist: { readonly rows: readonly WorklistRow[]; readonly nextCursor: string | null; readonly previousCursor: string | null };
}

export default function CostWorklist(props: CostWorklistProps) {
    const title = t('costing.worklist.title');

    return (
        <AppShell title={title} {...WORKLIST_SHELL}>
            <WorklistPage title={title} {...props} />
        </AppShell>
    );
}

function WorklistPage({ title, filters, product, worklist }: CostWorklistProps & { title: string }) {
    const [visiting, setVisiting] = useState(false);
    // "Biaya disimpan. Laporan sudah dihitung ulang." after the success of UXP-504, which leads here (UXS-04).
    const [note] = useState(takeSavedNote);

    const visit = (chosen: number | null, cursor: { readonly after?: string; readonly before?: string } = {}): void => {
        setVisiting(true);
        router.visit(worklistAddress(chosen, cursor), { preserveScroll: true, preserveState: true, onFinish: () => setVisiting(false) });
    };

    const columns: readonly Column<WorklistRow>[] = [
        { id: 'product', header: t('costing.worklist.col_product'), isHead: true, cell: (row) => row.product.name },
        { id: 'source', header: t('costing.worklist.col_source'), cell: (row) => t('costing.worklist.source_entry', { jenis: typeWord(row.sourceType), waktu: formatDateTime(row.recordedAt) }) },
        {
            id: 'status',
            header: t('costing.worklist.col_status'),
            cell: (row) => (
                <div className={'bk-stack'}>
                    {row.unknown && <StateMark kind={'cost_unknown'} />}
                    {row.marked && <span className={'bk-meta'}>{t('costing.status.marked_incomplete')}</span>}
                </div>
            ),
        },
        {
            id: 'correct',
            header: t('costing.correction.title_correct'),
            isAction: true,
            cell: (row) => <CorrectionButton evidenceId={row.evidenceId} cost={costOf(row)} opener={OPENER} rowName={row.product.name} />,
        },
        {
            id: 'history',
            header: t('costing.history.title'),
            isAction: true,
            cell: (row) => (
                <a className={'bk-table-link'} href={costHistoryPage(row.product.id, OPENER)} aria-label={rowLinkName(t('costing.history.title'), row.product.name)}>
                    {t('costing.history.title')}
                </a>
            ),
        },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} />}
            toolbar={<ProductFilter key={String(filters.product ?? 0)} chosen={product} onChoose={(picked) => visit(picked === null ? null : picked.id)} />}
            more={
                worklist.previousCursor !== null || worklist.nextCursor !== null ? (
                    <div className={'bk-actions'}>
                        {worklist.previousCursor !== null && <Button label={t('ux.action.previous_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters.product, { before: worklist.previousCursor ?? undefined })} />}
                        {worklist.nextCursor !== null && <Button label={t('ux.action.next_page')} variant={'secondary'} busy={visiting} onClick={() => visit(filters.product, { after: worklist.nextCursor ?? undefined })} />}
                    </div>
                ) : undefined
            }
        >
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                <Notice tone={'neutral'} urgency={'none'}>
                    <p>{t('costing.worklist.intro')}</p>
                </Notice>
                {/* Its rows become cards on a narrow screen (NAVIGATION_CONTRACTS UXP-503; UXA-12 names no worklist among the wide tables). */}
                {worklist.rows.length > 0 && <DataTable caption={title} columns={columns} rows={worklist.rows} rowKey={(row) => String(row.evidenceId)} />}
                {worklist.rows.length === 0 && filters.product !== null && <NoResults onClear={() => visit(null)} />}
                {worklist.rows.length === 0 && filters.product === null && <EmptyState body={t('costing.worklist.empty')} />}
            </div>
        </ListLayout>
    );
}

type Results =
    | { readonly kind: 'none' }
    | { readonly kind: 'searching' }
    | { readonly kind: 'rows'; readonly rows: readonly ProductRow[]; readonly unknownCode: string | null }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed'; readonly text: string };

/**
 * The filter "Produk" (UXI-313): the shared Product Search in the context `stock`. Typing searches
 * once the keys pause; the Enter that ends a code looks it up exactly and chooses its product; a
 * newer read supersedes an older one (TR-PAGE-04). A choice applies the filter; an emptied field
 * removes it.
 */
function ProductFilter({ chosen, onChoose }: { chosen: { readonly name: string } | null; onChoose: (product: { readonly id: number } | null) => void }) {
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
            <CodeField label={t('costing.worklist.filter_product')} hint={t('catalog.products.search_hint')} error={invalidText} value={text} onValueChange={edited} onEnter={() => (text.trim() === '' ? undefined : search(text.trim(), true))} inputRef={field} />
            {results.kind === 'searching' && <Loading lines={1} />}
            {results.kind === 'failed' && <LoadFailed onRetry={() => search(results.text, false)} />}
            {results.kind === 'rows' && results.unknownCode !== null && results.rows.length === 0 && <UnknownCode code={results.unknownCode} />}
            {results.kind === 'rows' && results.rows.length > 0 && (
                <ul className={'bk-stack'}>
                    {results.rows.map((row) => (
                        <li key={row.id}>
                            {/* The accessible name holds the visible text, in its order and with its words apart (WCAG 2.5.3). */}
                            <Button label={row.name} rowName={row.sku} variant={'secondary'} block={true} enterActivates={true} onClick={() => onChoose(row)}>
                                {' '}
                                <span className={'bk-meta'}>{row.sku}</span>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
