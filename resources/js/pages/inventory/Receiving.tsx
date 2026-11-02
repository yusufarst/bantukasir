import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Committed, StateMark } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { formatCost, formatDate, formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { movementPage, openerOf, RECEIVINGS_PAGE, RECEIVINGS_SHELL, REQUESTS_PAGE } from '@/lib/inventory/stock';

// UXP-406 "Detail barang masuk" (ROUTE_CONTRACTS RP-406; COPY_DECK section 9.7; INTERACTION_INVENTORY
// UXI-246 to UXI-248 and UXI-251; NAVIGATION_CONTRACTS UXP-406; UI_STATES section 6; BR-INV-14,
// BR-INV-15; D-033, D-047; CAP-12; TASK-022 scope item 1): one confirmed document — its time,
// reference, document date and who confirmed it — and its lines, each with its quantity, its
// purchase cost as every holder of CAP-11 may see it, "Biaya perlu dilengkapi pemilik" for an
// unknown one, never Rp0 (UXS-24), and its movement (UXI-248); a zero's evidence or reason only
// for the Owner. The current cost, "Dikoreksi Pemilik" and the cost at confirmation after an
// Owner's correction, and the Owner's correction history (UXI-246, UXI-247), come with the
// corrections of TASK-025; "Koreksi biaya" and "Batalkan mutasi" (UXI-249, UXI-250) with TASK-025
// and TASK-026. "Kembali ke …" names the page that opened it when that is UXP-403 or UXP-006,
// otherwise UXP-404 with its filters; after the success of UXP-405, UXP-404 (UXI-251).

interface Document {
    readonly id: number;
    readonly confirmedAt: string;
    readonly reference: string | null;
    readonly documentDate: string | null;
    readonly lineCount: number;
    readonly actorDisplayName: string;
}

interface Line {
    readonly lineNo: number;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly movementId: number;
    readonly quantity: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly cost: { readonly status: 'KNOWN' | 'UNKNOWN'; readonly amount: string | null };
    /** The Owner's alone (CAP-20). */
    readonly zeroNote?: string | null;
}

export default function Receiving({ document: receiving, lines }: { document: Document; lines: readonly Line[] }) {
    const title = t('inventory.receipt_detail.title');

    return (
        <AppShell title={title} {...RECEIVINGS_SHELL}>
            <ReceivingPage title={title} receiving={receiving} lines={lines} />
        </AppShell>
    );
}

/** The way back of UXI-251. */
function backOf(): { readonly href: string; readonly page: string } {
    const opener = openerOf(document.referrer, window.location.origin);
    const path = opener?.pathname ?? '';

    if (opener !== null && /^\/stock\/movements\/[0-9]+$/.test(path)) {
        return { href: path, page: t('inventory.movement.title') };
    }

    if (opener !== null && path === REQUESTS_PAGE) {
        return { href: REQUESTS_PAGE, page: t('commands.unresolved.title') };
    }

    return { href: opener !== null && path === RECEIVINGS_PAGE ? `${path}${opener.search}` : RECEIVINGS_PAGE, page: t('inventory.receipts.title') };
}

function ReceivingPage({ title, receiving, lines }: { title: string; receiving: Document; lines: readonly Line[] }) {
    const [back] = useState(backOf);
    const [note] = useState(takeSavedNote);

    const items: KeyValue[] = [
        { key: 'time', label: t('inventory.receipts.col_time'), value: formatDateTime(receiving.confirmedAt) },
        ...(receiving.reference === null ? [] : [{ key: 'reference', label: t('inventory.receipts.col_reference'), value: receiving.reference }]),
        ...(receiving.documentDate === null ? [] : [{ key: 'date', label: t('inventory.receipt_new.document_date'), value: formatDate(receiving.documentDate) }]),
        { key: 'actor', label: t('inventory.receipts.col_actor'), value: receiving.actorDisplayName },
    ];

    const columns: readonly Column<Line>[] = [
        { id: 'product', header: t('inventory.line.product'), isHead: true, cell: (line) => line.product.name },
        { id: 'quantity', header: t('inventory.line.quantity'), numeric: true, cell: (line) => formatQuantity(line.quantity, line.quantityPrecision, line.baseUnit) },
        {
            id: 'cost',
            header: t('costing.field.purchase_cost'),
            numeric: true,
            cell: (line) => (
                <>
                    {line.cost.amount === null ? <StateMark kind={'cost_unknown'} /> : formatCost(line.cost.amount)}
                    {line.zeroNote !== undefined && line.zeroNote !== null && (
                        <span className={'bk-meta'}>
                            {t('costing.field.zero_evidence')}: {line.zeroNote}
                        </span>
                    )}
                </>
            ),
        },
        {
            id: 'movement',
            header: t('inventory.movement.title'),
            isAction: true,
            cell: (line) => (
                <a className={'bk-table-link'} href={movementPage(line.movementId)} aria-label={`${t('inventory.movement.title')} ${line.product.name}`}>
                    {t('inventory.movement.title')}
                </a>
            ),
        },
    ];

    return (
        <PageContainer>
            <PageHeader title={title} subtitle={t('inventory.receipt_detail.subtitle', { waktu: formatDateTime(receiving.confirmedAt) })} back={back} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                <Section title={title} titleHidden={true}>
                    <KeyValueList items={items} />
                </Section>
                <DataTable caption={title} columns={columns} rows={lines} rowKey={(line) => String(line.lineNo)} />
            </div>
        </PageContainer>
    );
}
