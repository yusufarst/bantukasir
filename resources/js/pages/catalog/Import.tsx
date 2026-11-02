import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState, type ChangeEvent, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { LoadMore } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { UnsavedDialog } from '@/components/ui/Dialog';
import { CommandForm } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StateNotice } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { leaveSavedNote } from '@/lib/access/users';
import { throttledBody } from '@/lib/authAnswer';
import {
    cancelImport,
    CSV_MAX_BYTES,
    CSV_SIZE_LABEL,
    errorsFirst,
    IMPORT_SHELL,
    importErrors,
    importErrorText,
    namesMissingCategory,
    previewImport,
    rowErrorKey,
    rowErrorText,
    rowsOfRefusal,
    type ImportColumn,
    type ImportError,
    type ImportPreview,
    type PreviewRow,
} from '@/lib/catalog/csvImport';
import { CATALOG_COMMANDS, CATEGORIES_PAGE } from '@/lib/catalog/products';
import type { Refusal } from '@/lib/commands/answers';
import { committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { formatCount } from '@/lib/format';
import { t } from '@/lib/i18n';

// UXP-308 "Impor produk (CSV)" (ROUTE_CONTRACTS RP-308, RR-01, RR-02, RC-31; COPY_DECK section
// 8.9; INTERACTION_INVENTORY UXI-206 to UXI-212; NAVIGATION_CONTRACTS UXP-308, NC-03; UI_STATES
// section 6; WF-18; BR-CAT-37 to BR-CAT-43; TASK-019 scope item 5). The column list of DC-04;
// a file chosen is previewed at once by RR-01, a request and not a command, and a file above
// the limit is refused here with no request. The preview is the review: every row with its
// errors, the rows with errors first, and "Impor :jumlah produk" only for a file without an
// error, which Enter never activates (UXA-10). RC-31 takes the file's identifier and checksum;
// success leads to UXP-301 filtered to the new products, with the summary message. A refusal
// of RC-31 ended the file on the server, so the page shows why and offers a new file.
// "Batalkan impor" asks first (UXS-45) and removes the temporary file by RR-02 (NC-03). While
// this user holds an unresolved import request the page opens on its panel, and no file can be
// chosen (NC-13). Leaving with a preview open asks first; its file stays for housekeeping.

const INVALID_STATE: StateId = 'UXS-39';

const SKU_STATE: StateId = 'UXS-37-sku';

const BARCODE_STATE: StateId = 'UXS-37-barcode';

const CATEGORY_STATE: StateId = 'UXS-32-category';

const THROTTLED_STATE: StateId = 'UXS-12';

const RELOAD_STATE: StateId = 'UXS-10';

const FORBIDDEN_STATE: StateId = 'UXS-07';

const LOST_STATE: StateId = 'UXS-20';

const CSV_INVALID = 'CSV_INVALID';

const DUPLICATE_SKU = 'DUPLICATE_SKU';

const DUPLICATE_BARCODE = 'DUPLICATE_BARCODE';

const CATEGORY_INACTIVE = 'CATEGORY_INACTIVE';

/** The refusals of RC-31 that end the file on the server (ARCHITECTURE ARC-43). */
const FINAL_REFUSALS: readonly string[] = [CSV_INVALID, DUPLICATE_SKU, DUPLICATE_BARCODE, CATEGORY_INACTIVE];

/** The file of a CSV export of a spreadsheet; the server judges the content (SEC-UP-01). */
const CSV_TYPES = '.csv,text/csv';

/** The rows that the table adds at a time: a file may hold 5,000. */
const ROWS_SHOWN = 100;

const UNRESOLVED_PAGE = '/requests';

/** What a choice of file answered, other than a preview. */
type FileProblem =
    | { readonly kind: 'refused'; readonly errors: readonly ImportError[] }
    | { readonly kind: 'throttled'; readonly seconds: bigint }
    | { readonly kind: 'reload' }
    | { readonly kind: 'forbidden' }
    | { readonly kind: 'failed' };

export default function Import({ columns, canManageCategories }: { columns: readonly ImportColumn[]; canManageCategories: boolean }) {
    const title = t('catalog.csv_import.title');

    return (
        <AppShell title={title} {...IMPORT_SHELL}>
            <ImportFlow title={title} columns={columns} canManageCategories={canManageCategories} />
        </AppShell>
    );
}

/** What RC-31's refusal says, once its file is gone (UXS-39, UXS-37, UXS-32). */
function RefusedNotice({ refusal, footer }: { refusal: Refusal; footer: ReactNode }) {
    const rows = rowsOfRefusal(refusal.lines);
    const fileErrors = Object.values(refusal.fields).flatMap((entries) => importErrors(entries));
    const list =
        rows.length === 0 ? null : (
            <ul>
                {rows.flatMap(({ row, errors }) => errors.map((error, index) => <li key={rowErrorKey(row, index)}>{rowErrorText(row, error)}</li>))}
            </ul>
        );
    const firstFileError = fileErrors[0];

    switch (refusal.code) {
        case DUPLICATE_SKU:
            return (
                <StateNotice state={SKU_STATE} actions={footer}>
                    {list}
                </StateNotice>
            );
        case DUPLICATE_BARCODE:
            return (
                <StateNotice state={BARCODE_STATE} actions={footer}>
                    {list}
                </StateNotice>
            );
        case CATEGORY_INACTIVE:
            return (
                <StateNotice state={CATEGORY_STATE} body={t('ux.state.inactive.category.body', { kategori: typeof refusal.params.kategori === 'string' ? refusal.params.kategori : '' })} actions={footer}>
                    {list}
                </StateNotice>
            );
        default:
            return (
                <StateNotice state={INVALID_STATE} body={firstFileError === undefined ? t('ux.state.csv_invalid.body', { jumlah: formatCount(rows.length) }) : importErrorText(firstFileError)} actions={footer}>
                    {list}
                </StateNotice>
            );
    }
}

/** The state of a choice of file that is not a refusal of the file itself. */
function ChoiceNotice({ problem }: { problem: FileProblem }) {
    switch (problem.kind) {
        case 'throttled':
            return <StateNotice state={THROTTLED_STATE} body={throttledBody(problem.seconds)} />;
        case 'reload':
            return <StateNotice state={RELOAD_STATE} actions={<Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />} />;
        case 'forbidden':
            return <StateNotice state={FORBIDDEN_STATE} />;
        case 'failed':
            return <StateNotice state={LOST_STATE} />;
        case 'refused':
            return null;
    }
}

function ImportFlow({ title, columns, canManageCategories }: { title: string; columns: readonly ImportColumn[]; canManageCategories: boolean }) {
    const control = useCommand(CATALOG_COMMANDS.confirmImport);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const [preview, setPreview] = useState<ImportPreview | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [problem, setProblem] = useState<FileProblem | null>(null);
    const [refused, setRefused] = useState<Refusal | null>(null);
    const [asking, setAsking] = useState(false);
    const [shown, setShown] = useState(ROWS_SHOWN);
    const previewHeading = useRef<HTMLDivElement | null>(null);
    const choiceArea = useRef<HTMLDivElement | null>(null);
    const ids = useId();
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const finalRefusal = refusal !== null && FINAL_REFUSALS.includes(refusal.code);
    const busy = phase.kind === 'submitting';
    const frozen = busy || phase.kind === 'uncertain' || phase.kind === 'held' || phase.kind === 'reload';
    const blocked = blockedBy !== null && !busy;
    const labelId = `${ids}-label`;
    const hintId = `${ids}-hint`;
    const errorId = `${ids}-error`;

    useUnsavedChanges(preview !== null && !committed, t('ux.state.unsaved.body_import'));

    useEffect(() => {
        if (location !== undefined) {
            leaveSavedNote(control.ownRequest && preview !== null ? t('catalog.csv_import.success', { jumlah: formatCount(preview.dataRowCount) }) : committedText(control.target?.operation ?? ''));
            router.visit(location);
        }
    }, [location]);

    // A refusal of the file ended it on the server: the page keeps the reasons and offers a new file.
    useEffect(() => {
        if (finalRefusal) {
            setRefused(refusal);
            setPreview(null);
            control.startAgain();
        }
    }, [finalRefusal]);

    // The chosen file's preview takes the place of the control that had the focus, and the
    // empty page takes its place after a cancel.
    useEffect(() => {
        if (preview !== null) {
            previewHeading.current?.focus();
        }
    }, [preview?.importId]);

    const choose = (event: ChangeEvent<HTMLInputElement>): void => {
        const file = event.target.files?.item(0) ?? null;

        // The same file may be chosen again after a refusal.
        event.target.value = '';

        if (file === null) {
            return;
        }

        setRefused(null);

        if (file.size > CSV_MAX_BYTES) {
            setProblem({ kind: 'refused', errors: [{ key: 'ux.validation.upload.too_large', params: { ukuran: CSV_SIZE_LABEL } }] });

            return;
        }

        setProblem(null);
        setPreviewing(true);
        void previewImport(file).then((answer) => {
            setPreviewing(false);

            if (answer.kind === 'preview') {
                setShown(ROWS_SHOWN);
                setPreview(answer.preview);
            } else {
                setProblem(answer);
            }
        });
    };

    const cancel = (): void => {
        if (preview !== null) {
            void cancelImport(preview.importId);
        }

        setAsking(false);
        setPreview(null);
        setProblem(null);
        control.startAgain();
        window.requestAnimationFrame(() => choiceArea.current?.focus());
    };

    const confirm = (): void => {
        if (preview !== null) {
            control.submit({ importId: preview.importId, fileSha256: preview.fileSha256 });
        }
    };

    const fileErrors = problem?.kind === 'refused' ? problem.errors : [];
    const rows = preview === null ? [] : errorsFirst(preview.rows);
    const hasErrors = preview !== null && preview.errorRows > 0;
    const previewErrors = preview === null ? [] : preview.rows.flatMap((row) => row.errors);
    const refusedErrors = refused === null ? [] : rowsOfRefusal(refused.lines).flatMap((line) => line.errors);
    const categoryLink =
        canManageCategories && (namesMissingCategory(previewErrors) || namesMissingCategory(refusedErrors)) ? <ButtonLink href={CATEGORIES_PAGE} label={t('catalog.csv_import.create_category_link')} variant={'secondary'} onClick={guard} /> : undefined;
    const confirmReason = preview === null ? undefined : hasErrors ? t('catalog.csv_import.disabled_errors') : preview.dataRowCount === 0 ? t('ux.disabled.form_incomplete') : undefined;

    const previewColumns: readonly Column<PreviewRow>[] = [
        ...(hasErrors
            ? [
                  {
                      id: 'errors',
                      header: t('catalog.field.status'),
                      cell: (row: PreviewRow) =>
                          row.errors.length === 0 ? null : (
                              <ul className={'bk-error'}>
                                  {row.errors.map((error, index) => (
                                      <li key={rowErrorKey(row.row, index)}>{rowErrorText(row.row, error)}</li>
                                  ))}
                              </ul>
                          ),
                  },
              ]
            : []),
        { id: 'name', header: t('catalog.csv_import.column.name'), isHead: true, cell: (row) => row.name },
        { id: 'sku', header: t('catalog.csv_import.column.sku'), cell: (row) => row.sku },
        { id: 'category', header: t('catalog.csv_import.column.category'), cell: (row) => row.category },
        { id: 'price', header: t('catalog.csv_import.column.selling_price'), cell: (row) => row.sellingPrice },
    ];

    return (
        <CommandLayout header={<PageHeader title={title} />}>
            <Section title={t('catalog.csv_import.format_title')}>
                <div className={'bk-stack'}>
                    <p>{t('catalog.csv_import.format_intro')}</p>
                    <ul>
                        {columns.map((column) => (
                            <li key={column.header}>{column.required ? column.header : `${column.header} ${t('ux.action.optional')}`}</li>
                        ))}
                    </ul>
                    <p>{t('catalog.csv_import.scope_note')}</p>
                    <p>{t('catalog.csv_import.limits')}</p>
                </div>
            </Section>
            {refused !== null && <RefusedNotice refusal={refused} footer={categoryLink} />}
            {preview === null ? (
                <Card>
                    <div className={'bk-stack'} ref={choiceArea} tabIndex={-1}>
                        {problem !== null && <ChoiceNotice problem={problem} />}
                        {blocked ? (
                            <Button
                                label={t('catalog.csv_import.choose_file')}
                                size={'lg'}
                                enterActivates={false}
                                disabledReason={
                                    <>
                                        {t('catalog.csv_import.disabled_unresolved')} <a href={UNRESOLVED_PAGE}>{t('ux.action.open_unresolved')}</a>
                                    </>
                                }
                            />
                        ) : (
                            <CommandForm>
                                <div className={'bk-field'} data-invalid={fileErrors.length > 0}>
                                    <span className={'bk-file'}>
                                        <input
                                            type={'file'}
                                            accept={CSV_TYPES}
                                            disabled={previewing}
                                            aria-labelledby={labelId}
                                            aria-describedby={fileErrors.length === 0 ? hintId : `${errorId} ${hintId}`}
                                            aria-invalid={fileErrors.length === 0 ? undefined : true}
                                            onChange={choose}
                                        />
                                        <span className={'bk-btn bk-btn--primary'} id={labelId} aria-hidden={true}>
                                            {t('catalog.csv_import.choose_file')}
                                        </span>
                                    </span>
                                    {fileErrors.length > 0 && (
                                        <div className={'bk-error'} id={errorId}>
                                            <Icon name={'danger'} size={'sm'} />
                                            <ul>
                                                {fileErrors.map((error) => (
                                                    <li key={error.key}>{importErrorText(error)}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                    <p className={'bk-hint'} id={hintId}>
                                        {t('catalog.csv_import.empty')}
                                    </p>
                                </div>
                            </CommandForm>
                        )}
                    </div>
                </Card>
            ) : (
                <>
                    <Section title={t('catalog.csv_import.preview_title')}>
                        <div className={'bk-stack'} ref={previewHeading} tabIndex={-1}>
                            <p>{t('catalog.csv_import.preview_summary', { siap: formatCount(preview.readyRows), salah: formatCount(preview.errorRows) })}</p>
                            <p>{t('catalog.csv_import.all_or_nothing')}</p>
                            {hasErrors && <StateNotice state={INVALID_STATE} body={t('ux.state.csv_invalid.body', { jumlah: formatCount(preview.errorRows) })} actions={categoryLink} />}
                            {rows.length > 0 && <DataTable caption={t('catalog.csv_import.preview_title')} columns={previewColumns} rows={rows.slice(0, shown)} rowKey={(row) => String(row.row)} />}
                            {rows.length > shown && <LoadMore onLoad={() => setShown((current) => current + ROWS_SHOWN)} />}
                        </div>
                    </Section>
                    <CommandForm>
                        <div className={'bk-actions'}>
                            <Button label={t('catalog.csv_import.confirm', { jumlah: formatCount(preview.dataRowCount) })} size={'lg'} enterActivates={false} busy={busy} unavailable={frozen && !busy} disabledReason={confirmReason} onClick={confirm} />
                            {!frozen && <Button label={t('catalog.csv_import.cancel')} variant={'secondary'} enterActivates={false} onClick={() => setAsking(true)} />}
                        </div>
                    </CommandForm>
                    <UnsavedDialog open={asking} onStay={() => setAsking(false)} onLeave={cancel} body={t('ux.state.unsaved.body_import')} />
                </>
            )}
            {!finalRefusal && <CommandPanels control={control} {...(committed ? { committed: preview === null ? committedText(control.target?.operation ?? '') : t('catalog.csv_import.success', { jumlah: formatCount(preview.dataRowCount) }) } : {})} />}
        </CommandLayout>
    );
}
