import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState, type MouseEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, LoadFailed, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { CategoryField, CodeField, PrecisionField, UnresolvedReason } from '@/lib/catalog/Parts';
import {
    CATALOG_COMMANDS,
    catalogFieldMessage,
    checkProduct,
    precisionOf,
    productPage,
    PRODUCTS_SHELL,
    productsListAddress,
    quantityFromEntry,
    type Category,
    type CheckResult,
    type ProductRow,
} from '@/lib/catalog/products';
import { useScanCapture } from '@/lib/catalog/scanCapture';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// UXP-303 "Tambah produk" (ROUTE_CONTRACTS RP-303, RD-06, RC-17; COPY_DECK section 8.4;
// INTERACTION_INVENTORY UXI-178 to UXI-185; NAVIGATION_CONTRACTS UXP-303; WF-14;
// BR-CAT-03, BR-CAT-04; TASK-017 scope item 1). Step 1 searches first: "Cek", or the
// Enter that ends a scanned code in "Barcode", reads RD-06. An exact barcode or SKU shows
// its product and goes no further (UXS-37); similar names need "Tetap buat produk baru"
// (UXS-47); nothing found goes on with "Lanjut isi data". What was typed carries into
// step 2, the form, whose "Simpan produk" Enter never activates. A taken SKU or barcode
// is refused at its field, with a link to the product that RD-06 finds, since the refusal
// names none (TASK-016 finding F-1). Success leads to UXP-302 of the new product, with
// stock 0. Leaving with data entered asks first (UXS-45). TASK-022 and TASK-035 open the
// page over their working pages (NC-11): `CreateFlow` with `over`, whose back and cancel return
// to the working page and whose success hands the new product to it; `code` carries their
// unknown code. The working page then holds the one unsaved-changes guard of the document.

const SKU_STATE: StateId = 'UXS-37-sku';

const BARCODE_STATE: StateId = 'UXS-37-barcode';

const INELIGIBLE_STATE: StateId = 'UXS-38';

const CATEGORY_STATE: StateId = 'UXS-32-category';

const SIMILAR_STATE: StateId = 'UXS-47';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const DUPLICATE_SKU = 'DUPLICATE_SKU';

const DUPLICATE_BARCODE = 'DUPLICATE_BARCODE';

const BARCODE_NOT_ELIGIBLE = 'BARCODE_NOT_ELIGIBLE';

const CATEGORY_INACTIVE = 'CATEGORY_INACTIVE';

const CATEGORIES_PROP = 'categories';

const BARCODE_PATH = 'payload.barcode.token';

/** What the check of step 1 shows. */
type Check =
    | { readonly kind: 'none' }
    | { readonly kind: 'checking' }
    | { readonly kind: 'failed' }
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'found'; readonly result: CheckResult };

export default function ProductCreate({ categories, code }: { categories: readonly Category[]; code: string | null }) {
    const title = t('catalog.product_create.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <GuardedCreateFlow title={title} categories={categories} code={code} />
        </AppShell>
    );
}

/** UXP-303 as its own page, with its unsaved-changes guard (UXS-45). */
function GuardedCreateFlow({ title, categories, code }: { title: string; categories: readonly Category[]; code: string | null }) {
    const [dirty, setDirty] = useState(false);

    useUnsavedChanges(dirty);

    return <CreateFlow title={title} categories={categories} code={code} onDirty={setDirty} />;
}

/**
 * UXP-303 over a working page (NC-11; UXA-05): the page it covers stays, with its draft, and
 * these return to it — `onBack` from its back and cancel, `onCreated` with the new product.
 */
export interface OverWorkingPage {
    readonly page: string;
    readonly href: string;
    readonly onBack: () => void;
    readonly onCreated: (product: { readonly id: number; readonly sku: string }) => void;
}

/** The products that hold the barcode or SKU, each once. */
function exactProducts(result: CheckResult): readonly ProductRow[] {
    const barcode = result.barcode === null ? [] : [result.barcode];

    return result.sku === null || result.sku.id === result.barcode?.id ? barcode : [...barcode, result.sku];
}

export function CreateFlow({
    title,
    categories,
    code,
    onDirty,
    over,
}: {
    title: string;
    categories: readonly Category[];
    code: string | null;
    /** Whether data was entered and not sent, for the guard of the page that holds it. */
    onDirty: (dirty: boolean) => void;
    over?: OverWorkingPage;
}) {
    const control = useCommand(CATALOG_COMMANDS.createProduct);
    const { phase, blockedBy } = control;
    const pageGuard = useLeaveGuard();
    // Over a working page, back and cancel return to it and leave nothing (NC-11).
    const guard = (event: MouseEvent<HTMLAnchorElement>): void => {
        if (over === undefined) {
            pageGuard(event);

            return;
        }

        event.preventDefault();
        over.onBack();
    };
    const ids = useId();
    const [step, setStep] = useState<'search' | 'form'>('search');
    const [barcode, setBarcode] = useState(code ?? '');
    const [sku, setSku] = useState('');
    const [name, setName] = useState('');
    const [category, setCategory] = useState('');
    const [brand, setBrand] = useState('');
    const [unit, setUnit] = useState('');
    const [precision, setPrecision] = useState('0');
    const [price, setPrice] = useState<string | null>(null);
    const [stock, setStock] = useState('0');
    const [check, setCheck] = useState<Check>({ kind: 'none' });
    const [holder, setHolder] = useState<ProductRow | null>(null);
    // The name of the category that the request sent, which a refusal of it names (UXS-32): the list may no longer hold it.
    const [sentCategory, setSentCategory] = useState('');
    const [listPage] = useState(() => over?.href ?? productsListAddress());
    const latestCheck = useRef(0);
    const barcodeField = useRef<HTMLInputElement | null>(null);

    // In step 1 a scan reaches "Barcode"; in step 2 every field is ordinary typing (UXA-11).
    useScanCapture(barcodeField, step === 'search');

    const committed = phase.kind === 'committed' && control.ownRequest;
    const adoptedDone = phase.kind === 'committed' && !control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;
    const goToForm = (): void => setStep('form');
    const typed = barcode !== (code ?? '') || sku !== '' || name !== '' || category !== '' || brand !== '' || unit !== '' || precision !== '0' || price !== null || stock !== '0';

    const dirty = typed && !committed;

    useEffect(() => onDirty(dirty), [dirty]);

    useEffect(() => {
        if (location === undefined) {
            return;
        }

        if (over !== undefined) {
            const result = phase.kind === 'committed' ? phase.result : null;
            const id = result?.id;

            if (typeof id === 'number') {
                over.onCreated({ id, sku: sku.trim() });
            }

            return;
        }

        leaveSavedNote(t('catalog.product_create.success', { produk: name.trim() }));
        router.visit(location);
    }, [location]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const duplicate = refusal?.code === DUPLICATE_SKU || refusal?.code === DUPLICATE_BARCODE;

    // The refusal names no product; RD-06 finds the one that holds the code, for the link of UXS-37.
    useEffect(() => {
        setHolder(null);

        if (!duplicate) {
            return;
        }

        const taken = refusal?.code === DUPLICATE_SKU ? { sku } : { barcode };

        void checkProduct(taken).then((answer) => {
            if (answer.kind === 'read') {
                setHolder(answer.body.sku ?? answer.body.barcode);
            }
        });
    }, [refusal?.code, duplicate]);

    useEffect(() => {
        if (refusal?.code === CATEGORY_INACTIVE) {
            router.reload({ only: [CATEGORIES_PROP] });
        }
    }, [refusal?.code]);

    /** A change of what was checked makes the check's answer out of date. */
    const edited = (set: (value: string) => void) => (value: string) => {
        latestCheck.current += 1;
        setCheck({ kind: 'none' });
        set(value);
    };

    const anything = barcode.trim() !== '' || sku.trim() !== '' || name.trim() !== '';

    const runCheck = (): void => {
        if (!anything) {
            return;
        }

        latestCheck.current += 1;
        const mine = latestCheck.current;

        setCheck({ kind: 'checking' });
        void checkProduct({ barcode, sku, name }).then((answer) => {
            // A late answer never replaces a newer one (BR-CAT-30, TR-PAGE-04).
            if (mine !== latestCheck.current) {
                return;
            }

            if (answer.kind === 'read') {
                setCheck({ kind: 'found', result: answer.body });
            } else if (answer.kind === 'invalid') {
                setCheck({ kind: 'invalid', fields: answer.fields });
            } else {
                setCheck({ kind: 'failed' });
            }
        });
    };

    const header = <PageHeader title={title} back={{ href: listPage, page: over?.page ?? t('catalog.products.title'), onNavigate: guard }} />;
    const cancel = <ButtonLink href={listPage} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />;

    if (step === 'search') {
        const found = check.kind === 'found' ? check.result : null;
        const exact = found === null ? [] : exactProducts(found);
        const similar = found !== null && exact.length === 0 ? found.similarNames : [];
        const nothing = found !== null && exact.length === 0 && similar.length === 0;
        const fields = check.kind === 'invalid' ? check.fields : {};

        return (
            <CommandLayout header={header}>
                <Section title={t('catalog.product_create.step_search')}>
                    <div className={'bk-stack'}>
                        <p>{t('catalog.product_create.search_intro')}</p>
                        <CodeField label={t('catalog.field.barcode')} error={catalogFieldMessage(fields.barcode)} value={barcode} onValueChange={edited(setBarcode)} onEnter={runCheck} inputRef={barcodeField} />
                        <Field label={t('catalog.field.sku')} error={catalogFieldMessage(fields.sku)}>
                            <TextInput type={'text'} autoComplete={'off'} maxLength={64} value={sku} onChange={(event) => edited(setSku)(event.target.value)} />
                        </Field>
                        <Field label={t('catalog.field.name')} error={catalogFieldMessage(fields.name)}>
                            <TextInput type={'text'} autoComplete={'off'} maxLength={200} value={name} onChange={(event) => edited(setName)(event.target.value)} />
                        </Field>
                        <div className={'bk-actions'}>
                            <Button label={t('catalog.product_create.check')} busy={check.kind === 'checking'} disabledReason={anything ? undefined : t('ux.disabled.form_incomplete')} onClick={runCheck} />
                            {cancel}
                        </div>
                    </div>
                </Section>
                {check.kind === 'failed' && <LoadFailed onRetry={runCheck} />}
                {exact.length > 0 && (
                    <Notice tone={'danger'} urgency={'alert'} title={t('ux.state.duplicate_code.sku.title')}>
                        <p>{t('catalog.product_create.exact_match')}</p>
                        <ul>
                            {exact.map((product) => (
                                <li key={product.id}>
                                    <a className={'bk-table-link'} href={productPage(product.id)}>
                                        {product.name}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </Notice>
                )}
                {similar.length > 0 && (
                    <StateNotice state={SIMILAR_STATE} actions={<Button label={t('catalog.product_create.create_anyway')} onClick={goToForm} />}>
                        <ul>
                            {similar.map((product) => (
                                <li key={product.id}>
                                    <a className={'bk-table-link'} href={productPage(product.id)}>
                                        {product.name}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </StateNotice>
                )}
                {nothing && (
                    <Notice tone={'neutral'} urgency={'status'} actions={<Button label={t('catalog.product_create.continue')} onClick={goToForm} />}>
                        <p>{t('catalog.product_create.nothing_found')}</p>
                    </Notice>
                )}
                <CommandPanels control={control} />
            </CommandLayout>
        );
    }

    const chosen = categories.find((candidate) => String(candidate.id) === category);
    const invalid = refusal?.code === VALIDATION_FAILED;
    const busy = phase.kind === 'submitting';
    const incomplete = sku.trim() === '' || name.trim() === '' || chosen === undefined || unit.trim() === '' || price === null || stock.trim() === '';
    const reason = incomplete ? t('ux.disabled.form_incomplete') : blockedBy !== null && !busy ? <UnresolvedReason /> : undefined;

    const save = (): void => {
        if (chosen !== undefined && price !== null) {
            setSentCategory(chosen.name);
            control.submit({
                sku: sku.trim(),
                name: name.trim(),
                categoryId: chosen.id,
                brand: brand.trim() === '' ? null : brand.trim(),
                baseUnit: unit.trim(),
                quantityPrecision: precisionOf(precision),
                sellingPrice: price,
                minimumStock: quantityFromEntry(stock),
                ...(barcode.trim() === '' ? {} : { barcode: { token: barcode.trim() } }),
            });
        }
    };

    const fieldId = (field: string): string => `${ids}-${field}`;
    const summary: FieldError[] = [];
    const add = (path: string, id: string, label: string): string | undefined => {
        const text = refusal === null ? undefined : catalogFieldMessage(refusal.fields[path], sentCategory);

        if (text !== undefined) {
            summary.push({ fieldId: fieldId(id), label, message: text });
        }

        return text;
    };
    const skuError = add('payload.sku', 'sku', t('catalog.field.sku'));
    const nameError = add('payload.name', 'name', t('catalog.field.name'));
    const categoryError = add('payload.categoryId', 'category', t('catalog.field.category'));
    const brandError = add('payload.brand', 'brand', t('catalog.field.brand'));
    const unitError = add('payload.baseUnit', 'unit', t('catalog.field.unit'));
    const precisionError = add('payload.quantityPrecision', 'precision', t('catalog.field.precision'));
    const priceError = add('payload.sellingPrice', 'price', t('catalog.field.selling_price'));
    const stockError = add('payload.minimumStock', 'stock', t('catalog.field.min_stock'));
    const barcodeError = add(BARCODE_PATH, 'barcode', t('catalog.field.manufacturer_barcode'));
    // Over a working page the product is not opened: that would leave the draft (NC-11).
    const holderLink = holder === null || over !== undefined ? undefined : <ButtonLink href={productPage(holder.id)} label={t('ux.state.duplicate_code.open', { produk: holder.name })} variant={'secondary'} onClick={guard} />;

    return (
        <CommandLayout header={header}>
            {invalid && <ErrorSummary errors={summary} />}
            {refusal?.code === DUPLICATE_SKU && <StateNotice state={SKU_STATE} actions={holderLink} />}
            {refusal?.code === DUPLICATE_BARCODE && <StateNotice state={BARCODE_STATE} actions={holderLink} />}
            {refusal?.code === BARCODE_NOT_ELIGIBLE && <StateNotice state={INELIGIBLE_STATE} body={barcodeError} />}
            {refusal?.code === CATEGORY_INACTIVE && <StateNotice state={CATEGORY_STATE} body={t('ux.state.inactive.category.body', { kategori: sentCategory })} />}
            <Card>
                <CommandForm>
                    <Field id={fieldId('sku')} label={t('catalog.field.sku')} required={true} error={skuError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={64} value={sku} onChange={(event) => setSku(event.target.value)} />
                    </Field>
                    <Field id={fieldId('name')} label={t('catalog.field.name')} required={true} error={nameError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={200} value={name} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <CategoryField id={fieldId('category')} categories={categories} value={category} error={categoryError} onChange={setCategory} />
                    <Field id={fieldId('brand')} label={t('catalog.field.brand')} optional={true} error={brandError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={100} value={brand} onChange={(event) => setBrand(event.target.value)} />
                    </Field>
                    <Field id={fieldId('unit')} label={t('catalog.field.unit')} required={true} hint={t('catalog.field.unit_help')} error={unitError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={32} value={unit} onChange={(event) => setUnit(event.target.value)} />
                    </Field>
                    <PrecisionField id={fieldId('precision')} value={precision} hint={t('catalog.field.precision_help')} error={precisionError} onChange={setPrecision} />
                    <Field id={fieldId('price')} label={t('catalog.field.selling_price')} required={true} error={priceError}>
                        <MoneyInput value={price} onValueChange={setPrice} />
                    </Field>
                    <Field id={fieldId('stock')} label={t('catalog.field.min_stock')} required={true} hint={t('catalog.field.min_stock_help')} error={stockError}>
                        <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} value={stock} onChange={(event) => setStock(event.target.value)} />
                    </Field>
                    <CodeField id={fieldId('barcode')} label={t('catalog.field.manufacturer_barcode')} optional={true} hint={t('catalog.barcode.token_help')} error={barcodeError} value={barcode} onValueChange={setBarcode} />
                    <p className={'bk-hint'}>{t('catalog.product_create.zero_stock_help')}</p>
                    <div className={'bk-actions'}>
                        <Button label={t('catalog.product_create.save')} size={'lg'} enterActivates={false} busy={busy} disabledReason={reason} onClick={save} />
                        {cancel}
                        {((phase.kind === 'rejected' && !invalid) || phase.kind === 'not_committed' || adoptedDone) && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            {!invalid && <CommandPanels control={control} {...(committed ? { committed: t('catalog.product_create.success', { produk: name.trim() }) } : {})} />}
        </CommandLayout>
    );
}
