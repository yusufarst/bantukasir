import { router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { CategoryField, PrecisionField, UnresolvedReason } from '@/lib/catalog/Parts';
import { CATALOG_COMMANDS, catalogFieldMessage, precisionOf, productPage, PRODUCTS_SHELL, quantityFromEntry, type Category, type ProductDetail } from '@/lib/catalog/products';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatMoney, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';

// UXP-304 "Ubah produk" (ROUTE_CONTRACTS RP-304, RC-18; COPY_DECK section 8.5;
// INTERACTION_INVENTORY UXI-186 to UXI-189; NAVIGATION_CONTRACTS UXP-304; BR-CAT-07,
// BR-CAT-13; TASK-017 scope item 1): the form with the product's version. A category
// that the product already has stays in the list even when inactive (RC-18). A changed
// price shows old and new, and appends a revision. Unit and precision are disabled with
// their reason once the product has a movement, which the server says (TASK-021).
// "Simpan perubahan" is enabled once a field changed (UXS-13), never by Enter. A version
// changed meanwhile shows the current values (UXS-17), and the next save is expected
// against them. Success leads to UXP-302 with its message; leaving with changes asks
// first (UXS-45).

const STALE_STATE: StateId = 'UXS-17';

const CATEGORY_STATE: StateId = 'UXS-32-category';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const CATEGORY_INACTIVE = 'CATEGORY_INACTIVE';

const PROPS: string[] = ['product', 'categories'];

export default function ProductEdit({ product, categories }: { product: ProductDetail; categories: readonly Category[] }) {
    const title = t('catalog.product_edit.title');

    return (
        <AppShell title={title} {...PRODUCTS_SHELL}>
            <EditForm title={title} product={product} categories={categories} />
        </AppShell>
    );
}

function EditForm({ title, product, categories }: { title: string; product: ProductDetail; categories: readonly Category[] }) {
    const control = useCommand(CATALOG_COMMANDS.updateProduct);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const ids = useId();
    const initialStock = formatQuantity(product.minimumStock, product.quantityPrecision);
    const [name, setName] = useState(product.name);
    const [category, setCategory] = useState(String(product.categoryId));
    const [brand, setBrand] = useState(product.brand ?? '');
    const [stock, setStock] = useState(initialStock);
    const [price, setPrice] = useState<string | null>(product.price.amount);
    const [unit, setUnit] = useState(product.baseUnit);
    const [precision, setPrecision] = useState(String(product.quantityPrecision));
    // The name of the category that the request sent, which a refusal of it names (UXS-32): the list may no longer hold it.
    const [sentCategory, setSentCategory] = useState('');
    const detail = productPage(product.id);

    const priceChanged = price !== product.price.amount;
    const changed =
        name !== product.name ||
        category !== String(product.categoryId) ||
        brand !== (product.brand ?? '') ||
        stock !== initialStock ||
        priceChanged ||
        unit !== product.baseUnit ||
        precision !== String(product.quantityPrecision);
    const committed = phase.kind === 'committed' && control.ownRequest;
    const adoptedDone = phase.kind === 'committed' && !control.ownRequest;
    const location = phase.kind === 'committed' && control.ownRequest ? phase.result?.location : undefined;

    useUnsavedChanges(changed && !committed);

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(t('catalog.product_edit.success'));
            router.visit(location);
        }
    }, [location]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const inactive = refusal?.code === CATEGORY_INACTIVE;
    const invalid = refusal?.code === VALIDATION_FAILED;

    useEffect(() => {
        if (stale || inactive) {
            router.reload({ only: PROPS });
        }
    }, [stale, inactive]);

    const busy = phase.kind === 'submitting';
    const chosen = categories.find((candidate) => String(candidate.id) === category);
    const incomplete = name.trim() === '' || chosen === undefined || unit.trim() === '' || price === null || stock.trim() === '';
    const reason = !changed ? t('ux.disabled.no_change') : incomplete ? t('ux.disabled.form_incomplete') : blockedBy !== null && !busy ? <UnresolvedReason /> : undefined;

    const save = (): void => {
        if (chosen !== undefined && price !== null) {
            setSentCategory(chosen.name);
            control.submit(
                {
                    productId: product.id,
                    name: name.trim(),
                    categoryId: chosen.id,
                    brand: brand.trim() === '' ? null : brand.trim(),
                    baseUnit: unit.trim(),
                    quantityPrecision: precisionOf(precision),
                    sellingPrice: price,
                    minimumStock: quantityFromEntry(stock),
                },
                { productVersion: product.version },
            );
        }
    };

    const fieldId = (field: string): string => `${ids}-${field}`;
    const message = (field: string): string | undefined => (refusal === null ? undefined : catalogFieldMessage(refusal.fields[`payload.${field}`], sentCategory));
    const summary: FieldError[] = [];
    const add = (field: string, label: string): string | undefined => {
        const text = message(field);

        if (text !== undefined) {
            summary.push({ fieldId: fieldId(field), label, message: text });
        }

        return text;
    };
    const nameError = add('name', t('catalog.field.name'));
    const categoryError = add('categoryId', t('catalog.field.category'));
    const brandError = add('brand', t('catalog.field.brand'));
    const stockError = add('minimumStock', t('catalog.field.min_stock'));
    const priceError = add('sellingPrice', t('catalog.field.selling_price'));
    const unitError = add('baseUnit', t('catalog.field.unit'));
    const precisionError = add('quantityPrecision', t('catalog.field.precision'));

    const currentValues: KeyValue[] = [
        { key: 'name', label: t('catalog.field.name'), value: product.name },
        { key: 'category', label: t('catalog.field.category'), value: product.categoryName },
        { key: 'brand', label: t('catalog.field.brand'), value: product.brand ?? '' },
        { key: 'stock', label: t('catalog.field.min_stock'), value: formatQuantity(product.minimumStock, product.quantityPrecision) },
        { key: 'price', label: t('catalog.field.selling_price'), value: formatMoney(product.price.amount) },
        { key: 'unit', label: t('catalog.field.unit'), value: product.baseUnit },
        { key: 'precision', label: t('catalog.field.precision'), value: String(product.quantityPrecision) },
    ];

    return (
        <CommandLayout header={<PageHeader title={title} subtitle={t('catalog.product_detail.subtitle', { produk: product.name })} back={{ href: detail, page: t('catalog.product_detail.title'), onNavigate: guard }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {inactive && <StateNotice state={CATEGORY_STATE} body={t('ux.state.inactive.category.body', { kategori: sentCategory })} />}
            {stale && (
                <>
                    <StateNotice state={STALE_STATE} />
                    <KeyValueList items={currentValues} />
                </>
            )}
            <Card>
                <CommandForm>
                    <Field id={fieldId('name')} label={t('catalog.field.name')} required={true} error={nameError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={200} value={name} onChange={(event) => setName(event.target.value)} />
                    </Field>
                    <CategoryField id={fieldId('categoryId')} categories={categories} value={category} error={categoryError} onChange={setCategory} />
                    <Field id={fieldId('brand')} label={t('catalog.field.brand')} optional={true} error={brandError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={100} value={brand} onChange={(event) => setBrand(event.target.value)} />
                    </Field>
                    <Field id={fieldId('minimumStock')} label={t('catalog.field.min_stock')} required={true} hint={t('catalog.field.min_stock_help')} error={stockError}>
                        <TextInput type={'text'} inputMode={'decimal'} autoComplete={'off'} numeric={true} value={stock} onChange={(event) => setStock(event.target.value)} />
                    </Field>
                    <Field
                        id={fieldId('sellingPrice')}
                        label={t('catalog.field.selling_price')}
                        required={true}
                        hint={
                            priceChanged && price !== null ? (
                                <>
                                    {t('catalog.product_edit.price_change', { lama: formatMoney(product.price.amount), baru: formatMoney(price) })} {t('catalog.product_edit.price_help')}
                                </>
                            ) : (
                                t('catalog.product_edit.price_help')
                            )
                        }
                        error={priceError}
                    >
                        <MoneyInput value={price} onValueChange={setPrice} />
                    </Field>
                    <Field id={fieldId('baseUnit')} label={t('catalog.field.unit')} required={true} hint={product.unitLocked ? t('catalog.field.unit_lock_help') : t('catalog.field.unit_help')} error={unitError}>
                        <TextInput type={'text'} autoComplete={'off'} maxLength={32} value={unit} disabled={product.unitLocked} onChange={(event) => setUnit(event.target.value)} />
                    </Field>
                    <PrecisionField id={fieldId('quantityPrecision')} value={precision} disabled={product.unitLocked} hint={product.unitLocked ? t('catalog.field.unit_lock_help') : t('catalog.field.precision_help')} error={precisionError} onChange={setPrecision} />
                    <div className={'bk-actions'}>
                        <Button label={t('ux.action.save_changes')} size={'lg'} enterActivates={false} busy={busy} disabledReason={reason} onClick={save} />
                        <ButtonLink href={detail} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                        {((phase.kind === 'rejected' && !stale) || phase.kind === 'not_committed' || adoptedDone) && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            {!invalid && !inactive && <CommandPanels control={control} {...(committed ? { committed: t('catalog.product_edit.success') } : {})} />}
        </CommandLayout>
    );
}
