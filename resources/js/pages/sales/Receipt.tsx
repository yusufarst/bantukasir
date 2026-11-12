import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { Notice } from '@/components/ui/Notice';
import { ScanField } from '@/components/ui/Till';
import { AppShell, type ShellLayout } from '@/layouts/AppShell';
import { PageHeader, ResultLayout } from '@/layouts/Page';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { PrintControl } from '@/lib/documents/Printing';
import { ReceiptV1, type ReceiptIssuer, type ReceiptSnapshot } from '@/lib/documents/ReceiptDocument';
import { PRINT_RECEIPT_COMMAND } from '@/lib/documents/requests';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import { salePage, sizePayload } from '@/lib/sales/sales';
import { holdScan, RECEIPT_SIZES, rememberedSize, rememberSize, TILL_PAGE, type ReceiptSize } from '@/lib/sales/till';

// UXP-103 "Struk" (ROUTE_CONTRACTS RP-103; KEY_SCREENS KS-05; COPY_DECK sections 7.3 and 17.1;
// DOCUMENT_CONTRACTS DC-01; INTERACTION_INVENTORY UXI-088, UXI-089, UXI-091 and UXI-093;
// NAVIGATION_CONTRACTS UXP-103; DESIGN_SYSTEM 8.16; BR-SALE-14, BR-DOC-01, BR-DOC-02; TASK-029 scope
// item 3). The committed receipt, from its snapshot, with no further confirmation: what was saved first,
// then the next sale — "Transaksi baru", or a scan or a search in "Cari atau scan produk", which has the
// focus and starts the next sale on the till with that product (UXA-09, UXA-11) — then the receipt in the
// size chosen, 58 mm, 80 mm or A4, the last choice remembered for the browser session (SEC-CH-05).
// "Cetak struk" (UXI-090; TASK-031) records the print request in that size and opens its print view
// (RP-103P), with the reminder that a closed print dialog is not proof of ink (BR-DOC-04); "Detail
// penjualan" (UXI-092) leads to UXP-202.

const DESTINATION: DestinationId = 'till';

const TILL_LAYOUT: ShellLayout = 'rail';

interface ReceiptProps {
    readonly receipt: ReceiptSnapshot;
    /** The shop as the snapshot froze it; the shared prop `shop` stays the shell's (TASK-010). */
    readonly issuer: ReceiptIssuer;
}

export default function Receipt(props: ReceiptProps) {
    const title = t('sales.receipt.title');

    return (
        <AppShell title={title} current={DESTINATION} layout={TILL_LAYOUT}>
            <ReceiptPage title={title} receipt={props.receipt} issuer={props.issuer} />
        </AppShell>
    );
}

function ReceiptPage({ title, receipt, issuer }: ReceiptProps & { title: string }) {
    const [search, setSearch] = useState('');
    const [size, setSize] = useState<ReceiptSize>(rememberedSize);
    const searchField = useRef<HTMLInputElement | null>(null);
    const hint = useId();

    useScanCapture(searchField);

    // The search field has the focus when the receipt appears (UXI-089).
    useEffect(() => {
        searchField.current?.focus();
    }, []);

    const leaving = useRef(false);

    /**
     * A scan or a search here starts the next sale on the till with it (UXI-089; UXA-11). The till opens
     * once: a scan made before it opens is held after the others and taken with them (review R-05).
     */
    const nextSale = (code: string): void => {
        holdScan(code);
        setSearch('');

        if (!leaving.current) {
            leaving.current = true;
            router.visit(TILL_PAGE, {
                onFinish: () => {
                    leaving.current = false;
                },
            });
        }
    };

    const choose = (next: ReceiptSize): void => {
        setSize(next);
        rememberSize(next);
    };

    const sizeLabels: Readonly<Record<ReceiptSize, string>> = { '58': t('sales.receipt.size_58'), '80': t('sales.receipt.size_80'), a4: t('sales.receipt.size_a4') };
    const sizes: readonly SegmentedOption<ReceiptSize>[] = RECEIPT_SIZES.map((value) => ({ value, label: sizeLabels[value] }));

    return (
        <ResultLayout
            header={<PageHeader title={title} back={{ href: TILL_PAGE, page: t('sales.till.title') }} />}
            notice={<Notice tone={'success'} urgency={'status'} title={t('sales.receipt.saved', { nomor: receipt.number })} />}
            next={
                <>
                    <ButtonLink href={TILL_PAGE} label={t('sales.receipt.new_sale')} variant={'primary'} size={'xl'} className={'bk-btn--block'} />
                    <ScanField label={t('sales.till.search')} hint={t('sales.receipt.search_hint')} value={search} onValueChange={setSearch} onLookup={nextSale} inputRef={searchField} />
                    <div>
                        <ButtonLink href={salePage(receipt.saleId)} label={t('sales.detail.title')} variant={'secondary'} />
                    </div>
                </>
            }
            document={
                <section className={'bk-card bk-section bk-stack'} aria-label={title}>
                    <SegmentedControl legend={t('sales.receipt.size')} options={sizes} value={size} onValueChange={choose} />
                    <div className={'bk-print-row'}>
                        <PrintControl command={PRINT_RECEIPT_COMMAND} payload={{ saleId: receipt.saleId, size: sizePayload(size) }} label={t('sales.receipt.print')} variant={'secondary'} describedBy={hint} />
                        <p className={'bk-hint'} id={hint}>
                            {t('sales.receipt.print_check')}
                        </p>
                    </div>
                    <ReceiptV1 size={size} receipt={receipt} issuer={issuer} label={title} copy={false} />
                </section>
            }
        />
    );
}
