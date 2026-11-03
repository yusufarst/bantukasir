import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Committed, StateMark } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { CORRECT_CAPABILITY, type CorrectionEntry, type CostValue } from '@/lib/costing/costs';
import { CorrectionButton, CorrectionHistory } from '@/lib/costing/Parts';
import { formatCost, formatDate, formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { movementPage, openerOf, RECEIVINGS_PAGE, RECEIVINGS_SHELL, REQUESTS_PAGE } from '@/lib/inventory/stock';

// UXP-406 "Detail barang masuk" (ROUTE_CONTRACTS RP-406; COPY_DECK section 9.7; INTERACTION_INVENTORY
// UXI-246 to UXI-249 and UXI-251; NAVIGATION_CONTRACTS UXP-406; UI_STATES section 6; BR-INV-14,
// BR-INV-15; D-033, D-047; CAP-12; TASK-022 scope item 1, TASK-025 scope items 5 and 6): one
// confirmed document — its time, reference, document date and who confirmed it — and its lines,
// each with its quantity, its purchase cost as every holder of CAP-11 may see it, "Biaya perlu
// dilengkapi pemilik" for an unknown one, never Rp0 (UXS-24), and its movement (UXI-248); a zero's
// evidence or reason only for the Owner. After the Owner's correction or completion every holder
// sees the current cost, "Dikoreksi Pemilik" and the cost at confirmation, never the reason or the
// history (UXS-26; D-047); the Owner also sees the correction history (UXI-247) and "Koreksi biaya"
// or "Lengkapi biaya" per line (UXI-249; TASK-025). "Batalkan mutasi" (UXI-250) comes with
// TASK-026. "Kembali ke …" names the page that opened it when that is UXP-403 or UXP-006,
// otherwise UXP-404 with its filters; after the success of UXP-405, UXP-404 (UXI-251).

/** UXP-504 returns here. */
const OPENER = 'receiving';

/** The signed-in user that every page shares (TASK-007). */
interface SharedProps {
    readonly auth: { readonly user: { readonly capabilities: readonly string[] } };
    readonly [key: string]: unknown;
}

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
    /** The cost now, after the Owner's correction or completion, if any (D-047). */
    readonly cost: CostValue;
    readonly corrected: boolean;
    /** The cost at confirmation (D-047). */
    readonly confirmedCost: CostValue;
    /** The Owner's alone (CAP-20). */
    readonly zeroNote?: string | null;
    readonly evidenceId?: number;
    readonly correctionNo?: number;
    readonly corrections?: readonly CorrectionEntry[];
}

export default function Receiving({ document: receiving, lines }: { document: Document; lines: readonly Line[] }) {
    const title = t('inventory.receipt_detail.title');

    return (
        <AppShell title={title} {...RECEIVINGS_SHELL}>
            <ReceivingPage title={title} receiving={receiving} lines={lines} />
        </AppShell>
    );
}

/** The name of a line's link to its movement (UXI-248), which names the line's product. */
function movementLinkName(line: Line): string {
    return `${t('inventory.movement.title')} ${line.product.name}`;
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

/** A cost, or the words of UXS-24 in its place, never Rp0 (BR-COST-03). */
function costView(cost: CostValue) {
    return cost.amount === null ? <StateMark kind={'cost_unknown'} /> : formatCost(cost.amount);
}

/**
 * UXI-246: the purchase cost; after the Owner's correction or completion, the current cost with
 * "Dikoreksi Pemilik" and the cost at confirmation, or "Saat dikonfirmasi: biaya belum diketahui"
 * after a completion (UXS-26; D-047; BR-INV-15).
 */
function CostCell({ line }: { line: Line }) {
    if (!line.corrected) {
        return <>{costView(line.cost)}</>;
    }

    return (
        <span className={'bk-stack'}>
            <span>
                {t('costing.field.cost_now')}: {costView(line.cost)}
            </span>
            <StateMark kind={'owner_corrected'} />
            <span className={'bk-meta'}>{line.confirmedCost.amount === null ? t('costing.field.cost_was_unknown') : `${t('costing.field.cost_at_confirmation')}: ${formatCost(line.confirmedCost.amount)}`}</span>
        </span>
    );
}

function ReceivingPage({ title, receiving, lines }: { title: string; receiving: Document; lines: readonly Line[] }) {
    const [back] = useState(backOf);
    const [note] = useState(takeSavedNote);
    // UXI-249, the Owner's: "Koreksi biaya" or "Lengkapi biaya" per line (CAP-19).
    const corrects = usePage<SharedProps>().props.auth.user.capabilities.includes(CORRECT_CAPABILITY) && lines.some((line) => line.evidenceId !== undefined);
    const corrected = lines.filter((line) => (line.corrections?.length ?? 0) > 0);

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
                    <CostCell line={line} />
                    {line.zeroNote !== undefined && line.zeroNote !== null && (
                        <span className={'bk-meta'}>
                            {t('costing.field.zero_evidence')}: {line.zeroNote}
                        </span>
                    )}
                </>
            ),
        },
        ...(corrects
            ? [
                  {
                      id: 'correct',
                      header: t('costing.correction.title_correct'),
                      isAction: true,
                      cell: (line: Line) => (line.evidenceId === undefined ? null : <CorrectionButton evidenceId={line.evidenceId} cost={line.cost} opener={OPENER} rowName={line.product.name} />),
                  },
              ]
            : []),
        {
            id: 'movement',
            header: t('inventory.movement.title'),
            isAction: true,
            cell: (line) => (
                <a className={'bk-table-link'} href={movementPage(line.movementId)} aria-label={movementLinkName(line)}>
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
                {corrected.length > 0 && (
                    <Section title={t('costing.field.correction_history')}>
                        <div className={'bk-stack'}>
                            {corrected.map((line) => (
                                <CorrectionHistory key={line.lineNo} title={line.product.name} entries={line.corrections ?? []} />
                            ))}
                        </div>
                    </Section>
                )}
            </div>
        </PageContainer>
    );
}
