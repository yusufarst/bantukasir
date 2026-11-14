import { useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { sessionPage, UNRESOLVED_PAGE } from '@/lib/cash/sessions';
import { useUnresolved } from '@/lib/commands/unresolved';
import { PrintControl } from '@/lib/documents/Printing';
import { quantityOf, type ReceiptLine } from '@/lib/documents/ReceiptDocument';
import { PRINT_RECEIPT_COMMAND } from '@/lib/documents/requests';
import { formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import { CorrectionEntries } from '@/lib/sales/Corrections';
import { refundPage, unresolvedRefund, type Correction } from '@/lib/sales/refunds';
import { methodWord, saleBackFrom, sizePayload } from '@/lib/sales/sales';
import { RECEIPT_SIZES, rememberedSize, rememberSize, type ReceiptSize } from '@/lib/sales/till';

// UXP-202 "Detail penjualan" (ROUTE_CONTRACTS RP-202, RC-44; COPY_DECK section 7.7; INTERACTION_INVENTORY
// UXI-117 to UXI-121; NAVIGATION_CONTRACTS UXP-202; UI_STATES section 6; BR-DOC-04, BR-DOC-06; BR-RFD-12;
// D-067; TASK-031 scope item 4; TASK-034 scope item 3): one Sale in the viewer's scope — its time, cashier and
// customer, its lines as its receipt froze them, one per product, the total, the payment and its session
// (UXI-119). "Cetak ulang struk" with the size choice (UXI-117) records a print request, which carries the
// copy marker after the Sale's first (BR-DOC-04), and opens its print view. The way back names the page it
// was opened from, UXP-201 by default (UXI-121; NC-01). For the Owner (TASK-034): "Pengembalian uang dan
// retur" (UXI-118) leads to UXP-203, disabled with its reason once nothing remains to refund or to return
// (BR-RFD-03) and while the Owner holds an unresolved refund or return, with the way to UXP-006 (NC-13;
// BR-RFD-17); and the linked refunds and returns, each with its money, its goods and its reason (UXI-120),
// beside the Sale, which never changes.

const DESTINATION: DestinationId = 'sales';

const CASH = 'CASH';

interface SaleProps {
    readonly sale: {
        readonly id: number;
        readonly number: string;
        readonly committedAt: string;
        readonly cashier: string;
        readonly customerName: string | null;
        readonly total: string;
        readonly sessionId: number;
        readonly sessionNumber: string;
    };
    readonly lines: readonly ReceiptLine[];
    readonly payment: {
        readonly method: string;
        readonly tendered: string | null;
        readonly change: string | null;
        readonly reference: string | null;
    };
    /** The Owner's alone: the linked corrections (UXI-120) and whether anything remains to correct (UXI-118). */
    readonly corrections?: readonly Correction[];
    readonly refundable?: boolean;
}

export default function Show(props: SaleProps) {
    const title = t('sales.detail.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <SaleDetail title={title} {...props} />
        </AppShell>
    );
}

/**
 * UXI-118: "Pengembalian uang dan retur", disabled with its reason once both bounds are used up, and while
 * the Owner holds an unresolved refund or return of either family, with "Buka Permintaan belum pasti".
 */
function RefundEntry({ saleId, refundable }: { saleId: number; refundable: boolean }) {
    const unresolved = useUnresolved();
    const label = t('sales.refund.title');

    if (unresolvedRefund(unresolved.union) !== null) {
        const reason = (
            <>
                {t('sales.refund.disabled_unresolved')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
            </>
        );

        return <Button label={label} variant={'secondary'} enterActivates={false} disabledReason={reason} />;
    }

    return refundable ? <ButtonLink href={refundPage(saleId)} label={label} variant={'secondary'} /> : <Button label={label} variant={'secondary'} enterActivates={false} disabledReason={t('sales.refund.disabled_fully_refunded')} />;
}

function SaleDetail({ title, sale, lines, payment, corrections, refundable }: SaleProps & { title: string }) {
    const [back] = useState(() => saleBackFrom(document.referrer, window.location.origin));
    const [size, setSize] = useState<ReceiptSize>(rememberedSize);
    const cash = payment.method === CASH;

    const choose = (next: ReceiptSize): void => {
        setSize(next);
        rememberSize(next);
    };

    const sizeLabels: Readonly<Record<ReceiptSize, string>> = { '58': t('sales.receipt.size_58'), '80': t('sales.receipt.size_80'), a4: t('sales.receipt.size_a4') };
    const sizes: readonly SegmentedOption<ReceiptSize>[] = RECEIPT_SIZES.map((value) => ({ value, label: sizeLabels[value] }));

    const facts: KeyValue[] = [
        { key: 'time', label: t('sales.detail.time'), value: formatDateTime(sale.committedAt) },
        { key: 'cashier', label: t('sales.detail.cashier'), value: sale.cashier },
        ...(sale.customerName === null ? [] : [{ key: 'customer', label: t('sales.detail.customer'), value: sale.customerName }]),
        {
            key: 'session',
            label: t('cash.sessions.title'),
            value: (
                <a className={'bk-table-link'} href={sessionPage(sale.sessionId)}>
                    {t('sales.detail.session_link', { nomor: sale.sessionNumber })}
                </a>
            ),
        },
    ];

    const columns: readonly Column<ReceiptLine>[] = [
        { id: 'name', header: t('sales.detail.lines'), isHead: true, cell: (line) => line.name },
        { id: 'sku', header: t('catalog.field.sku'), cell: (line) => line.sku },
        {
            id: 'quantity',
            header: t('sales.detail.quantity'),
            numeric: true,
            cell: (line) => (
                <>
                    {quantityOf(line)} {line.unit}
                </>
            ),
        },
        { id: 'price', header: t('sales.detail.unit_price'), numeric: true, cell: (line) => formatMoney(line.unitPrice) },
        { id: 'total', header: t('sales.detail.line_total'), numeric: true, cell: (line) => formatMoney(line.lineTotal) },
    ];

    const paid: KeyValue[] = [
        { key: 'method', label: t('sales.payment.method'), value: methodWord(payment.method) },
        { key: 'total', label: t('sales.detail.total'), value: formatMoney(sale.total), strong: true },
        ...(cash && payment.tendered !== null ? [{ key: 'tendered', label: t('sales.payment.tendered'), value: formatMoney(payment.tendered) }] : []),
        ...(cash && payment.change !== null ? [{ key: 'change', label: t('sales.payment.change'), value: formatMoney(payment.change) }] : []),
        ...(payment.reference === null ? [] : [{ key: 'reference', label: t('sales.detail.reference'), value: payment.reference }]),
    ];

    return (
        <PageContainer width={'form'}>
            <div className={'bk-stack'}>
                <PageHeader title={title} subtitle={sale.number} back={back} actions={refundable === undefined ? undefined : <RefundEntry saleId={sale.id} refundable={refundable} />} />
                <Section title={title} titleHidden={true}>
                    <KeyValueList items={facts} />
                </Section>
                <Section title={t('sales.detail.lines')}>
                    <DataTable caption={t('sales.detail.lines')} columns={columns} rows={lines} rowKey={(line) => line.lineNo} />
                </Section>
                <Section title={t('sales.detail.payment')}>
                    <KeyValueList items={paid} />
                </Section>
                {corrections !== undefined && corrections.length > 0 && (
                    <Section title={t('sales.detail.refunds')}>
                        <ol className={'bk-stack'}>
                            {corrections.map((correction) => (
                                <li key={correction.id}>
                                    <CorrectionEntries correction={correction} />
                                </li>
                            ))}
                        </ol>
                    </Section>
                )}
                <Section title={t('sales.detail.reprint')} titleHidden={true}>
                    <div className={'bk-stack'}>
                        <SegmentedControl legend={t('sales.receipt.size')} options={sizes} value={size} onValueChange={choose} />
                        <div className={'bk-print-row'}>
                            <PrintControl command={PRINT_RECEIPT_COMMAND} payload={{ saleId: sale.id, size: sizePayload(size) }} label={t('sales.detail.reprint')} variant={'primary'} />
                        </div>
                    </div>
                </Section>
            </div>
        </PageContainer>
    );
}
