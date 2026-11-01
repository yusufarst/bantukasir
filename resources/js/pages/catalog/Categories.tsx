import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { FilterChip, FilterChips } from '@/components/ui/Choices';
import { KeyValueList } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Dialog } from '@/components/ui/Dialog';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import type { StateId } from '@/components/ui/stateCatalogue';
import { Committed, EmptyState, NoResults, StateMark, StateNotice } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { ListLayout, PageHeader } from '@/layouts/Page';
import { blockedReason, UnresolvedReason } from '@/lib/catalog/Parts';
import { CATALOG_COMMANDS, CATEGORIES_PAGE, CATEGORIES_SHELL, catalogFieldMessage, type Category } from '@/lib/catalog/products';
import { committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-307 "Kategori" (ROUTE_CONTRACTS RP-307, RC-21 to RC-24; COPY_DECK section 8.8;
// INTERACTION_INVENTORY UXI-202 to UXI-205; NAVIGATION_CONTRACTS UXP-307; BR-CAT-09;
// TASK-017 scope item 1): every category with its status, by the filter `status`, which
// stands in the page's address. "Tambah kategori" and a row's "Ubah nama" open an inline
// form, whose "Batal" sends nothing and whose "Simpan" Enter never activates (UXA-10);
// a row's "Nonaktifkan" or "Aktifkan kembali" asks in a dialog. A command updates the
// list in place with its message; a version changed meanwhile shows UXS-17 at the row,
// and the list shows the current values. A category is never deleted (D-042).

const STALE_STATE: StateId = 'UXS-17';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

const STALE_VERSION = 'STALE_VERSION';

const CATEGORIES_PROP = 'categories';

const STATUS_FILTERS: readonly { readonly value: string; readonly label: TranslationKey }[] = [
    { value: 'active', label: 'catalog.status.active' },
    { value: 'inactive', label: 'catalog.status.inactive' },
];

/** The one form or dialog open on the page; each opening is a new intent. */
type Action = { readonly kind: 'add' } | { readonly kind: 'rename'; readonly category: Category } | { readonly kind: 'lifecycle'; readonly category: Category };

export default function Categories({ categories, filters }: { categories: readonly Category[]; filters: { readonly status: string | null } }) {
    const title = t('catalog.categories.title');

    return (
        <AppShell title={title} {...CATEGORIES_SHELL}>
            <CategoriesList title={title} categories={categories} status={filters.status} />
        </AppShell>
    );
}

function CategoriesList({ title, categories, status }: { title: string; categories: readonly Category[]; status: string | null }) {
    const [action, setAction] = useState<Action | null>(null);
    const [opening, setOpening] = useState(0);
    const [note, setNote] = useState<string | null>(null);
    // The category whose deactivation or reactivation found it changed (UXS-17 at the row).
    const [staleRow, setStaleRow] = useState<number | null>(null);
    const unresolved = useUnresolved();

    const open = (next: Action): void => {
        setNote(null);
        setStaleRow(null);
        setOpening((current) => current + 1);
        setAction(next);
    };

    const reload = (): void => router.reload({ only: [CATEGORIES_PROP] });

    const done = (text: string): void => {
        setAction(null);
        setNote(text);
        reload();
    };

    const onStale = (category: Category): void => {
        setAction(null);
        setStaleRow(category.id);
        reload();
    };

    const visit = (next: string | null): void => {
        router.get(CATEGORIES_PAGE, next === null ? {} : { status: next }, { preserveScroll: true });
    };

    const addReason = blockedReason(unresolved.union, CATALOG_COMMANDS.createCategory);
    const openAdd = (): void => open({ kind: 'add' });
    const openRename = (category: Category): void => open({ kind: 'rename', category });

    const lifecycleEntries = (category: Category): MenuEntry[] => [
        { kind: 'action', label: category.active ? t('ux.action.deactivate') : t('ux.action.reactivate'), danger: category.active, onSelect: () => open({ kind: 'lifecycle', category }) },
    ];

    const columns: readonly Column<Category>[] = [
        {
            id: 'name',
            header: t('catalog.categories.name'),
            isHead: true,
            cell: (category) =>
                action?.kind === 'rename' && action.category.id === category.id ? (
                    <RenameRun key={opening} category={category} onClose={() => setAction(null)} onDone={done} onChanged={reload} />
                ) : (
                    <>
                        {category.name}
                        {staleRow === category.id && <StateNotice state={STALE_STATE} />}
                    </>
                ),
        },
        { id: 'status', header: t('catalog.field.status'), cell: (category) => (category.active ? <StatusWord tone={'success'} label={t('catalog.status.active')} /> : <StateMark kind={'inactive'} />) },
        {
            id: 'actions',
            header: t('ux.action.more_actions'),
            isAction: true,
            cell: (category) => {
                const reason = blockedReason(unresolved.union, CATALOG_COMMANDS.renameCategory);

                return (
                    <div className={'bk-actions'}>
                        <Button label={t('catalog.categories.rename')} accessibleName={t('catalog.categories.rename_named', { kategori: category.name })} variant={'secondary'} enterActivates={false} disabledReason={reason} onClick={() => openRename(category)} />
                        {reason === undefined && <Menu label={t('ux.action.more_actions')} rowName={category.name} items={lifecycleEntries(category)} />}
                    </div>
                );
            },
        },
    ];

    return (
        <ListLayout
            header={<PageHeader title={title} actions={<Button label={t('catalog.categories.add')} enterActivates={false} disabledReason={addReason} onClick={openAdd} />} />}
            toolbar={
                <FilterChips label={t('catalog.field.status')}>
                    {STATUS_FILTERS.map((filter) => (
                        <FilterChip key={filter.value} label={t(filter.label)} pressed={status === filter.value} onPressedChange={(pressed) => visit(pressed ? filter.value : null)} />
                    ))}
                </FilterChips>
            }
        >
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {action?.kind === 'add' && <AddRun key={opening} onClose={() => setAction(null)} onDone={done} />}
                {categories.length > 0 && <DataTable caption={title} columns={columns} rows={categories} rowKey={(category) => String(category.id)} />}
                {categories.length === 0 && status !== null && <NoResults onClear={() => visit(null)} />}
                {categories.length === 0 && status === null && <EmptyState body={t('catalog.categories.empty')} />}
                {action?.kind === 'lifecycle' && <LifecycleRun key={opening} category={action.category} onClose={() => setAction(null)} onDone={done} onStale={() => onStale(action.category)} />}
            </div>
        </ListLayout>
    );
}

/** The inline form of a name (UXI-203, UXI-204): "Simpan" sends it, "Batal" closes it and sends nothing. */
function NameForm({ initial, busy, blocked, error, onSave, onClose }: { initial: string; busy: boolean; blocked: boolean; error: string | undefined; onSave: (name: string) => void; onClose: () => void }) {
    const [name, setName] = useState(initial);
    const field = useRef<HTMLInputElement | null>(null);
    const empty = name.trim() === '';

    // The form opens where the user acts (UXA-08).
    useEffect(() => {
        field.current?.focus();
    }, []);

    return (
        <CommandForm>
            <Field label={t('catalog.categories.name')} required={true} error={error}>
                <TextInput ref={field} type={'text'} autoComplete={'off'} maxLength={100} value={name} onChange={(event) => setName(event.target.value)} />
            </Field>
            <div className={'bk-actions'}>
                <Button label={t('ux.action.save')} enterActivates={false} busy={busy} disabledReason={empty ? t('ux.disabled.form_incomplete') : blocked && !busy ? <UnresolvedReason /> : undefined} onClick={() => onSave(name.trim())} />
                <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} onClick={onClose} />
            </div>
        </CommandForm>
    );
}

function AddRun({ onClose, onDone }: { onClose: () => void; onDone: (text: string) => void }) {
    const control = useCommand(CATALOG_COMMANDS.createCategory);
    const { phase } = control;
    const [sent, setSent] = useState('');
    const committed = phase.kind === 'committed';

    useEffect(() => {
        if (committed) {
            onDone(control.ownRequest ? t('catalog.categories.added', { kategori: sent }) : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;

    return (
        <Card>
            <div className={'bk-stack'}>
                <NameForm
                    initial={''}
                    busy={phase.kind === 'submitting'}
                    blocked={control.blockedBy !== null}
                    error={catalogFieldMessage(refusal?.fields['payload.name'])}
                    onSave={(name) => {
                        setSent(name);
                        control.submit({ name });
                    }}
                    onClose={onClose}
                />
                {refusal?.code !== VALIDATION_FAILED && !committed && <CommandPanels control={control} />}
            </div>
        </Card>
    );
}

/** "Ubah nama" at its row: a version changed meanwhile says so there, the typed name stays, and the next save is expected against the row as read again (UXS-17). */
function RenameRun({ category, onClose, onDone, onChanged }: { category: Category; onClose: () => void; onDone: (text: string) => void; onChanged: () => void }) {
    const control = useCommand(CATALOG_COMMANDS.renameCategory);
    const { phase } = control;
    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const stale = refusal?.code === STALE_VERSION;
    const committed = phase.kind === 'committed';

    useEffect(() => {
        if (stale) {
            onChanged();
        }
    }, [stale]);

    useEffect(() => {
        if (committed) {
            onDone(control.ownRequest ? t('catalog.categories.renamed') : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    return (
        <div className={'bk-stack'}>
            {stale && (
                <>
                    <StateNotice state={STALE_STATE} />
                    <KeyValueList items={[{ key: String(category.id), label: t('catalog.categories.name'), value: category.name }]} />
                </>
            )}
            <NameForm
                initial={category.name}
                busy={phase.kind === 'submitting'}
                blocked={control.blockedBy !== null}
                error={catalogFieldMessage(refusal?.fields['payload.name'])}
                onSave={(name) => control.submit({ categoryId: category.id, name }, { categoryVersion: category.version })}
                onClose={onClose}
            />
            {refusal?.code !== VALIDATION_FAILED && !stale && !committed && <CommandPanels control={control} />}
        </div>
    );
}

/** "Nonaktifkan" or "Aktifkan kembali" of a row, in its dialog (UXI-205; BR-CAT-09). */
function LifecycleRun({ category, onClose, onDone, onStale }: { category: Category; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const deactivating = category.active;
    const control = useCommand(deactivating ? CATALOG_COMMANDS.deactivateCategory : CATALOG_COMMANDS.reactivateCategory);
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
            onDone(!control.ownRequest ? committedText(control.target?.operation ?? '') : deactivating ? t('catalog.category.deactivated') : t('catalog.category.reactivated'));
        }
    }, [committed]);

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={deactivating ? t('catalog.category.deactivate_confirm.title', { kategori: category.name }) : t('catalog.category.reactivate_confirm.title', { kategori: category.name })}
                description={<p>{deactivating ? t('catalog.category.deactivate_confirm.body') : t('catalog.category.reactivate_confirm.body')}</p>}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button
                            label={deactivating ? t('ux.action.deactivate') : t('ux.action.reactivate')}
                            variant={deactivating ? 'danger' : 'primary'}
                            enterActivates={false}
                            busy={busy}
                            onClick={() => control.submit({ categoryId: category.id }, { categoryVersion: category.version })}
                        />
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
