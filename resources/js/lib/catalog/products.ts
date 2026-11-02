import { leaveSignedInSession } from '@/lib/browserSession';
import { requestJson } from '@/lib/commands/client';
import { t } from '@/lib/i18n';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';

// The catalogue pages (COPY_DECK sections 8.1 to 8.6 and 8.8; ROUTE_CONTRACTS RP-301 to
// RP-305 and RP-307; INTERACTION_CONTRACTS sections 3.1 and 3.5; TASK-017 scope item 1):
// the records as the server sends them, the commands of RC-17 to RC-24 and RC-27 to
// RC-30, the reads RD-01, RD-05 and RD-06, the messages of the fields, and what passes from
// the list to its child pages. A filter or a page is kept in the page's props, which
// the encrypted history state holds, so that the browser's Back finds them (NC-10). The
// way back of a child page is a new entry of the history, so it takes the list's address
// from the document's referrer, the address that opened it: a search text is never in
// the browser's storage (SEC-CH-05).

export const PRODUCTS_PAGE = '/products';

export const NEW_PRODUCT_PAGE = '/products/new';

export const CATEGORIES_PAGE = '/categories';

export function productPage(id: number): string {
    return `${PRODUCTS_PAGE}/${String(id)}`;
}

/** UXP-306 of a product (ROUTE_CONTRACTS RP-306). */
export function labelsPage(id: number): string {
    return `${productPage(id)}/labels`;
}

/** UXP-305, opened from a product (ROUTE_CONTRACTS RP-305). */
export function correctionPage(id: number): string {
    return `${productPage(id)}/barcode-correction`;
}

/** Where the catalogue's pages stand in the shell. */
export const PRODUCTS_SHELL = { current: 'products', area: 'products', subCurrent: 'products' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

export const CATEGORIES_SHELL = { current: 'products', area: 'products', subCurrent: 'categories' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** The family of every command of these pages (INTERACTION_CONTRACTS section 1.4). */
export const CATALOG_COMMANDS = {
    createProduct: { operation: 'product.create', family: 'product_create' },
    updateProduct: { operation: 'product.update', family: 'product_edit' },
    deactivateProduct: { operation: 'product.deactivate', family: 'product_edit' },
    reactivateProduct: { operation: 'product.reactivate', family: 'product_edit' },
    createCategory: { operation: 'category.create', family: 'category' },
    renameCategory: { operation: 'category.rename', family: 'category' },
    deactivateCategory: { operation: 'category.deactivate', family: 'category' },
    reactivateCategory: { operation: 'category.reactivate', family: 'category' },
    addBarcode: { operation: 'barcode.add', family: 'barcode' },
    generateBarcode: { operation: 'barcode.generate', family: 'barcode' },
    retireBarcode: { operation: 'barcode.retire', family: 'barcode' },
    correctBarcode: { operation: 'barcode.correct_misassignment', family: 'barcode_correction', recentAuth: true },
    setPhoto: { operation: 'product_photo.set', family: 'photo' },
    removePhoto: { operation: 'product_photo.remove', family: 'photo' },
    printLabels: { operation: 'document.print_labels', family: 'print' },
    confirmImport: { operation: 'csv_import.confirm', family: 'csv_import' },
} as const;

/** A result of RD-01 or RD-06 (App\DTO\Catalog\ProductRowData). */
export interface ProductRow {
    readonly id: number;
    readonly name: string;
    readonly sku: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly price: { readonly amount: string; readonly revisionId: number };
    readonly active: boolean;
    readonly thumbnail: string | null;
    readonly activeBarcode: boolean;
}

/** A category of the pages (App\DTO\Catalog\CategoryRowData). */
export interface Category {
    readonly id: number;
    readonly name: string;
    readonly active: boolean;
    readonly version: number;
}

/** A product of UXP-302 and UXP-304 (App\DTO\Catalog\ProductDetailData). */
export interface ProductDetail {
    readonly id: number;
    readonly sku: string;
    readonly name: string;
    readonly categoryId: number;
    readonly categoryName: string;
    readonly categoryActive: boolean;
    readonly brand: string | null;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly price: { readonly amount: string; readonly revisionId: number };
    readonly minimumStock: string;
    readonly active: boolean;
    readonly version: number;
    readonly unitLocked: boolean;
    readonly photo: string | null;
}

/** The filters and page of UXP-301 (ROUTE_CONTRACTS RP-301). */
export interface ProductFilters {
    readonly q: string;
    readonly category: number | null;
    readonly brand: string;
    readonly status: string | null;
    readonly page: number;
    /** The CSV import whose products the list holds: RC-31's location (TASK-019). */
    readonly import?: number | null;
}

/** The precisions a product may have (BR-NUM-02). */
export const PRECISIONS: readonly number[] = [0, 1, 2, 3];

/** A precision of the form's list, from the option's text; 0 for anything else. */
export function precisionOf(value: string): number {
    return PRECISIONS.find((precision) => String(precision) === value) ?? 0;
}

/** The address of UXP-301 with its filters; a filter that is not set is absent. */
export function productsAddress(filters: ProductFilters): string {
    const query = new URLSearchParams();

    if (filters.q !== '') {
        query.set('q', filters.q);
    }

    if (filters.category !== null) {
        query.set('category', String(filters.category));
    }

    if (filters.brand !== '') {
        query.set('brand', filters.brand);
    }

    if (filters.status !== null) {
        query.set('status', filters.status);
    }

    if (filters.page > 1) {
        query.set('page', String(filters.page));
    }

    if (filters.import !== undefined && filters.import !== null) {
        query.set('import', String(filters.import));
    }

    const search = query.toString();

    return search === '' ? PRODUCTS_PAGE : `${PRODUCTS_PAGE}?${search}`;
}

/** The page of this application that opened this document, or null. */
function openerOf(referrer: string, origin: string): URL | null {
    try {
        const opener = new URL(referrer);

        return opener.origin === origin ? opener : null;
    } catch {
        return null;
    }
}

/** The address of UXP-301 with the filters it stood at when it opened this document, or the whole list (NC-01, NC-10). */
export function listAddressFrom(referrer: string, origin: string): string {
    const opener = openerOf(referrer, origin);

    return opener?.pathname === PRODUCTS_PAGE ? `${PRODUCTS_PAGE}${opener.search}` : PRODUCTS_PAGE;
}

/** Whether the search-first step of UXP-303 opened this document (NC-01; UXI-177, UXI-179). */
export function openedFromCreate(referrer: string, origin: string): boolean {
    return openerOf(referrer, origin)?.pathname === NEW_PRODUCT_PAGE;
}

/** The way back of this document to the list. */
export function productsListAddress(): string {
    return listAddressFrom(document.referrer, window.location.origin);
}

/** Whether the way back of this document leads to the search-first step. */
export function cameFromCreate(): boolean {
    return openedFromCreate(document.referrer, window.location.origin);
}

/**
 * A quantity as typed, in the canonical form: "1,5" is 1.5 and "1.250,5" is 1250.5, the
 * format of COPY_DECK section 1.3. A text that is neither stays as typed, and the server
 * refuses it at the field, never read as another number.
 */
export function quantityFromEntry(text: string): string {
    const entry = text.trim();
    const grouped = /^[0-9]{1,3}(\.[0-9]{3})+(,[0-9]*)?$/.test(entry);

    return (grouped ? entry.replace(/\./g, '') : entry).replace(',', '.');
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** What a read of the catalogue answered. */
export type ReadAnswer<Body> =
    | { readonly kind: 'read'; readonly body: Body }
    /** VALIDATION_FAILED, with the field entries of RT-07; the earlier results stay (INTERACTION_CONTRACTS section 3.2). */
    | { readonly kind: 'invalid'; readonly fields: Readonly<Record<string, unknown>> }
    | { readonly kind: 'failed' };

/** A read of the user's own request (never RT-10's background marker). A signed-out answer ends the page. */
async function read<Body>(url: string, parse: (body: Readonly<Record<string, unknown>>) => Body, headers?: Readonly<Record<string, string>>): Promise<ReadAnswer<Body>> {
    const arrived = await requestJson('GET', url, undefined, { waitMs: 30_000, ...(headers === undefined ? {} : { headers }) });

    if (arrived?.status === 401) {
        leaveSignedInSession();

        return { kind: 'failed' };
    }

    if (arrived?.status === 200 && isRecord(arrived.body)) {
        return { kind: 'read', body: parse(arrived.body) };
    }

    if (arrived?.status === 422 && isRecord(arrived.body) && isRecord(arrived.body.fields)) {
        return { kind: 'invalid', fields: arrived.body.fields };
    }

    return { kind: 'failed' };
}

export interface SearchPage {
    readonly rows: readonly ProductRow[];
    readonly hasMore: boolean;
}

function rowsOf(value: unknown): readonly ProductRow[] {
    return Array.isArray(value) ? (value as readonly ProductRow[]) : [];
}

/** The next value of `X-Search-Sequence` of this document (INTERACTION_CONTRACTS section 3.1). */
let lastSequence = 0;

/** RD-01 in the `catalog` context with these parameters. */
export function searchProducts(parameters: Readonly<Record<string, string>>): Promise<ReadAnswer<SearchPage>> {
    const query = new URLSearchParams({ context: 'catalog', ...parameters });

    lastSequence += 1;

    return read(`/data/products/search?${query.toString()}`, (body) => ({ rows: rowsOf(body.rows), hasMore: body.hasMore === true }), { 'X-Search-Sequence': String(lastSequence) });
}

/** What RC-30 expects, as RD-05 showed it: the product the token was on, and whether it was active or retired as a misassignment. */
export interface TokenMappingState {
    readonly productId: number;
    readonly productName: string;
    readonly state: 'ACTIVE' | 'RETIRED_MISASSIGNMENT';
}

/** The answer of RD-05 (App\DTO\Catalog\TokenMappingData); `kind` is null for a token that was never mapped. */
export interface TokenLookup {
    readonly token: string;
    readonly kind: 'MANUFACTURER' | 'INTERNAL' | null;
    readonly activeProduct: { readonly id: number; readonly name: string } | null;
    readonly mapping: TokenMappingState | null;
    readonly correctable: boolean;
}

/** RD-05 for a scanned or typed token (INTERACTION_CONTRACTS section 3.5). */
export function lookUpToken(token: string): Promise<ReadAnswer<TokenLookup>> {
    return read(`/data/barcode-tokens/lookup?${new URLSearchParams({ token }).toString()}`, (body) => body as unknown as TokenLookup);
}

/** The filters of a search as RD-01 takes them. */
export function searchParameters(filters: ProductFilters): Record<string, string> {
    const parameters: Record<string, string> = {};

    if (filters.q !== '') {
        parameters.q = filters.q;
    }

    if (filters.category !== null) {
        parameters.category = String(filters.category);
    }

    if (filters.brand !== '') {
        parameters.brand = filters.brand;
    }

    if (filters.status !== null) {
        parameters.status = filters.status;
    }

    if (filters.import !== undefined && filters.import !== null) {
        parameters.import = String(filters.import);
    }

    return parameters;
}

/** The most results of one read of RD-01 (INTERACTION_CONTRACTS section 3.1). */
const MAX_PER_PAGE = 100;

const PER_PAGE = 25;

/**
 * The results of the first `pages` pages of a search: the list a user returns to after
 * "Muat lebih banyak" (NC-10), read in as few requests as RD-01's largest page allows.
 */
export async function searchPages(filters: ProductFilters): Promise<ReadAnswer<SearchPage>> {
    const parameters = searchParameters(filters);

    if (filters.page <= 1) {
        return searchProducts(parameters);
    }

    const wanted = filters.page * PER_PAGE;
    const rows: ProductRow[] = [];
    let chunk = 1;
    let more = true;

    while (more && rows.length < wanted) {
        const answer = await searchProducts({ ...parameters, page: String(chunk), perPage: String(MAX_PER_PAGE) });

        if (answer.kind !== 'read') {
            return answer;
        }

        rows.push(...answer.body.rows);
        more = answer.body.hasMore;
        chunk += 1;
    }

    return { kind: 'read', body: { rows: rows.slice(0, wanted), hasMore: more || rows.length > wanted } };
}

/** What RD-06 finds (INTERACTION_CONTRACTS section 3.5). */
export interface CheckResult {
    readonly barcode: ProductRow | null;
    readonly sku: ProductRow | null;
    readonly similarNames: readonly ProductRow[];
}

function productOf(value: unknown): ProductRow | null {
    return isRecord(value) ? (value as unknown as ProductRow) : null;
}

/** RD-06 for the texts given; an empty text is not asked about. */
export function checkProduct(texts: { readonly barcode?: string; readonly sku?: string; readonly name?: string }): Promise<ReadAnswer<CheckResult>> {
    const query = new URLSearchParams();

    for (const [name, value] of Object.entries(texts)) {
        if (value !== undefined && value.trim() !== '') {
            query.set(name, value.trim());
        }
    }

    return read(`/data/products/check?${query.toString()}`, (body) => ({ barcode: productOf(body.barcode), sku: productOf(body.sku), similarNames: rowsOf(body.similarNames) }));
}

interface FieldEntry {
    readonly key: string;
    readonly params: Readonly<Record<string, unknown>>;
}

function firstEntry(entries: unknown): FieldEntry | null {
    const first: unknown = Array.isArray(entries) ? entries[0] : null;

    if (!isRecord(first) || typeof first.key !== 'string') {
        return null;
    }

    return { key: first.key, params: isRecord(first.params) ? first.params : {} };
}

function textOf(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

/**
 * The message of a field's first refusal (COPY_DECK sections 12, 13.2 and 16.1), or none
 * for a key these forms do not show. A duplicate's key is the parent of its texts, and a
 * barcode's reason is named whole (TASK-016 finding F-1). A refused category names the
 * one the form holds.
 */
export function catalogFieldMessage(entries: unknown, categoryName = ''): string | undefined {
    const entry = firstEntry(entries);

    switch (entry?.key) {
        case 'ux.validation.required':
            return t('ux.validation.required');
        case 'ux.validation.text_max':
            return t('ux.validation.text_max', { maks: textOf(entry.params.maks) });
        case 'ux.validation.choose_one':
            return t('ux.validation.choose_one');
        case 'ux.validation.sku':
            return t('ux.validation.sku');
        case 'ux.validation.barcode_token':
            return t('ux.validation.barcode_token');
        case 'ux.validation.money':
            return t('ux.validation.money');
        case 'ux.validation.money_min':
            return t('ux.validation.money_min');
        case 'ux.validation.money_max':
            return t('ux.validation.money_max');
        case 'ux.validation.min_stock':
            return t('ux.validation.min_stock');
        case 'ux.validation.quantity_whole':
            return t('ux.validation.quantity_whole');
        case 'ux.validation.quantity_max':
            return t('ux.validation.quantity_max');
        case 'ux.validation.quantity_decimals':
            return t('ux.validation.quantity_decimals', { desimal: textOf(entry.params.desimal) });
        case 'ux.state.duplicate_code.sku':
            return t('ux.state.duplicate_code.sku.body');
        case 'ux.state.duplicate_code.barcode':
            return t('ux.state.duplicate_code.barcode.body');
        case 'ux.state.barcode_ineligible.retired':
            return t('ux.state.barcode_ineligible.retired');
        case 'ux.state.barcode_ineligible.reserved':
            return t('ux.state.barcode_ineligible.reserved');
        case 'ux.state.barcode_ineligible.check_digit':
            return t('ux.state.barcode_ineligible.check_digit');
        case 'ux.state.barcode_ineligible.not_correctable':
            return t('ux.state.barcode_ineligible.not_correctable');
        case 'ux.state.inactive.category':
            return t('ux.state.inactive.category.body', { kategori: categoryName });
        case 'ux.validation.quantity_positive':
            return t('ux.validation.quantity_positive');
        case 'ux.validation.upload.image_type':
            return t('ux.validation.upload.image_type');
        case 'ux.validation.upload.too_large':
            return t('ux.validation.upload.too_large', { ukuran: textOf(entry.params.ukuran) });
        case 'ux.validation.upload.too_many_pixels':
            return t('ux.validation.upload.too_many_pixels', { jumlah: textOf(entry.params.jumlah) });
        case 'ux.validation.upload.animated':
            return t('ux.validation.upload.animated');
        case 'ux.validation.upload.unreadable':
            return t('ux.validation.upload.unreadable');
        default:
            return undefined;
    }
}
