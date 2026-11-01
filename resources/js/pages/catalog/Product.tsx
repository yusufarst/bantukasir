import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { SegmentedControl } from '@/components/ui/Choices';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Dialog } from '@/components/ui/Dialog';
import { CommandForm } from '@/components/ui/Field';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import type { StateId } from '@/components/ui/stateCatalogue';
import { Committed, StateMark, StateNotice } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { blockedReason, CodeField } from '@/lib/catalog/Parts';
import { CATALOG_COMMANDS, catalogFieldMessage, checkProduct, NEW_PRODUCT_PAGE, productPage, PRODUCTS_SHELL, productsListAddress, takeFromCreate, type ProductDetail, type ProductRow } from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';

// UXP-302 "Detail produk" (ROUTE_CONTRACTS RP-302, RC-19, RC-20, RC-27 to RC-29; COPY_DECK
// section 8.3; INTERACTION_INVENTORY UXI-165, UXI-166, UXI-168, UXI-171 to UXI-173 and
// UXI-177; NAVIGATION_CONTRACTS UXP-302; BR-CAT-08, BR-CAT-13, BR-CAT-19, BR-CAT-52;
// TASK-017 scope item 1): the product's data, its price and price history, its barcodes,
// and its status, an inactive one marked. A command updates its section in place with
// its message; each asks in a dialog or an inline form that Enter never confirms. No
// control deletes anything (D-042). TASK-018, TASK-020, TASK-022 and TASK-025 add the
// photo, labels, correction, stock and cost history, and TASK-042 and TASK-022 the way
// back to UXP-612 and UXP-006 (UXI-177).

const STALE_STATE: StateId = 'UXS-17';

const DUPLICATE_STATE: StateId = 'UXS-37-barcode';

const INELIGIBLE_STATE: StateId = 'UXS-38';

const STALE_VERSION = 'STALE_VERSION';

const DUPLICATE_BARCODE = 'DUPLICATE_BARCODE';

const BARCODE_NOT_ELIGIBLE = 'BARCODE_NOT_ELIGIBLE';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const RELOADED: string[] = ['product', 'priceHistory', 'barcodes'];

const MANUFACTURER = 'MANUFACTURER';

const MISASSIGNMENT = 'MISASSIGNMENT';

const OTHER = 'OTHER';

type ReasonClass = typeof MISASSIGNMENT | typeof OTHER;

interface PriceEntry {
    readonly amount: string;
    readonly startedAt: string;
    readonly actorDisplayName: string;
}

interface Barcode {
    readonly mappingId: number;
    readonly token: string;
    readonly kind: string;
    readonly active: boolean;
    readonly reasonClass: string | null;
}

export default function Product({ product, priceHistory, barcodes }: { product: ProductDetail; priceHistory: readonly PriceEntry[]; barcodes: readonly Barcode[] | null }) {
    const title = t('catalog.product_detail.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <ProductPage title={title} product={product} priceHistory={priceHistory} barcodes={barcodes} />
        </AppShell>
    );
}

function ProductPage({ title, product, priceHistory, barcodes }: { title: string; product: ProductDetail; priceHistory: readonly PriceEntry[]; barcodes: readonly Barcode[] | null }) {
    const [note, setNote] = useState(takeSavedNote);
    const [stale, setStale] = useState(false);
    const [lifecycle, setLifecycle] = useState(false);
    const [opening, setOpening] = useState(0);
    const [fromCreate] = useState(takeFromCreate);
    const [listPage] = useState(productsListAddress);
    const unresolved = useUnresolved();

    const reload = (): void => router.reload({ only: RELOADED });

    const done = (text: string): void => {
        setLifecycle(false);
        setStale(false);
        setNote(text);
        reload();
    };

    const onStale = (): void => {
        setLifecycle(false);
        setStale(true);
        reload();
    };

    const openLifecycle = (): void => {
        setNote(null);
        setStale(false);
        setOpening((current) => current + 1);
        setLifecycle(true);
    };

    const lifecycleCommand = product.active ? CATALOG_COMMANDS.deactivateProduct : CATALOG_COMMANDS.reactivateProduct;
    const lifecycleReason = blockedReason(unresolved.union, lifecycleCommand);
    const lifecycleLabel = product.active ? t('ux.action.deactivate') : t('ux.action.reactivate');
    const lifecycleEntries: MenuEntry[] = [{ kind: 'action', label: lifecycleLabel, danger: product.active, onSelect: openLifecycle }];
    const editPage = `${productPage(product.id)}/edit`;

    const data: KeyValue[] = [
        { key: 'sku', label: t('catalog.field.sku'), value: product.sku },
        { key: 'name', label: t('catalog.field.name'), value: product.name },
        {
            key: 'category',
            label: t('catalog.field.category'),
            value: (
                <>
                    {product.categoryName} {!product.categoryActive && <StateMark kind={'inactive'} />}
                </>
            ),
        },
        ...(product.brand === null ? [] : [{ key: 'brand', label: t('catalog.field.brand'), value: product.brand }]),
        { key: 'unit', label: t('catalog.field.unit'), value: product.baseUnit },
        { key: 'precision', label: t('catalog.field.precision'), value: String(product.quantityPrecision) },
        { key: 'stock', label: t('catalog.field.min_stock'), value: formatQuantity(product.minimumStock, product.quantityPrecision, product.baseUnit) },
        { key: 'status', label: t('catalog.field.status'), value: product.active ? <StatusWord tone={'success'} label={t('catalog.status.active')} /> : <StateMark kind={'inactive'} /> },
    ];

    return (
        <PageContainer width={'form'}>
            <PageHeader
                title={title}
                subtitle={t('catalog.product_detail.subtitle', { produk: product.name })}
                back={fromCreate ? { href: NEW_PRODUCT_PAGE, page: t('catalog.product_create.title') } : { href: listPage, page: t('catalog.products.title') }}
                actions={
                    <>
                        <ButtonLink href={editPage} label={t('catalog.product_edit.title')} variant={'primary'} />
                        {lifecycleReason === undefined ? (
                            <Menu label={t('ux.action.more_actions')} items={lifecycleEntries} />
                        ) : (
                            <Button label={lifecycleLabel} variant={'secondary'} enterActivates={false} disabledReason={lifecycleReason} />
                        )}
                    </>
                }
            />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {stale && <StateNotice state={STALE_STATE} />}
                {lifecycle && <LifecycleRun key={opening} product={product} onClose={() => setLifecycle(false)} onDone={done} onStale={onStale} />}
                <Section title={t('catalog.product_detail.section_data')}>
                    <KeyValueList items={data} />
                </Section>
                <Section title={t('catalog.product_detail.section_price')}>
                    <div className={'bk-stack'}>
                        <p className={'bk-num'}>{formatMoney(product.price.amount)}</p>
                        <Section title={t('catalog.product_detail.price_history')} level={3} card={false}>
                            <ul className={'bk-stack'}>
                                {priceHistory.map((entry) => (
                                    <li key={entry.startedAt} className={'bk-meta'}>
                                        {t('catalog.product_detail.price_entry', { uang: formatMoney(entry.amount), waktu: formatDateTime(entry.startedAt), nama: entry.actorDisplayName })}
                                    </li>
                                ))}
                            </ul>
                        </Section>
                    </div>
                </Section>
                {barcodes !== null && <BarcodesSection product={product} barcodes={barcodes} onDone={done} onStale={onStale} onOpen={() => setNote(null)} />}
            </div>
        </PageContainer>
    );
}

/** "Nonaktifkan" or "Aktifkan kembali", in its dialog (UXI-166; BR-CAT-08). */
function LifecycleRun({ product, onClose, onDone, onStale }: { product: ProductDetail; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const deactivating = product.active;
    const control = useCommand(deactivating ? CATALOG_COMMANDS.deactivateProduct : CATALOG_COMMANDS.reactivateProduct);
    const { phase } = control;
    const asking = phase.kind === 'idle' || phase.kind === 'submitting';
    const stale = phase.kind === 'rejected' && phase.refusal.code === STALE_VERSION;
    const committed = phase.kind === 'committed';
    const busy = phase.kind === 'submitting';

    useEffect(() => {
        if (stale) {
            onStale();
        }
    }, [stale]);

    useEffect(() => {
        if (committed) {
            onDone(!control.ownRequest ? committedText(control.target?.operation ?? '') : deactivating ? t('catalog.product.deactivated') : t('catalog.product.reactivated'));
        }
    }, [committed]);

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={deactivating ? t('catalog.product.deactivate_confirm.title', { produk: product.name }) : t('catalog.product.reactivate_confirm.title', { produk: product.name })}
                description={<p>{deactivating ? t('catalog.product.deactivate_confirm.body') : t('catalog.product.reactivate_confirm.body')}</p>}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button label={deactivating ? t('ux.action.deactivate') : t('ux.action.reactivate')} variant={deactivating ? 'danger' : 'primary'} enterActivates={false} busy={busy} onClick={() => control.submit({ productId: product.id }, { productVersion: product.version })} />
                    </>
                }
            />
            {!asking && !stale && !committed && (
                <div className={'bk-stack'}>
                    <CommandPanels control={control} />
                    {(phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'throttled') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={onClose} />}
                </div>
            )}
        </>
    );
}

/** The section of the barcodes (UXI-171 to UXI-173), by CAP-06, which the server's prop says. */
function BarcodesSection({ product, barcodes, onDone, onStale, onOpen }: { product: ProductDetail; barcodes: readonly Barcode[]; onDone: (text: string) => void; onStale: () => void; onOpen: () => void }) {
    const [adding, setAdding] = useState(false);
    const [retiring, setRetiring] = useState<Barcode | null>(null);
    const [opening, setOpening] = useState(0);
    const unresolved = useUnresolved();
    const addReason = blockedReason(unresolved.union, CATALOG_COMMANDS.addBarcode);

    const next = (): void => {
        onOpen();
        setOpening((current) => current + 1);
    };

    const finished = (text: string): void => {
        setAdding(false);
        setRetiring(null);
        setOpening((current) => current + 1);
        onDone(text);
    };

    // A new key for each opening: its command starts again with a new intent.
    const generateKey = `generate-${String(opening)}`;
    const retireKey = `retire-${String(opening)}`;

    const retiredMeanwhile = (): void => {
        setRetiring(null);
        onStale();
    };

    const retireEntries = (barcode: Barcode): MenuEntry[] => [
        {
            kind: 'action',
            label: t('catalog.barcode.retire'),
            danger: true,
            onSelect: () => {
                next();
                setRetiring(barcode);
            },
        },
    ];

    const columns: readonly Column<Barcode>[] = [
        {
            id: 'token',
            header: t('catalog.field.barcode'),
            isHead: true,
            cell: (barcode) => (
                <>
                    <span className={'bk-token'}>{barcode.token}</span> <span className={'bk-meta'}>{barcode.kind === MANUFACTURER ? t('catalog.barcode.kind_manufacturer') : t('catalog.barcode.kind_internal')}</span>
                </>
            ),
        },
        {
            id: 'status',
            header: t('catalog.field.status'),
            cell: (barcode) => (barcode.active ? <StatusWord tone={'success'} label={t('catalog.barcode.status_active')} /> : <StatusWord tone={'neutral'} inactive={true} label={retiredText(barcode)} />),
        },
        {
            id: 'actions',
            header: t('ux.action.more_actions'),
            isAction: true,
            cell: (barcode) => (barcode.active && addReason === undefined ? <Menu label={t('ux.action.more_actions')} rowName={barcode.token} items={retireEntries(barcode)} /> : null),
        },
    ];

    return (
        <Section title={t('catalog.product_detail.section_barcodes')}>
            <div className={'bk-stack'}>
                {barcodes.length > 0 && <DataTable caption={t('catalog.product_detail.section_barcodes')} columns={columns} rows={barcodes} rowKey={(barcode) => String(barcode.mappingId)} />}
                {adding ? (
                    <AddBarcodeRun key={opening} product={product} onClose={() => setAdding(false)} onDone={finished} />
                ) : (
                    <div className={'bk-actions'}>
                        <Button
                            label={t('catalog.barcode.add_manufacturer')}
                            variant={'secondary'}
                            enterActivates={false}
                            disabledReason={addReason}
                            onClick={() => {
                                next();
                                setAdding(true);
                            }}
                        />
                    </div>
                )}
                <GenerateBarcodeRun key={generateKey} product={product} onDone={finished} />
                {retiring !== null && <RetireRun key={retireKey} barcode={retiring} onClose={() => setRetiring(null)} onDone={finished} onStale={retiredMeanwhile} />}
            </div>
        </Section>
    );
}

function retiredText(barcode: Barcode): string {
    if (barcode.reasonClass === MISASSIGNMENT) {
        return t('catalog.barcode.retired_reason', { alasan: t('catalog.barcode.reason_misassignment').toLowerCase() });
    }

    return barcode.reasonClass === OTHER ? t('catalog.barcode.retired_reason', { alasan: t('catalog.barcode.reason_other').toLowerCase() }) : t('catalog.barcode.status_retired');
}

/** The inline form of a manufacturer barcode (UXI-171): Enter ends the code and saves nothing. */
function AddBarcodeRun({ product, onClose, onDone }: { product: ProductDetail; onClose: () => void; onDone: (text: string) => void }) {
    const control = useCommand(CATALOG_COMMANDS.addBarcode);
    const { phase, blockedBy } = control;
    const [token, setToken] = useState('');
    const [holder, setHolder] = useState<ProductRow | null>(null);
    const field = useRef<HTMLInputElement | null>(null);
    const committed = phase.kind === 'committed';
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const duplicate = refusal?.code === DUPLICATE_BARCODE;
    const busy = phase.kind === 'submitting';

    useScanCapture(field);

    useEffect(() => {
        field.current?.focus();
    }, []);

    useEffect(() => {
        if (committed) {
            onDone(control.ownRequest ? t('catalog.barcode.added') : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    // The refusal names no product; RD-06 finds the one that holds the code (TASK-016 finding F-1).
    useEffect(() => {
        setHolder(null);

        if (duplicate) {
            void checkProduct({ barcode: token }).then((answer) => {
                if (answer.kind === 'read') {
                    setHolder(answer.body.barcode);
                }
            });
        }
    }, [duplicate]);

    const message = refusal === null ? undefined : catalogFieldMessage(refusal.fields['payload.token']);

    return (
        <div className={'bk-stack'}>
            {duplicate && <StateNotice state={DUPLICATE_STATE} actions={holder === null ? undefined : <ButtonLink href={productPage(holder.id)} label={t('ux.state.duplicate_code.open', { produk: holder.name })} variant={'secondary'} />} />}
            {refusal?.code === BARCODE_NOT_ELIGIBLE && <StateNotice state={INELIGIBLE_STATE} body={message} />}
            <CommandForm>
                <CodeField label={t('catalog.field.barcode')} hint={t('catalog.barcode.token_help')} error={message} value={token} onValueChange={setToken} inputRef={field} />
                <div className={'bk-actions'}>
                    <Button label={t('catalog.barcode.save')} enterActivates={false} busy={busy} disabledReason={token.trim() === '' ? t('ux.disabled.form_incomplete') : blockedBy !== null && !busy ? blockedReason([blockedBy], CATALOG_COMMANDS.addBarcode) : undefined} onClick={() => control.submit({ productId: product.id, token: token.trim() })} />
                    <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} onClick={onClose} />
                </div>
            </CommandForm>
            {refusal?.code !== VALIDATION_FAILED && !duplicate && refusal?.code !== BARCODE_NOT_ELIGIBLE && !committed && <CommandPanels control={control} />}
        </div>
    );
}

/** "Buat barcode internal" (UXI-172): one press sends it; the page's panel holds an unresolved request of the family (NC-13 (b)). */
function GenerateBarcodeRun({ product, onDone }: { product: ProductDetail; onDone: (text: string) => void }) {
    const control = useCommand(CATALOG_COMMANDS.generateBarcode);
    const { phase, blockedBy } = control;
    const committed = phase.kind === 'committed';
    const code = phase.kind === 'committed' ? phase.result?.number : undefined;
    const busy = phase.kind === 'submitting';

    useEffect(() => {
        if (committed) {
            onDone(control.ownRequest && code !== undefined ? t('catalog.barcode.generated', { kode: code }) : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    return (
        <div className={'bk-stack'}>
            <div className={'bk-actions'}>
                <Button label={t('catalog.barcode.generate_internal')} variant={'secondary'} enterActivates={false} busy={busy} disabledReason={blockedBy !== null && !busy ? blockedReason([blockedBy], CATALOG_COMMANDS.generateBarcode) : undefined} onClick={() => control.submit({ productId: product.id })} />
            </div>
            <p className={'bk-hint'}>{t('catalog.barcode.generate_help')}</p>
            {!committed && <CommandPanels control={control} />}
        </div>
    );
}

/** "Pensiunkan barcode", in its dialog with the reason class (UXI-173; BR-CAT-52). */
function RetireRun({ barcode, onClose, onDone, onStale }: { barcode: Barcode; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const control = useCommand(CATALOG_COMMANDS.retireBarcode);
    const { phase } = control;
    const [reason, setReason] = useState<ReasonClass | null>(null);
    const asking = phase.kind === 'idle' || phase.kind === 'submitting';
    const stale = phase.kind === 'rejected' && phase.refusal.code === STALE_VERSION;
    const committed = phase.kind === 'committed';
    const busy = phase.kind === 'submitting';
    const manufacturer = barcode.kind === MANUFACTURER;

    useEffect(() => {
        if (stale) {
            onStale();
        }
    }, [stale]);

    useEffect(() => {
        if (committed) {
            onDone(control.ownRequest ? t('catalog.barcode.retired') : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    const title = t('catalog.barcode.retire_confirm.title', { kode: barcode.token });

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={title}
                description={<p>{manufacturer ? t('catalog.barcode.retire_confirm.body') : t('catalog.barcode.retire_confirm.body_internal')}</p>}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button label={t('catalog.barcode.retire')} variant={'danger'} enterActivates={false} busy={busy} disabledReason={reason === null ? t('ux.disabled.form_incomplete') : undefined} onClick={() => control.submit({ mappingId: barcode.mappingId, reasonClass: reason })} />
                    </>
                }
            >
                <SegmentedControl
                    legend={title}
                    legendHidden={true}
                    value={reason}
                    onValueChange={setReason}
                    options={[
                        { value: MISASSIGNMENT, label: t('catalog.barcode.reason_misassignment') },
                        { value: OTHER, label: t('catalog.barcode.reason_other') },
                    ]}
                />
                {reason === MISASSIGNMENT && manufacturer && <p className={'bk-hint'}>{t('catalog.barcode.reason_misassignment_help')}</p>}
                {reason === OTHER && <p className={'bk-hint'}>{t('catalog.barcode.reason_other_help')}</p>}
            </Dialog>
            {!asking && !stale && !committed && (
                <div className={'bk-stack'}>
                    <CommandPanels control={control} />
                    {(phase.kind === 'rejected' || phase.kind === 'not_committed' || phase.kind === 'throttled') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={onClose} />}
                </div>
            )}
        </>
    );
}
