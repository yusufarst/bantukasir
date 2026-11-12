import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { brandOf, documentTitle, type Shop } from '@/layouts/AppShell';
import { ReceiptDocument, type ReceiptIssuer, type ReceiptSnapshot } from '@/lib/documents/ReceiptDocument';
import { openedForPrinting } from '@/lib/documents/requests';
import { t } from '@/lib/i18n';
import type { ReceiptSize } from '@/lib/sales/till';

// RP-103P, the print view of a receipt (ROUTE_CONTRACTS RP-103P; DOCUMENT_CONTRACTS DC-01; COPY_DECK section
// 17.1; DESIGN_SYSTEM 8.16 and 12; UXA-23; BR-DOC-01 to BR-DOC-04; ARC-44, ARC-45; TASK-031 scope item 2):
// the receipt of the committed print request, from its snapshot only, by the template version it names,
// in the size asked for, "Salinan" first at the top when the request is a copy. It has no control and no
// shell: it opens in its own tab, and the browser's print dialog opens once the paper is drawn. A print
// dialog is not proof of ink, a failed print is simply requested again, and nothing here writes
// (TR-HTTP-02; REQ-SALE-11).

interface ReceiptPrintProps {
    readonly receipt: ReceiptSnapshot;
    readonly issuer: ReceiptIssuer;
    readonly template: string;
    readonly size: ReceiptSize;
    readonly copy: boolean;
}

export default function ReceiptPrint({ receipt, issuer, template, size, copy }: ReceiptPrintProps) {
    const { shop } = usePage<{ shop?: Shop | null }>().props;
    const title = t('sales.receipt.title');
    const printed = useRef(false);

    // Once, after the first drawing and once the font is there to measure the text, and only on the
    // navigation that follows the request, never on a reload or a move back (review R-03).
    useEffect(() => {
        if (printed.current || !openedForPrinting()) {
            return;
        }

        printed.current = true;
        void document.fonts.ready.then(() => window.print());
    }, []);

    return (
        <>
            <Head title={documentTitle(title, brandOf(shop))} />
            <main className={'bk-print-view'}>
                <h1 className={'bk-sr-only'}>{title}</h1>
                <ReceiptDocument template={template} size={size} receipt={receipt} issuer={issuer} label={title} copy={copy} />
            </main>
        </>
    );
}
