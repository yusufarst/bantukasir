import { retrySeconds } from '@/lib/answers';
import { leaveSignedInSession } from '@/lib/browserSession';
import { requestJson } from '@/lib/commands/client';
import { t } from '@/lib/i18n';
import type { AreaId, DestinationId, SubDestinationId } from '@/lib/navigation';

// The CSV product import on the client (ROUTE_CONTRACTS RP-308, RR-01, RR-02;
// INTERACTION_CONTRACTS section 6 and RC-31; DOCUMENT_CONTRACTS DC-04; COPY_DECK sections
// 8.9 and 16.3; TASK-019 scope item 5): the requests that are not commands, what they
// answer, and the words of each error, which the server names by its key.

export const IMPORT_SHELL = { current: 'products', area: 'products', subCurrent: 'csv' } as const satisfies { current: DestinationId; area: AreaId; subCurrent: SubDestinationId };

/** The largest file (SECURITY_MODEL section 13), and how the refusal names it (COPY_DECK section 16.2). */
export const CSV_MAX_BYTES = 2 * 1024 * 1024;

export const CSV_SIZE_LABEL = '2 MB';

/** The key of the error that UXI-209's link answers. */
const CATEGORY_MISSING = 'catalog.csv_import.error.category_missing';

/** A column of DC-04 as RP-308 gives it. */
export interface ImportColumn {
    readonly header: string;
    readonly required: boolean;
}

/** An error of the file or of a row: its message key and its safe parameters. */
export interface ImportError {
    readonly key: string;
    readonly params: Readonly<Record<string, unknown>>;
}

/** A row of the preview of RR-01. */
export interface PreviewRow {
    readonly row: number;
    readonly sku: string;
    readonly name: string;
    readonly category: string;
    readonly sellingPrice: string;
    readonly errors: readonly ImportError[];
}

/** The answer of RR-01 for a file that can be previewed. */
export interface ImportPreview {
    readonly importId: string;
    readonly fileSha256: string;
    readonly dataRowCount: number;
    readonly readyRows: number;
    readonly errorRows: number;
    readonly rows: readonly PreviewRow[];
}

export type PreviewAnswer =
    | { readonly kind: 'preview'; readonly preview: ImportPreview }
    /** CSV_INVALID, with the errors of the whole file; or a file that the limits above the application refused. */
    | { readonly kind: 'refused'; readonly errors: readonly ImportError[] }
    | { readonly kind: 'throttled'; readonly seconds: bigint }
    | { readonly kind: 'reload' }
    | { readonly kind: 'forbidden' }
    | { readonly kind: 'failed' };

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** The entries of a field or a row as the server sent them; anything else is none. */
export function importErrors(entries: unknown): readonly ImportError[] {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries.flatMap((entry: unknown) => (isRecord(entry) && typeof entry.key === 'string' ? [{ key: entry.key, params: isRecord(entry.params) ? entry.params : {} }] : []));
}

/** The answer of RR-01 as the page reads it. */
export function previewAnswerOf(arrived: { readonly status: number; readonly body: unknown; readonly retryAfter: string | null } | null): PreviewAnswer {
    if (arrived === null) {
        return { kind: 'failed' };
    }

    if (arrived.status === 200 && isRecord(arrived.body)) {
        return { kind: 'preview', preview: arrived.body as unknown as ImportPreview };
    }

    if (arrived.status === 413) {
        return { kind: 'refused', errors: [{ key: 'ux.validation.upload.too_large', params: { ukuran: CSV_SIZE_LABEL } }] };
    }

    if (arrived.status === 422 && isRecord(arrived.body) && isRecord(arrived.body.fields)) {
        const errors = importErrors(arrived.body.fields.file);

        return errors.length > 0 ? { kind: 'refused', errors } : { kind: 'failed' };
    }

    if (arrived.status === 429) {
        return { kind: 'throttled', seconds: retrySeconds(arrived.retryAfter) };
    }

    if (arrived.status === 419) {
        return { kind: 'reload' };
    }

    return arrived.status === 403 ? { kind: 'forbidden' } : { kind: 'failed' };
}

/** RR-01: the file, as multipart; its name and type mean nothing to the server (SEC-UP-01). */
export async function previewImport(file: Blob): Promise<PreviewAnswer> {
    const form = new FormData();

    form.append('file', file);
    const arrived = await requestJson('POST', '/products/import/preview', form);

    if (arrived?.status === 401) {
        leaveSignedInSession();
    }

    return previewAnswerOf(arrived);
}

/** RR-02: removes the temporary file; a failure leaves it for housekeeping (ARC-43). */
export async function cancelImport(importId: string): Promise<void> {
    const arrived = await requestJson('POST', `/products/import/${importId}/cancel`, undefined, { waitMs: 30_000 });

    if (arrived?.status === 401) {
        leaveSignedInSession();
    }
}

function textOf(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

/** The words of an error of the file or of a row (COPY_DECK sections 16.2 and 16.3), or none for a key that this page does not show. */
export function importErrorText(error: ImportError): string | undefined {
    const column = textOf(error.params.kolom);

    switch (error.key) {
        case 'ux.validation.upload.csv_type':
            return t('ux.validation.upload.csv_type');
        case 'ux.validation.upload.csv_rows':
            return t('ux.validation.upload.csv_rows');
        case 'ux.validation.upload.too_large':
            return t('ux.validation.upload.too_large', { ukuran: textOf(error.params.ukuran) });
        case 'ux.validation.upload.unreadable':
            return t('ux.validation.upload.unreadable');
        case 'catalog.csv_import.error.missing_column':
            return t('catalog.csv_import.error.missing_column', { kolom: column });
        case 'catalog.csv_import.error.unknown_column':
            return t('catalog.csv_import.error.unknown_column', { kolom: column });
        case 'catalog.csv_import.error.required':
            return t('catalog.csv_import.error.required', { kolom: column });
        case 'catalog.csv_import.error.invalid_value':
            return t('catalog.csv_import.error.invalid_value', { kolom: column });
        case 'catalog.csv_import.error.formula':
            return t('catalog.csv_import.error.formula');
        case 'catalog.csv_import.error.category_missing':
            return t('catalog.csv_import.error.category_missing');
        case 'catalog.csv_import.error.category_ambiguous':
            return t('catalog.csv_import.error.category_ambiguous');
        case 'catalog.csv_import.error.sku_taken':
            return t('catalog.csv_import.error.sku_taken');
        case 'catalog.csv_import.error.sku_duplicate':
            return t('catalog.csv_import.error.sku_duplicate');
        case 'catalog.csv_import.error.barcode_taken':
            return t('catalog.csv_import.error.barcode_taken');
        case 'catalog.csv_import.error.barcode_duplicate':
            return t('catalog.csv_import.error.barcode_duplicate');
        case 'catalog.csv_import.error.barcode_reserved':
            return t('catalog.csv_import.error.barcode_reserved');
        case 'catalog.csv_import.error.file_changed':
            return t('catalog.csv_import.error.file_changed');
        default:
            return undefined;
    }
}

/** A row's error with its row number, the format of every row error (COPY_DECK section 16.3). */
export function rowErrorText(row: number, error: ImportError): string {
    return t('catalog.csv_import.error.row', { nomor: String(row), masalah: importErrorText(error) ?? '' });
}

/** The row errors of a refusal of RC-31 (INTERACTION_CONTRACTS section 1.6), in the order of the file. */
export function rowsOfRefusal(lines: Readonly<Record<string, unknown>>): readonly { readonly row: number; readonly errors: readonly ImportError[] }[] {
    return Object.entries(lines)
        .map(([row, line]) => ({ row: Number(row), errors: importErrors(isRecord(line) ? line.errors : null) }))
        .filter((line) => Number.isSafeInteger(line.row) && line.errors.length > 0)
        .sort((first, second) => first.row - second.row);
}

/** The rows with errors first (NAVIGATION_CONTRACTS UXP-308), each part in the order of the file. */
export function errorsFirst(rows: readonly PreviewRow[]): readonly PreviewRow[] {
    return [...rows.filter((row) => row.errors.length > 0), ...rows.filter((row) => row.errors.length === 0)];
}

/** Whether a row names a category that no active category matches, which UXI-209 answers. */
export function namesMissingCategory(errors: readonly ImportError[]): boolean {
    return errors.some((error) => error.key === CATEGORY_MISSING);
}
