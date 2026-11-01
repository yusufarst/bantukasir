import { Head, usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { StateNotice } from '@/components/ui/States';
import { brandOf, documentTitle, type Shop } from '@/layouts/AppShell';
import { drawCode128 } from '@/lib/documents/code128';
import { t } from '@/lib/i18n';

// RP-306P, the print view of barcode labels (ROUTE_CONTRACTS RP-306P; DOCUMENT_CONTRACTS
// DC-05; COPY_DECK section 17.3; UXA-23; BR-CAT-35, BR-CAT-36; TASK-018 scope item 3): as
// many labels of 50 × 30 mm, a candidate size confirmed at gate DEVICES, as the committed
// print request recorded, each with the name, the bars, the code and the SKU, and never a
// cost or a price. It has no control and no shell: it opens in its own tab from UXP-306,
// which stays for another print, and the browser's print dialog opens once the labels are
// drawn. A print dialog is not proof of ink, and nothing here writes (TR-HTTP-02).

interface Label {
    readonly name: string;
    readonly sku: string;
    readonly code: string;
    readonly count: number;
}

export default function LabelPrint({ label }: { label: Label }) {
    const { shop } = usePage<{ shop?: Shop | null }>().props;
    const title = t('catalog.labels.title');
    const [failed, setFailed] = useState(false);
    const printed = useRef(false);

    // Once, after the first drawing and once the font is there to measure the text.
    useEffect(() => {
        if (printed.current || failed) {
            return;
        }

        printed.current = true;
        void document.fonts.ready.then(() => window.print());
    }, [failed]);

    return (
        <>
            <Head title={documentTitle(title, brandOf(shop))} />
            <main className={'bk-labels'}>
                <h1 className={'bk-sr-only'}>{title}</h1>
                {failed ? (
                    <StateNotice state={'UXS-44'} />
                ) : (
                    [...Array(label.count).keys()].map((index) => <LabelCard key={index} label={label} onFailed={() => setFailed(true)} />)
                )}
            </main>
        </>
    );
}

function LabelCard({ label, onFailed }: { label: Label; onFailed: () => void }) {
    const bars = useRef<SVGSVGElement | null>(null);

    useLayoutEffect(() => {
        if (bars.current === null) {
            return;
        }

        try {
            drawCode128(bars.current, label.code);
        } catch {
            onFailed();
        }
    }, [label.code]);

    return (
        <article className={'bk-label'}>
            <p className={'bk-label__name bk-clamp-2'}>{label.name}</p>
            <svg ref={bars} className={'bk-label__bars'} role={'img'} aria-label={label.code} />
            <p className={'bk-label__text'}>{label.code}</p>
            <p className={'bk-label__text'}>{t('documents.label.sku', { sku: label.sku })}</p>
        </article>
    );
}
