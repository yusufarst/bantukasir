import type { ReactNode } from 'react';

export type PaperSize = '58' | '80' | 'a4';

/**
 * Receipt paper of DESIGN_SYSTEM 8.16 and section 12, which TASK-031
 * assembles from the receipt's snapshot: 58 mm, 80 mm and A4, black on white
 * whatever the accent, 12 px minimum. The copy marker of a reprint ("Salinan",
 * BR-DOC-04) prints at the top, bold, in a 1.5 px box. Print styles show only
 * the paper (base.css).
 */
export function ReceiptPaper({ size, label, copyMarker, children }: { size: PaperSize; label: string; copyMarker?: string; children: ReactNode }) {
    return (
        <div className={'bk-paper-stage'}>
            <article className={'bk-paper'} data-paper={size} aria-label={label}>
                {copyMarker !== undefined && <p className={'bk-paper__copy'}>{copyMarker}</p>}
                {children}
            </article>
        </div>
    );
}

export function PaperShop({ name, lines }: { name: string; lines?: readonly string[] }) {
    return (
        <header className={'bk-paper__center'}>
            <p className={'bk-paper__shop'}>{name}</p>
            {lines?.map((line) => <p key={line}>{line}</p>)}
        </header>
    );
}

/** The A4 title, "Nota Penjualan" (BR-DOC-02). */
export function PaperTitle({ children }: { children: ReactNode }) {
    return <h2 className={'bk-paper__title'}>{children}</h2>;
}

export function PaperRule() {
    return <hr className={'bk-paper__rule'} />;
}

export function PaperRow({ label, value, total = false }: { label: ReactNode; value: ReactNode; total?: boolean }) {
    return (
        <p className={total ? 'bk-paper__row bk-paper__total' : 'bk-paper__row'}>
            <span>{label}</span>
            <span>{value}</span>
        </p>
    );
}

export function PaperItems({ children }: { children: ReactNode }) {
    return <ul className={'bk-paper__items'}>{children}</ul>;
}

/**
 * A receipt line: the product name wraps in full on its own line, never cut;
 * the SKU in monospace; quantity × price and the line total on the next line
 * (UXA-15, KS-05).
 */
export function PaperItem({ name, sku, detail, total }: { name: string; sku?: string; detail: string; total: string }) {
    return (
        <li className={'bk-paper__item'}>
            <p className={'bk-paper__name'}>{name}</p>
            {sku !== undefined && <p className={'bk-paper__sku'}>{sku}</p>}
            <p className={'bk-paper__row'}>
                <span>{detail}</span>
                <span>{total}</span>
            </p>
        </li>
    );
}
