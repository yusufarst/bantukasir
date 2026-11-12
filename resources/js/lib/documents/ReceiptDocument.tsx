import type { ComponentType } from 'react';
import { PaperItem, PaperItems, PaperRow, PaperRule, PaperShop, PaperTitle, ReceiptPaper, type PaperSize } from '@/components/ui/ReceiptPaper';
import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';

// The receipt of DOCUMENT_CONTRACTS DC-01 as a template version of the code base (ARC-44, ARC-45; BR-DOC-01
// to BR-DOC-03; DESIGN_SYSTEM 8.16 and 12; UXA-15, UXA-23; TASK-031 scope item 1): rendered from the
// ReceiptSnapshot only, in 58 mm, 80 mm or A4 "Nota Penjualan", each printing only what the snapshot holds,
// never a cost, a tax line or a claim of a tax invoice. A template a snapshot names is never changed in
// place: a change is a new version beside `receipt.1` in RECEIPT_TEMPLATES, so that every earlier receipt
// is reproduced exactly. The copy marker "Salinan" is the paper's first element on a reprint (BR-DOC-04).

/** A frozen line of the snapshot, every value a string (SCHEMA section 5.9). */
export interface ReceiptLine {
    readonly lineNo: string;
    readonly sku: string;
    readonly name: string;
    readonly unit: string;
    readonly quantity: string;
    readonly unitPrice: string;
    readonly lineTotal: string;
}

/** The receipt as its snapshot froze it (App\Queries\Sales\Receipts). */
export interface ReceiptSnapshot {
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
}

/** The shop as the snapshot's BusinessProfile revision froze it; the shared prop `shop` stays the shell's. */
export interface ReceiptIssuer {
    readonly name: string;
    readonly identity: string | null;
    readonly address: string | null;
    readonly contact: string | null;
    readonly email: string | null;
    readonly header: string | null;
    readonly footer: string | null;
    readonly logo: string | null;
}

export interface ReceiptDocumentProps {
    readonly size: PaperSize;
    readonly receipt: ReceiptSnapshot;
    readonly issuer: ReceiptIssuer;
    /** The paper's accessible name. */
    readonly label: string;
    /** A reprint: "Salinan" first at the top (DC-01). */
    readonly copy: boolean;
}

const A4: PaperSize = 'a4';

const CASH = 'CASH';

/** The logo stands beside the shop's name, which says it: its text alternative is empty (WCAG 1.1.1). */
const DECORATIVE = '';

/** A frozen quantity in its own precision, with a decimal comma: "2.5" is "2,5". */
export function quantityOf(line: ReceiptLine): string {
    const decimals = line.quantity.includes('.') ? (line.quantity.split('.')[1] ?? '').length : 0;

    try {
        return formatQuantity(line.quantity, decimals);
    } catch {
        return line.quantity;
    }
}

/** `receipt.1`: DC-01 rows 1 to 13 in their order. */
export function ReceiptV1({ size, receipt, issuer, label, copy }: ReceiptDocumentProps) {
    const cash = receipt.method === CASH;
    const shopLines = [issuer.identity, issuer.address, issuer.contact, issuer.email, issuer.header].filter((line): line is string => line !== null && line.trim() !== '');
    const method = cash ? t('sales.payment.method_cash') : t('sales.payment.method_transfer');

    return (
        <ReceiptPaper size={size} label={label} {...(copy ? { copyMarker: t('documents.receipt.copy_marker') } : {})}>
            {issuer.logo !== null && <img className={'bk-paper__logo'} src={issuer.logo} alt={DECORATIVE} />}
            <PaperShop name={issuer.name} lines={shopLines} />
            {size === A4 && <PaperTitle>{t('documents.invoice.title')}</PaperTitle>}
            <PaperRule />
            <p>{t('documents.receipt.number', { nomor: receipt.number })}</p>
            <p>{t('documents.receipt.time', { waktu: formatDateTime(receipt.issuedAt) })}</p>
            <p>{t('documents.receipt.cashier', { nama: receipt.cashier })}</p>
            <p>{t('documents.receipt.session', { nomor: receipt.sessionNumber })}</p>
            {receipt.customerName !== null && <p>{t('documents.receipt.customer', { nama: receipt.customerName })}</p>}
            <PaperRule />
            {size === A4 ? (
                <table className={'bk-paper__table'}>
                    <thead>
                        <tr>
                            <th scope={'col'}>{t('catalog.products.title')}</th>
                            <th scope={'col'}>{t('sales.detail.quantity')}</th>
                            <th scope={'col'}>{t('sales.detail.unit_price')}</th>
                            <th scope={'col'}>{t('documents.receipt.line_total')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {receipt.lines.map((line) => (
                            <tr key={line.lineNo}>
                                <td>
                                    <p className={'bk-paper__name'}>{line.name}</p>
                                    <p className={'bk-paper__sku'}>{t('documents.receipt.sku', { sku: line.sku })}</p>
                                </td>
                                <td>
                                    {quantityOf(line)} {line.unit}
                                </td>
                                <td>{formatMoney(line.unitPrice)}</td>
                                <td>{formatMoney(line.lineTotal)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
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
            )}
            <PaperRule />
            <PaperRow label={t('documents.receipt.total')} value={formatMoney(receipt.total)} total={true} />
            <p>{t('documents.receipt.method', { metode: method })}</p>
            {cash && receipt.tendered !== null && <PaperRow label={t('documents.receipt.tendered')} value={formatMoney(receipt.tendered)} />}
            {cash && receipt.change !== null && <PaperRow label={t('documents.receipt.change')} value={formatMoney(receipt.change)} />}
            {issuer.footer !== null && issuer.footer.trim() !== '' && (
                <>
                    <PaperRule />
                    <p className={'bk-paper__center'}>{issuer.footer}</p>
                </>
            )}
        </ReceiptPaper>
    );
}

/** Every receipt template of the code base, by the version a snapshot names (ARC-45). */
export const RECEIPT_TEMPLATES: Readonly<Record<string, ComponentType<ReceiptDocumentProps>>> = {
    'receipt.1': ReceiptV1,
};

/** The receipt by the template its snapshot names; a version the code base does not know is a defect, never rendered by another. */
export function ReceiptDocument({ template, ...props }: ReceiptDocumentProps & { readonly template: string }) {
    const Template = RECEIPT_TEMPLATES[template];

    if (Template === undefined) {
        throw new Error(`The receipt template ${template} is not in the code base (ARC-45).`);
    }

    return <Template {...props} />;
}
