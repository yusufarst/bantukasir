import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { CommandForm, Field, SelectInput, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StateNotice } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { visitInDocument } from '@/lib/catalog/Parts';
import { CATALOG_COMMANDS, catalogFieldMessage, productPage, PRODUCTS_SHELL } from '@/lib/catalog/products';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// UXP-306 "Cetak label barcode" (ROUTE_CONTRACTS RP-306, RC-47, RP-306P; COPY_DECK section
// 8.7; INTERACTION_INVENTORY UXI-199 to UXI-201; NAVIGATION_CONTRACTS UXP-306; UI_STATES
// section 6; BR-CAT-35, BR-CAT-36; TASK-018 scope items 3 and 4): one barcode of the
// product, chosen only when it has more than one, and 1 to 100 labels. "Cetak" records the
// print request (RC-47) and opens the print view in its own tab, so that this page stays
// for another print with its number kept. The tab is opened on the press, the only moment
// a browser allows it; it gets its address once the request committed, closes if it did
// not, and without a tab the print view takes this one. A print request is exempt from
// the block of NC-13 and has no uncertain panel: a failure is UXS-44 and is simply asked
// again, a repeat of the same request answering the same print request. Enter never
// prints (UXA-10).

const STALE_STATE: StateId = 'UXS-17';

const FAILED_STATE: StateId = 'UXS-44';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/** The labels one print request may hold: 1 to 100 (DOCUMENT_CONTRACTS DC-05). */
const LABEL_COUNTS: readonly number[] = [...Array(100).keys()].map((index) => index + 1);

const RELOADED: string[] = ['barcodes'];

interface LabelBarcode {
    readonly mappingId: number;
    readonly token: string;
}

interface Product {
    readonly id: number;
    readonly name: string;
}

export default function Labels({ product, barcodes }: { product: Product; barcodes: readonly LabelBarcode[] }) {
    const title = t('catalog.labels.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <LabelsForm title={title} product={product} barcodes={barcodes} />
        </AppShell>
    );
}

/** The whole number of labels typed, or null when it is not one of 1 to 100. */
function labelCount(text: string): number | null {
    const entry = text.trim();

    return LABEL_COUNTS.find((count) => String(count) === entry) ?? null;
}

function LabelsForm({ title, product, barcodes }: { title: string; product: Product; barcodes: readonly LabelBarcode[] }) {
    const [chosen, setChosen] = useState<number | null>(null);
    const [quantity, setQuantity] = useState('1');
    const [opening, setOpening] = useState(0);
    const [stale, setStale] = useState(false);
    const back = productPage(product.id);
    const barcode = barcodes.find((candidate) => candidate.mappingId === chosen) ?? barcodes[0] ?? null;
    const count = labelCount(quantity);

    // Another print starts a new request with a new intent, with the page's fields kept.
    const again = (): void => setOpening((current) => current + 1);

    const onStale = (): void => {
        setStale(true);
        setChosen(null);
        router.reload({ only: RELOADED });
        again();
    };

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('catalog.product_detail.subtitle', { produk: product.name })} back={{ href: back, page: t('catalog.product_detail.title'), onNavigate: (event) => visitInDocument(event, back) }} />}>
            {stale && <StateNotice state={STALE_STATE} />}
            <Card>
                <CommandForm>
                    <PrintRun key={opening} productId={product.id} barcodes={barcodes} barcode={barcode} onBarcode={setChosen} count={count} quantity={quantity} onQuantity={setQuantity} onStart={() => setStale(false)} onPrinted={again} onStale={onStale} />
                </CommandForm>
            </Card>
        </CommandLayout>
    );
}

/**
 * The fields (UXI-199) with "Cetak" (UXI-200), whose request starts again for each print.
 * The fields are read-only while a frozen request is out or may be sent again, so that what
 * they show is what is recorded (INTERACTION_CONTRACTS section 1.3).
 */
function PrintRun({
    productId,
    barcodes,
    barcode,
    onBarcode,
    count,
    quantity,
    onQuantity,
    onStart,
    onPrinted,
    onStale,
}: {
    productId: number;
    barcodes: readonly LabelBarcode[];
    barcode: LabelBarcode | null;
    onBarcode: (mappingId: number | null) => void;
    count: number | null;
    quantity: string;
    onQuantity: (text: string) => void;
    onStart: () => void;
    onPrinted: () => void;
    onStale: () => void;
}) {
    const control = useCommand(CATALOG_COMMANDS.printLabels);
    const { phase } = control;
    const tab = useRef<Window | null>(null);
    const mappingId = barcode?.mappingId ?? null;
    const busy = phase.kind === 'submitting';
    const frozen = busy || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'throttled';
    const canSend = phase.kind === 'idle' || phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'throttled';
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;
    const failed = phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'not_committed' || (refusal !== null && !stale && !invalid);
    const retryable = phase.kind === 'uncertain';

    useEffect(() => {
        if (!busy && !committed && tab.current !== null) {
            tab.current.close();
            tab.current = null;
        }
    }, [busy, committed]);

    useEffect(() => {
        if (stale) {
            onStale();
        }
    }, [stale]);

    useEffect(() => {
        if (!committed) {
            return;
        }

        if (location === undefined) {
            tab.current?.close();
        } else if (tab.current === null) {
            router.visit(location);
        } else {
            tab.current.location.href = location;
        }

        tab.current = null;
        onPrinted();
    }, [committed]);

    const open = (): void => {
        tab.current = window.open('', '_blank');
    };

    // A tab only for a request that really starts: the page's other phases send nothing.
    const print = (): void => {
        if (canSend && mappingId !== null && count !== null) {
            onStart();
            open();
            control.submit({ productId, mappingId, count });
        }
    };

    // The same request after an uncertain answer, which answers the same print request; a
    // held one is an earlier epoch's or another's, so the page is read again; after any
    // other failure a new press is a new request.
    const retry = (): void => {
        if (retryable) {
            open();
            control.resend();
        } else if (phase.kind === 'held') {
            window.location.reload();
        } else {
            onPrinted();
        }
    };

    return (
        <>
            {barcodes.length > 1 ? (
                <Field label={t('catalog.labels.barcode')} required={true}>
                    <SelectInput disabled={frozen} value={barcode === null ? '' : String(barcode.mappingId)} onChange={(event) => onBarcode(barcodes.find((candidate) => String(candidate.mappingId) === event.target.value)?.mappingId ?? null)}>
                        {barcodes.map((candidate) => (
                            <option key={candidate.mappingId} value={String(candidate.mappingId)}>
                                {candidate.token}
                            </option>
                        ))}
                    </SelectInput>
                </Field>
            ) : (
                barcode !== null && <p className={'bk-token'}>{barcode.token}</p>
            )}
            <Field label={t('catalog.labels.quantity')} required={true} hint={t('catalog.labels.size_help')} error={refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.count'])}>
                <TextInput type={'text'} inputMode={'numeric'} autoComplete={'off'} maxLength={3} disabled={frozen} value={quantity} onChange={(event) => onQuantity(event.target.value)} />
            </Field>
            {failed && <StateNotice state={FAILED_STATE} actions={<Button label={t('ux.action.retry')} variant={'secondary'} enterActivates={false} onClick={retry} />} />}
            {!failed && !stale && !invalid && !committed && phase.kind !== 'submitting' && phase.kind !== 'idle' && <CommandPanels control={control} />}
            <div className={'bk-actions'}>
                <Button label={t('catalog.labels.print')} size={'lg'} enterActivates={false} busy={busy} unavailable={phase.kind === 'reload'} disabledReason={mappingId === null || count === null ? t('ux.disabled.form_incomplete') : undefined} onClick={failed ? retry : print} />
            </div>
        </>
    );
}
