import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ButtonLink } from '@/components/ui/Button';
import { SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { Notice } from '@/components/ui/Notice';
import { PaperItem, PaperItems, PaperRow, PaperRule, PaperShop, PaperTitle, ReceiptPaper } from '@/components/ui/ReceiptPaper';
import { ScanField } from '@/components/ui/Till';
import { AppShell, type ShellLayout } from '@/layouts/AppShell';
import { PageHeader, ResultLayout } from '@/layouts/Page';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import { CASH } from '@/lib/sales/cart';
import { holdScan, RECEIPT_SIZES, rememberedSize, rememberSize, TILL_PAGE, type ReceiptSize } from '@/lib/sales/till';

// UXP-103 "Struk" (ROUTE_CONTRACTS RP-103; KEY_SCREENS KS-05; COPY_DECK sections 7.3 and 17.1;
// DOCUMENT_CONTRACTS DC-01; INTERACTION_INVENTORY UXI-088, UXI-089, UXI-091 and UXI-093;
// NAVIGATION_CONTRACTS UXP-103; DESIGN_SYSTEM 8.16; BR-SALE-14, BR-DOC-01, BR-DOC-02; TASK-029 scope
// item 3). The committed receipt, from its snapshot, with no further confirmation: what was saved first,
// then the next sale — "Transaksi baru", or a scan or a search in "Cari atau scan produk", which has the
// focus and starts the next sale on the till with that product (UXA-09, UXA-11) — then the receipt in the
// size chosen, 58 mm, 80 mm or A4, the last choice remembered for the browser session (SEC-CH-05). Its
// print controls and "Detail penjualan" are added by the Tasks that deliver their routes (TASK-031; EXC-52).

const DESTINATION: DestinationId = 'till';

const TILL_LAYOUT: ShellLayout = 'rail';

const A4: ReceiptSize = 'a4';

/** The logo stands beside the shop's name, which says it: its text alternative is empty (WCAG 1.1.1). */
const DECORATIVE = '';

interface ReceiptLine {
    readonly lineNo: string;
    readonly sku: string;
    readonly name: string;
    readonly unit: string;
    readonly quantity: string;
    readonly unitPrice: string;
    readonly lineTotal: string;
}

interface ReceiptProps {
    readonly receipt: {
        readonly saleId: number;
        readonly number: string;
        readonly issuedAt: string;
        readonly cashier: string;
        readonly sessionNumber: string;
        readonly method: string;
        readonly total: string;
        readonly tendered: string | null;
        readonly change: string | null;
        readonly customerName: string | null;
        readonly lines: readonly ReceiptLine[];
    };
    readonly shop: {
        readonly name: string;
        readonly identity: string | null;
        readonly address: string | null;
        readonly contact: string | null;
        readonly email: string | null;
        readonly header: string | null;
        readonly footer: string | null;
        readonly logo: string | null;
    };
}

export default function Receipt(props: ReceiptProps) {
    const title = t('sales.receipt.title');

    return (
        <AppShell title={title} current={DESTINATION} layout={TILL_LAYOUT}>
            <ReceiptPage title={title} {...props} />
        </AppShell>
    );
}

/** A frozen quantity in its own precision, with a decimal comma: "2.5" is "2,5". */
function quantityOf(line: ReceiptLine): string {
    const decimals = line.quantity.includes('.') ? (line.quantity.split('.')[1] ?? '').length : 0;

    try {
        return formatQuantity(line.quantity, decimals);
    } catch {
        return line.quantity;
    }
}

function ReceiptPage({ title, receipt, shop }: ReceiptProps & { title: string }) {
    const [search, setSearch] = useState('');
    const [size, setSize] = useState<ReceiptSize>(rememberedSize);
    const searchField = useRef<HTMLInputElement | null>(null);
    const cash = receipt.method === CASH;

    useScanCapture(searchField);

    // The search field has the focus when the receipt appears (UXI-089).
    useEffect(() => {
        searchField.current?.focus();
    }, []);

    /** A scan or a search here starts the next sale on the till with it (UXI-089; UXA-11). */
    const nextSale = (code: string): void => {
        holdScan(code);
        router.visit(TILL_PAGE);
    };

    const choose = (next: ReceiptSize): void => {
        setSize(next);
        rememberSize(next);
    };

    const sizeLabels: Readonly<Record<ReceiptSize, string>> = { '58': t('sales.receipt.size_58'), '80': t('sales.receipt.size_80'), a4: t('sales.receipt.size_a4') };
    const sizes: readonly SegmentedOption<ReceiptSize>[] = RECEIPT_SIZES.map((value) => ({ value, label: sizeLabels[value] }));
    const shopLines = [shop.identity, shop.address, shop.contact, shop.email, shop.header].filter((line): line is string => line !== null && line.trim() !== '');
    const method = cash ? t('sales.payment.method_cash') : t('sales.payment.method_transfer');

    return (
        <ResultLayout
            header={<PageHeader title={title} back={{ href: TILL_PAGE, page: t('sales.till.title') }} />}
            notice={<Notice tone={'success'} urgency={'status'} title={t('sales.receipt.saved', { nomor: receipt.number })} />}
            next={
                <>
                    <ButtonLink href={TILL_PAGE} label={t('sales.receipt.new_sale')} variant={'primary'} size={'xl'} className={'bk-btn--block'} />
                    <ScanField label={t('sales.till.search')} hint={t('sales.receipt.search_hint')} value={search} onValueChange={setSearch} onLookup={nextSale} inputRef={searchField} />
                </>
            }
            document={
                <section className={'bk-card bk-section bk-stack'} aria-label={title}>
                    <SegmentedControl legend={t('sales.receipt.size')} options={sizes} value={size} onValueChange={choose} />
                    <ReceiptPaper size={size} label={title}>
                        {shop.logo !== null && <img className={'bk-paper__logo'} src={shop.logo} alt={DECORATIVE} />}
                        <PaperShop name={shop.name} lines={shopLines} />
                        {size === A4 && <PaperTitle>{t('documents.invoice.title')}</PaperTitle>}
                        <PaperRule />
                        <p>{t('documents.receipt.number', { nomor: receipt.number })}</p>
                        <p>{t('documents.receipt.time', { waktu: formatDateTime(receipt.issuedAt) })}</p>
                        <p>{t('documents.receipt.cashier', { nama: receipt.cashier })}</p>
                        <p>{t('documents.receipt.session', { nomor: receipt.sessionNumber })}</p>
                        {receipt.customerName !== null && <p>{t('documents.receipt.customer', { nama: receipt.customerName })}</p>}
                        <PaperRule />
                        <PaperItems>
                            {receipt.lines.map((line) => (
                                <PaperItem
                                    key={line.lineNo}
                                    name={line.name}
                                    sku={t('documents.receipt.sku', { sku: line.sku })}
                                    detail={t('documents.receipt.line_quantity', { jumlah: quantityOf(line), satuan: line.unit, harga: formatMoney(line.unitPrice) })}
                                    total={formatMoney(line.lineTotal)}
                                />
                            ))}
                        </PaperItems>
                        <PaperRule />
                        <PaperRow label={t('documents.receipt.total')} value={formatMoney(receipt.total)} total={true} />
                        <p>{t('documents.receipt.method', { metode: method })}</p>
                        {cash && receipt.tendered !== null && <PaperRow label={t('documents.receipt.tendered')} value={formatMoney(receipt.tendered)} />}
                        {cash && receipt.change !== null && <PaperRow label={t('documents.receipt.change')} value={formatMoney(receipt.change)} />}
                        {shop.footer !== null && shop.footer.trim() !== '' && (
                            <>
                                <PaperRule />
                                <p className={'bk-paper__center'}>{shop.footer}</p>
                            </>
                        )}
                    </ReceiptPaper>
                </section>
            }
        />
    );
}
