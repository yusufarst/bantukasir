import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { brandOf, documentTitle, type Shop } from '@/layouts/AppShell';
import { openedForPrinting } from '@/lib/documents/requests';
import { SessionReportDocument, type SessionReportIssuer, type SessionReportSnapshot } from '@/lib/documents/SessionReportDocument';
import { t } from '@/lib/i18n';

// RP-206P, the print view of a session report (ROUTE_CONTRACTS RP-206P; DOCUMENT_CONTRACTS DC-02; COPY_DECK
// section 17.2; UXA-23; BR-CASH-19 to BR-CASH-22; ARC-44, ARC-45; TASK-031 scope item 2): the report of the
// committed print request, from its snapshot only, on A4, by the template version it names, without a copy
// marker. It has no control and no shell: it opens in its own tab, and the browser's print dialog opens
// once the paper is drawn. A failed print is requested again and renders the same snapshot; nothing here
// writes (TR-HTTP-02).

export default function SessionReportPrint({ report, issuer }: { report: SessionReportSnapshot; issuer: SessionReportIssuer }) {
    const { shop } = usePage<{ shop?: Shop | null }>().props;
    const title = t('documents.session_report.title');
    const printed = useRef(false);

    // Only on the navigation that follows the request, never on a reload or a move back (review R-03).
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
                <SessionReportDocument report={report} issuer={issuer} label={title} />
            </main>
        </>
    );
}
