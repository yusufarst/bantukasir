import { useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Committed, CostIncomplete, StateMark } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { productPage, PRODUCTS_SHELL } from '@/lib/catalog/products';
import { WORKLIST_PAGE, WORKLIST_SHELL, worklistAddress, type CorrectionEntry, type CostValue, type HistoryOpener } from '@/lib/costing/costs';
import { correctionText, CorrectionButton } from '@/lib/costing/Parts';
import { formatCost, formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { backFrom, REQUESTS_PAGE, typeWord, type MovementType } from '@/lib/inventory/stock';

// UXP-505 "Riwayat biaya" (ROUTE_CONTRACTS RP-505; COPY_DECK section 10.6; INTERACTION_INVENTORY UXI-324
// and UXI-325; NAVIGATION_CONTRACTS UXP-505; UI_STATES section 6; BR-COST-06, BR-COST-09, BR-COST-13,
// BR-COST-20, BR-COST-27, BR-COST-38; CAP-20, the Owner's alone; TASK-025 scope item 6). One product's
// cost: its inventory value and average cost now — "Biaya perlu dilengkapi pemilik" while UNKNOWN,
// never Rp0 (UXS-24), and "Data biaya belum lengkap" with the way to UXP-503 while a figure is
// incomplete or the product carries a mark (UXS-25) — its cost sources, newest first, each with
// "Koreksi biaya" or "Lengkapi biaya" (UXI-324), and its corrections. "Kembali ke …" names the page
// that opened it (UXI-325).

/** UXP-504 returns here. */
const OPENER = 'history';

interface EvidenceRow {
    readonly evidenceId: number;
    readonly movementId: number;
    readonly sourceType: MovementType;
    readonly recordedAt: string;
    readonly quantity: string;
    readonly cost: CostValue;
    readonly zeroNote: string | null;
    readonly correctionNo: number;
    readonly marked: boolean;
}

interface ProductCorrection extends CorrectionEntry {
    readonly evidenceId: number;
    readonly sourceType: MovementType;
    readonly sourceRecordedAt: string;
}

interface CostHistoryProps {
    readonly product: { readonly id: number; readonly name: string; readonly sku: string; readonly baseUnit: string; readonly quantityPrecision: number };
    readonly valuation: { readonly status: 'KNOWN' | 'UNKNOWN'; readonly value: string | null; readonly averageCost: string | null; readonly onHand: string } | null;
    readonly marked: boolean;
    readonly evidence: readonly EvidenceRow[];
    readonly corrections: readonly ProductCorrection[];
    readonly opener: HistoryOpener | null;
}

export default function CostHistory(props: CostHistoryProps) {
    const title = t('costing.history.title');
    const shell = props.opener === 'worklist' ? WORKLIST_SHELL : PRODUCTS_SHELL;

    return (
        <AppShell title={title} {...shell}>
            <HistoryPage title={title} {...props} />
        </AppShell>
    );
}

/** The way back of UXI-325: the page that opened this one, with its filters, and its title. */
function backOf(opener: HistoryOpener | null, product: number): { readonly href: string; readonly page: string } {
    switch (opener) {
        case 'worklist':
            return { href: backFrom(document.referrer, window.location.origin, (path) => path === WORKLIST_PAGE, WORKLIST_PAGE), page: t('costing.worklist.title') };
        case 'requests':
            return { href: REQUESTS_PAGE, page: t('commands.unresolved.title') };
        default:
            return { href: productPage(product), page: t('catalog.product_detail.title') };
    }
}

/** The key of a correction in the product's list: its cost source and its number. */
function correctionKey(entry: ProductCorrection): string {
    return `${String(entry.evidenceId)}-${String(entry.correctionNo)}`;
}

/** A value, or the words of UXS-24 in its place, never Rp0 (BR-COST-03). */
function costOf(amount: string | null) {
    return amount === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(amount)}</span>;
}

function HistoryPage({ title, product, valuation, marked, evidence, corrections, opener }: CostHistoryProps & { title: string }) {
    const [back] = useState(() => backOf(opener, product.id));
    // "Biaya disimpan. Laporan sudah dihitung ulang." after the success of UXP-504, which leads here (UXS-04).
    const [note] = useState(takeSavedNote);
    const incomplete = marked || valuation?.status === 'UNKNOWN';
    const unit = (quantity: string): string => formatQuantity(quantity, product.quantityPrecision, product.baseUnit);

    const figures: KeyValue[] =
        valuation === null
            ? []
            : [
                  { key: 'value', label: t('costing.history.inventory_value'), value: costOf(valuation.value) },
                  ...(valuation.status === 'KNOWN' && valuation.averageCost === null ? [] : [{ key: 'average', label: t('costing.history.average_cost'), value: costOf(valuation.averageCost) }]),
                  { key: 'stock', label: t('inventory.stock.col_stock'), value: <span className={'bk-num'}>{unit(valuation.onHand)}</span> },
              ];

    return (
        <PageContainer width={'form'}>
            <PageHeader title={title} subtitle={t('catalog.product_detail.subtitle', { produk: product.name })} back={back} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {incomplete && <CostIncomplete action={<ButtonLink href={worklistAddress(product.id)} label={t('costing.worklist.title')} variant={'secondary'} />} />}
                {figures.length > 0 && (
                    <Section title={t('costing.history.inventory_value')} titleHidden={true}>
                        <KeyValueList items={figures} />
                    </Section>
                )}
                <Section title={t('costing.evidence.title')}>
                    <ul className={'bk-stack'}>
                        {evidence.map((row) => {
                            const name = t('costing.worklist.source_entry', { jenis: typeWord(row.sourceType), waktu: formatDateTime(row.recordedAt) });

                            return (
                                <li key={row.evidenceId}>
                                    <div className={'bk-stack'}>
                                        <p>
                                            {t('costing.history.evidence_entry', {
                                                jenis: typeWord(row.sourceType),
                                                waktu: formatDateTime(row.recordedAt),
                                                jumlah: unit(row.quantity),
                                                biaya: row.cost.amount === null ? t('costing.status.unknown') : formatCost(row.cost.amount),
                                            })}
                                        </p>
                                        {row.zeroNote !== null && (
                                            <p className={'bk-meta'}>
                                                {t('costing.field.zero_evidence')}: {row.zeroNote}
                                            </p>
                                        )}
                                        {row.marked && <p className={'bk-meta'}>{t('costing.status.marked_incomplete')}</p>}
                                        <div className={'bk-actions'}>
                                            <CorrectionButton evidenceId={row.evidenceId} cost={row.cost} opener={OPENER} rowName={name} />
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </Section>
                {corrections.length > 0 && (
                    <Section title={t('costing.history.corrections')}>
                        <ul className={'bk-stack'}>
                            {corrections.map((entry) => (
                                <li key={correctionKey(entry)}>
                                    <p>{t('costing.worklist.source_entry', { jenis: typeWord(entry.sourceType), waktu: formatDateTime(entry.sourceRecordedAt) })}</p>
                                    <p className={'bk-meta'}>{correctionText(entry)}</p>
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}
            </div>
        </PageContainer>
    );
}
