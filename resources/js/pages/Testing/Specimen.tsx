import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AnnouncerProvider, announcements, useAnnounce } from '@/components/ui/Announcer';
import { Button } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { FilterChip, FilterChips, SegmentedControl, type SegmentedOption } from '@/components/ui/Choices';
import { Figure, Figures, KeyValueList, LinkRow, LinkRows, LoadMore, RefreshLine, type KeyValue, type ReadValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Drawer, ReviewDialog, UnsavedDialog } from '@/components/ui/Dialog';
import { CommandForm, Field, MoneyInput, TextInput } from '@/components/ui/Field';
import type { IconName } from '@/components/ui/icons';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import { PaperItem, PaperItems, PaperRow, PaperRule, PaperShop, PaperTitle, ReceiptPaper, type PaperSize } from '@/components/ui/ReceiptPaper';
import {
    Committed,
    CostIncomplete,
    DisabledReason,
    EmptyState,
    ErrorSummary,
    LoadFailed,
    Loading,
    NoResults,
    RecoveryPanel,
    StaleRead,
    StateMark,
    StateNotice,
    StillSubmitting,
    TenderLow,
    UnknownCode,
    type FieldError,
    type MarkKind,
    type RecoveryKind,
} from '@/components/ui/States';
import { Pill, StatusWord, type Tone } from '@/components/ui/Status';
import { Steps } from '@/components/ui/Steps';
import { CartLine, CartRegion, CartTotal, ProductResult, ProductResults, QuantityStepper, ScanField, TillWorkspace } from '@/components/ui/Till';
import { TrendChart, type TrendPoint } from '@/components/ui/TrendChart';
import { STATE_CATALOGUE, type StateId } from '@/components/ui/stateCatalogue';
import { applyAccent } from '@/lib/accent';
import { compareMoney, lineTotal, subtractMoney, sumMoney } from '@/lib/exact';
import { formatMoney, formatVariance } from '@/lib/format';
import { t } from '@/lib/i18n';
import { CommandLayout, HomeLayout, ListLayout, PageContainer, PageHeader, PreAuthLayout, ReportLayout, ResultLayout } from '@/layouts/Page';
import { AMOUNTS, EVENTS, FIXTURE, HISTORY, lineDetail, METHOD_OPTIONS, PAPER_OPTIONS, PRODUCTS, SPECIMEN_IDS, TOP, TREND } from './specimenFixtures';

// The component specimen (TASK-005 scope item 8), only in the local and
// testing environments: every component of DESIGN_SYSTEM section 8 and the
// state presentations, rendered with the texts of their keys and synthetic
// data (specimenFixtures.ts), for the browser tests of tests/Browser/.

type EventName = (typeof EVENTS)[keyof typeof EVENTS];
type Product = (typeof PRODUCTS)[number];
type HistoryRow = (typeof HISTORY)[number];
type Method = (typeof METHOD_OPTIONS)[number]['value'];

const TONES: readonly Tone[] = ['neutral', 'success', 'warning', 'pending', 'danger', 'info'];
const MARKS: readonly MarkKind[] = ['cost_unknown', 'owner_corrected', 'inactive', 'unverified'];
const RECOVERY: readonly RecoveryKind[] = ['uncertain', 'idempotency_conflict', 'epoch_stale'];
const ROW_ICONS: readonly IconName[] = ['boxes', 'tag'];
const ROW_TONES: readonly Tone[] = ['warning', 'pending'];
const NOTICE_STATES = (Object.keys(STATE_CATALOGUE) as StateId[]).filter((state) => STATE_CATALOGUE[state].presentation === 'notice');

const FIGURES: readonly { label: string; value: ReadValue; hint?: string; incomplete?: boolean }[] = [
    { label: FIXTURE.figures.sales, value: { status: 'loaded', text: formatMoney(AMOUNTS.netSales) }, hint: FIXTURE.figureHint },
    { label: FIXTURE.figures.count, value: { status: 'loading' } },
    { label: FIXTURE.figures.cash, value: { status: 'failed' } },
    { label: FIXTURE.figures.transfer, value: { status: 'stale', text: formatMoney(AMOUNTS.transfer) } },
    { label: FIXTURE.figures.cost, value: { status: 'unknown_cost' }, incomplete: true },
];

const METHODS: readonly SegmentedOption<Method>[] = METHOD_OPTIONS;
const PAPERS: readonly SegmentedOption<PaperSize>[] = PAPER_OPTIONS;

const TREND_POINTS: readonly TrendPoint[] = TREND.map(([date, value]) => ({ date, value, valueText: formatMoney(value) }));

const UNKNOWN_COST_VALUE: ReadValue = { status: 'unknown_cost' };
const LOADED_SALES: ReadValue = { status: 'loaded', text: formatMoney(AMOUNTS.netSales) };

function fieldErrors(): readonly FieldError[] {
    return [
        { fieldId: SPECIMEN_IDS.product, label: FIXTURE.productLabel, message: t('ux.validation.required') },
        { fieldId: SPECIMEN_IDS.quantity, label: FIXTURE.quantityLabel, message: t('ux.validation.quantity_positive') },
    ];
}

function useEvents(): [readonly EventName[], (event: EventName) => void] {
    const [events, setEvents] = useState<readonly EventName[]>([]);

    return [events, (event) => setEvents((list) => [...list, event])];
}

/** A row's overflow; its second item opens the review dialog, as a row action may (review finding R-15). */
function rowMenu(record: (event: EventName) => void, openReview: () => void): readonly MenuEntry[] {
    return [
        { kind: 'action', label: FIXTURE.menuItems[0], onSelect: () => record(EVENTS.menu) },
        { kind: 'action', label: t('ux.action.review'), onSelect: openReview },
    ];
}

function pageMenu(record: (event: EventName) => void): readonly MenuEntry[] {
    return [
        { kind: 'action', label: FIXTURE.menuItems[0], icon: 'tag', onSelect: () => record(EVENTS.menu) },
        { kind: 'link', label: FIXTURE.menuItems[1], icon: 'printer', href: TOP },
        { kind: 'separator' },
        { kind: 'action', label: FIXTURE.menuItems[2], danger: true, onSelect: () => record(EVENTS.menu) },
    ];
}

function productColumns(record: (event: EventName) => void, openReview: () => void): readonly Column<Product>[] {
    return [
        { id: 'product', header: FIXTURE.columns.product, isHead: true, cell: (row) => <span className={'bk-clamp-2'}>{row.name}</span> },
        { id: 'sku', header: FIXTURE.columns.sku, cell: (row) => <span className={'bk-mono'}>{row.sku}</span> },
        { id: 'stock', header: FIXTURE.columns.stock, numeric: true, cell: (row) => row.stock },
        { id: 'cost', header: FIXTURE.columns.cost, numeric: true, cell: (row) => (row.cost === null ? <StateMark kind={'cost_unknown'} /> : formatMoney(row.cost)) },
        { id: 'status', header: FIXTURE.columns.status, cell: (row) => <StatusWord tone={row.status} label={FIXTURE.tones[row.status]} /> },
        {
            id: 'actions',
            header: t('ux.action.more_actions'),
            isAction: true,
            cell: (row) => <Menu label={t('ux.action.more_actions')} rowName={row.name} icon={'more'} iconOnly={true} variant={'quiet'} items={rowMenu(record, openReview)} />,
        },
    ];
}

const HISTORY_COLUMNS: readonly Column<HistoryRow>[] = [
    { id: 'time', header: FIXTURE.columns.time, isHead: true, cell: (row) => row.time },
    { id: 'movement', header: FIXTURE.columns.movement, cell: (row) => row.movement },
    { id: 'reference', header: FIXTURE.columns.reference, cell: (row) => <span className={'bk-mono'}>{row.reference}</span> },
    { id: 'actor', header: FIXTURE.columns.actor, cell: (row) => row.actor },
    { id: 'stock', header: FIXTURE.columns.stock, numeric: true, cell: (row) => row.stock },
];

function cashSummary(expected: ReadValue | string): readonly KeyValue[] {
    return [
        { key: 'physical', label: FIXTURE.physical, value: formatMoney(AMOUNTS.physical) },
        { key: 'expected', label: FIXTURE.expected, value: typeof expected === 'string' ? formatMoney(expected) : expected },
        { key: 'variance', label: FIXTURE.variance, value: <StatusWord tone={'warning'} label={formatVariance(AMOUNTS.variance)} />, strong: true },
    ];
}

function SpecimenSection({ id, title, children }: { id: string; title: string; children: ReactNode }) {
    const titleId = `${id}-title`;
    const testId = `specimen-${id}`;

    return (
        <section className={'bk-stack'} aria-labelledby={titleId} data-testid={testId}>
            <h2 className={'bk-h2'} id={titleId} data-fixture={true}>
                {title}
            </h2>
            {children}
        </section>
    );
}

/** Two announcements close together, one per region (UXA-17). */
function AnnounceBoth() {
    const announce = useAnnounce();

    return (
        <Button
            label={t('ux.action.check_status')}
            variant={'secondary'}
            data-testid={'announce-both'}
            onClick={() => {
                announce(announcements.refused(FIXTURE.committed), true);
                announce(announcements.loaded());
            }}
        />
    );
}

/** The page archetypes of DESIGN_SYSTEM 7.3 in their containers, each with a small header of its own. */
function Archetypes() {
    const header = (name: string): ReactNode => <h3 className={'bk-h3'}>{name}</h3>;

    return (
        <div className={'bk-stack'} data-testid={'archetypes'}>
            <HomeLayout header={header(FIXTURE.archetypes.home)} panels={<LoadFailed onRetry={() => undefined} />} />
            <ListLayout header={header(FIXTURE.archetypes.list)} toolbar={<span data-fixture={true}>{FIXTURE.filter}</span>} refresh={<RefreshLine at={FIXTURE.staleAt} />} more={<LoadMore onLoad={() => undefined} />}>
                <EmptyState body={FIXTURE.emptyBody} />
            </ListLayout>
            <ReportLayout header={header(FIXTURE.archetypes.report)} period={<span data-fixture={true}>{FIXTURE.figureHint}</span>} completeness={<CostIncomplete />} figures={<Figures><Figure label={FIXTURE.figures.sales} value={LOADED_SALES} /></Figures>} />
            <CommandLayout header={header(FIXTURE.archetypes.command)} width={'focused'}>
                <Card className={'bk-section'}>
                    <Button label={t('ux.action.review')} />
                </Card>
            </CommandLayout>
            <ResultLayout header={header(FIXTURE.archetypes.result)} notice={<Committed message={FIXTURE.committed} />} next={<Button label={t('ux.action.done')} size={'xl'} />} document={<span data-fixture={true}>{FIXTURE.receipt}</span>} />
        </div>
    );
}

/** The pre-authentication archetype alone (KS-01): one centred card, no shell, no business data. */
function PreAuthSpecimen() {
    return (
        <AnnouncerProvider>
            <Head title={FIXTURE.signIn} />
            <PreAuthLayout>
                <h1 className={'bk-title'}>{FIXTURE.signIn}</h1>
                <Field label={FIXTURE.email}>
                    <TextInput type={'email'} autoComplete={'username'} inputMode={'email'} />
                </Field>
                <Field label={t('ux.state.recent_auth.password')}>
                    <TextInput type={'password'} autoComplete={'current-password'} />
                </Field>
                <Button label={FIXTURE.signIn} size={'lg'} block={true} />
            </PreAuthLayout>
        </AnnouncerProvider>
    );
}

export default function Specimen() {
    if (new URLSearchParams(window.location.search).get('tampilan') === 'masuk') {
        return <PreAuthSpecimen />;
    }

    return <FullSpecimen />;
}

function FullSpecimen() {
    const [events, record] = useEvents();
    const [reviewOpen, setReviewOpen] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [method, setMethod] = useState<Method>('cash');
    const [paper, setPaper] = useState<PaperSize>('58');
    const [chips, setChips] = useState<readonly boolean[]>([true, false, false]);
    const [amount, setAmount] = useState<string | null>(AMOUNTS.openingCash);
    const [product, setProduct] = useState('');
    const [search, setSearch] = useState('');
    const [quantities, setQuantities] = useState<Readonly<Record<string, string>>>({ p1: '2', p2: '1' });
    const [showErrors, setShowErrors] = useState(false);
    const [unsavedOpen, setUnsavedOpen] = useState(false);
    const searchRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        applyAccent(document, new URLSearchParams(window.location.search).get('aksen'));
    }, []);

    const cartLines = PRODUCTS.filter((item) => quantities[item.id] !== undefined);
    const lineTotals = cartLines.map((item) => lineTotal(quantities[item.id] ?? AMOUNTS.zero, 0, item.price));
    const cartTotal = sumMoney(AMOUNTS.zero, ...lineTotals);

    const setQuantity = (id: string, value: string): void => setQuantities((current) => ({ ...current, [id]: value }));
    const increment = (id: string): void => setQuantity(id, sumMoney(quantities[id] ?? AMOUNTS.zero, AMOUNTS.one));
    const decrement = (id: string): void => {
        const current = quantities[id] ?? AMOUNTS.one;

        if (compareMoney(current, AMOUNTS.one) > 0) {
            setQuantity(id, subtractMoney(current, AMOUNTS.one));
        }
    };
    const remove = (id: string): void => setQuantities((current) => Object.fromEntries(Object.entries(current).filter(([key]) => key !== id)));
    const add = (id: string): void => {
        setQuantities((current) => ({ ...current, [id]: current[id] ?? AMOUNTS.one }));
        searchRef.current?.focus();
    };
    const openReview = (): void => {
        record(EVENTS.review);
        setReviewOpen(true);
    };
    const confirm = (): void => {
        record(EVENTS.confirm);
        setConfirmed(true);
        setReviewOpen(false);
    };
    const clearCart = (): void => {
        record(EVENTS.clear);
        setQuantities({});
    };
    const toggleChip = (index: number, pressed: boolean): void => setChips((list) => list.map((value, position) => (position === index ? pressed : value)));

    return (
        <AnnouncerProvider>
            <Head title={FIXTURE.heading} />
            <main className={'bk-page'} data-testid={'specimen'}>
                <PageContainer>
                    <PageHeader title={FIXTURE.heading} back={{ href: TOP, page: FIXTURE.heading }} />
                    <div className={'bk-stack'} id={'specimen-top'}>
                        <SpecimenSection id={'buttons'} title={FIXTURE.sections.buttons}>
                            <div className={'bk-actions'}>
                                <Button label={t('ux.action.review')} shortcut={'confirm'} onClick={openReview} data-testid={'open-review'} />
                                <Button label={t('ux.action.cancel')} variant={'secondary'} />
                                <Button label={t('ux.action.reload')} variant={'quiet'} icon={'refresh'} />
                                <Button label={t('ux.action.leave')} variant={'danger'} onClick={() => setUnsavedOpen(true)} data-testid={'open-unsaved'} />
                                <Button label={t('ux.nav.menu_open')} variant={'secondary'} icon={'menu'} iconOnly={true} onClick={() => setDrawerOpen(true)} data-testid={'open-drawer'} />
                            </div>
                            <div className={'bk-actions'}>
                                <Button label={t('ux.action.retry')} data-testid={'button-md'} />
                                <Button label={t('ux.action.retry')} size={'lg'} data-testid={'button-lg'} />
                                <Button label={t('ux.action.retry')} size={'xl'} data-testid={'button-xl'} />
                                <Button label={t('ux.action.check_status')} busy={busy} onClick={() => setBusy(true)} data-testid={'button-busy'} />
                                <StillSubmitting active={busy} />
                            </div>
                            <div className={'bk-actions'}>
                                <Button label={t('ux.action.done')} disabledReason={t('ux.disabled.form_incomplete')} data-testid={'button-disabled'} />
                                <Button label={t('ux.action.save')} variant={'secondary'} disabledReason={t('ux.disabled.no_change')} />
                            </div>
                            <div className={'bk-final'}>
                                <Button label={FIXTURE.finalAction} size={'final'} onClick={() => record(EVENTS.final)} data-testid={'final-action'} />
                            </div>
                            <div className={'bk-actions'}>
                                <Button label={t('ux.action.back_home')} variant={'quiet'} icon={'arrow-left'} shortcut={'back'} onClick={() => record(EVENTS.back)} data-testid={'back-action'} />
                            </div>
                        </SpecimenSection>

                        <SpecimenSection id={'fields'} title={FIXTURE.sections.fields}>
                            <Card className={'bk-section bk-stack'}>
                                <CommandForm label={FIXTURE.sections.fields} onSubmit={() => record(EVENTS.submit)}>
                                    {showErrors && <ErrorSummary errors={fieldErrors()} />}
                                    <Field id={SPECIMEN_IDS.product} label={FIXTURE.productLabel} hint={FIXTURE.productHint} required={true} error={showErrors ? t('ux.validation.required') : undefined}>
                                        <TextInput value={product} onChange={(event) => setProduct(event.target.value)} autoComplete={'off'} data-testid={'field-product'} />
                                    </Field>
                                    <Field id={SPECIMEN_IDS.quantity} label={FIXTURE.quantityLabel} error={showErrors ? t('ux.validation.quantity_positive') : undefined}>
                                        <TextInput suffix={FIXTURE.quantityUnit} numeric={true} inputMode={'decimal'} data-testid={'field-quantity'} />
                                    </Field>
                                    <div className={'bk-actions'}>
                                        <Button label={t('ux.action.review')} onClick={() => setShowErrors(true)} data-testid={'show-errors'} />
                                    </div>
                                </CommandForm>
                                <CommandForm label={FIXTURE.amountLabel} onSubmit={() => record(EVENTS.submit)}>
                                    <Field label={FIXTURE.amountLabel} optional={true}>
                                        <MoneyInput value={amount} onValueChange={setAmount} size={'xl'} data-testid={'field-amount'} />
                                    </Field>
                                </CommandForm>
                            </Card>
                        </SpecimenSection>

                        <SpecimenSection id={'choices'} title={FIXTURE.sections.choices}>
                            <SegmentedControl legend={FIXTURE.method} size={'xl'} value={method} onValueChange={setMethod} options={METHODS} />
                            <SegmentedControl legend={FIXTURE.paper} value={paper} onValueChange={setPaper} options={PAPERS} />
                            <FilterChips label={FIXTURE.filter}>
                                {FIXTURE.chips.map((label, index) => (
                                    <FilterChip key={label} label={label} pressed={chips[index] === true} onPressedChange={(pressed) => toggleChip(index, pressed)} />
                                ))}
                            </FilterChips>
                        </SpecimenSection>

                        <SpecimenSection id={'status'} title={FIXTURE.sections.status}>
                            <div className={'bk-actions'}>
                                {TONES.map((tone) => (
                                    <StatusWord key={tone} tone={tone} label={FIXTURE.tones[tone]} />
                                ))}
                                <StatusWord tone={'warning'} label={formatVariance(AMOUNTS.variance)} />
                            </div>
                            <div className={'bk-actions'}>
                                {TONES.map((tone) => (
                                    <Pill key={tone} tone={tone} label={FIXTURE.tones[tone]} />
                                ))}
                            </div>
                            <div className={'bk-actions'} data-testid={'marks'}>
                                {MARKS.map((kind) => (
                                    <StateMark key={kind} kind={kind} />
                                ))}
                            </div>
                        </SpecimenSection>

                        <SpecimenSection id={'notices'} title={FIXTURE.sections.notices}>
                            {RECOVERY.map((kind) => (
                                <RecoveryPanel key={kind} kind={kind} mustNot={FIXTURE.mustNot} onResend={() => record(EVENTS.resend)} onCheckStatus={() => record(EVENTS.check)} />
                            ))}
                            <StaleRead at={FIXTURE.staleAt} onReload={() => record(EVENTS.reload)} />
                            <LoadFailed onRetry={() => record(EVENTS.retry)} />
                            <CostIncomplete />
                            <UnknownCode code={FIXTURE.unknownCode} />
                            <TenderLow missing={FIXTURE.tenderMissing} />
                            {confirmed && <Committed message={FIXTURE.committed} />}
                            <DisabledReason id={SPECIMEN_IDS.reason} reason={t('ux.disabled.unresolved_family')} unresolvedHref={TOP} />
                        </SpecimenSection>

                        <SpecimenSection id={'dialogs'} title={FIXTURE.sections.dialogs}>
                            <div className={'bk-actions'}>
                                <Menu label={FIXTURE.menu} icon={'more'} iconOnly={true} items={pageMenu(record)} />
                            </div>
                        </SpecimenSection>

                        <SpecimenSection id={'data'} title={FIXTURE.sections.data}>
                            <Card>
                                <Figures>
                                    {FIGURES.map((figure) => (
                                        <Figure key={figure.label} label={figure.label} value={figure.value} hint={figure.hint} incomplete={figure.incomplete} />
                                    ))}
                                </Figures>
                            </Card>
                            <Section title={FIXTURE.rows}>
                                <LinkRows label={FIXTURE.rows}>
                                    {FIXTURE.rowItems.map((title, index) => (
                                        <li key={title}>
                                            <LinkRow href={TOP} title={title} meta={FIXTURE.rowMeta[index]} icon={ROW_ICONS[index]} tone={ROW_TONES[index]} />
                                        </li>
                                    ))}
                                </LinkRows>
                            </Section>
                            <Section title={FIXTURE.table} titleHidden={true}>
                                <RefreshLine at={FIXTURE.staleAt} count={FIXTURE.resultCount} onReload={() => record(EVENTS.reload)} />
                                <DataTable caption={FIXTURE.table} rows={PRODUCTS} rowKey={(row) => row.id} columns={productColumns(record, openReview)} />
                                <LoadMore onLoad={() => record(EVENTS.more)} />
                            </Section>
                            <Section title={FIXTURE.wideTable}>
                                <DataTable caption={FIXTURE.wideTable} wide={true} rows={HISTORY} rowKey={(row) => row.id} columns={HISTORY_COLUMNS} />
                            </Section>
                        </SpecimenSection>

                        <SpecimenSection id={'chart'} title={FIXTURE.sections.chart}>
                            <Card className={'bk-section'}>
                                <TrendChart
                                    title={FIXTURE.chartTitle}
                                    points={TREND_POINTS}
                                    pointText={(point) => `${point.date}: ${point.valueText}`}
                                    showTableLabel={FIXTURE.showTable}
                                    showChartLabel={FIXTURE.showChart}
                                    dateHeader={FIXTURE.dateHeader}
                                    valueHeader={FIXTURE.valueHeader}
                                />
                            </Card>
                        </SpecimenSection>

                        <SpecimenSection id={'till'} title={FIXTURE.sections.till}>
                            <Card>
                                <TillWorkspace
                                    work={
                                        <>
                                            <ScanField label={FIXTURE.searchLabel} hint={t('ux.list.search_hint')} value={search} onValueChange={setSearch} onLookup={() => record(EVENTS.lookup)} inputRef={searchRef} />
                                            <ProductResults label={FIXTURE.results}>
                                                {PRODUCTS.map((item) => (
                                                    <ProductResult
                                                        key={item.id}
                                                        name={item.name}
                                                        meta={
                                                            <>
                                                                <span className={'bk-mono'}>{item.sku}</span>
                                                                <span>{item.stock}</span>
                                                            </>
                                                        }
                                                        price={formatMoney(item.price)}
                                                        disabledReason={item.status === 'danger' ? FIXTURE.tones.danger : undefined}
                                                        onAdd={() => add(item.id)}
                                                    />
                                                ))}
                                            </ProductResults>
                                        </>
                                    }
                                    cart={
                                        <CartRegion
                                            label={FIXTURE.cart}
                                            head={
                                                <>
                                                    <h3 className={'bk-h3'}>{FIXTURE.cart}</h3>
                                                    <Button label={FIXTURE.clearCart} variant={'quiet'} icon={'trash'} onClick={clearCart} />
                                                </>
                                            }
                                            foot={<CartTotal label={FIXTURE.total} value={formatMoney(cartTotal)} payLabel={FIXTURE.pay} onPay={() => record(EVENTS.pay)} />}
                                        >
                                            {cartLines.length === 0 ? (
                                                <li>
                                                    <EmptyState body={FIXTURE.emptyBody} />
                                                </li>
                                            ) : (
                                                cartLines.map((item, index) => (
                                                    <CartLine
                                                        key={item.id}
                                                        name={item.name}
                                                        meta={formatMoney(item.price)}
                                                        total={formatMoney(lineTotals[index] ?? AMOUNTS.zero)}
                                                        controls={
                                                            <>
                                                                <QuantityStepper
                                                                    label={FIXTURE.quantityOf(item.name)}
                                                                    value={quantities[item.id] ?? ''}
                                                                    onValueChange={(value) => setQuantity(item.id, value)}
                                                                    onCommit={() => searchRef.current?.focus()}
                                                                    onIncrement={() => increment(item.id)}
                                                                    onDecrement={() => decrement(item.id)}
                                                                    incrementLabel={FIXTURE.increment(item.name)}
                                                                    decrementLabel={FIXTURE.decrement(item.name)}
                                                                    canDecrement={quantities[item.id] !== AMOUNTS.one}
                                                                />
                                                                <Button label={t('ux.action.remove_line')} rowName={item.name} variant={'quiet'} icon={'trash'} onClick={() => remove(item.id)} />
                                                            </>
                                                        }
                                                    />
                                                ))
                                            )}
                                        </CartRegion>
                                    }
                                />
                            </Card>
                        </SpecimenSection>

                        <SpecimenSection id={'steps'} title={FIXTURE.sections.steps}>
                            <Steps label={FIXTURE.steps} steps={FIXTURE.stepItems} current={1} />
                        </SpecimenSection>

                        <SpecimenSection id={'paper'} title={FIXTURE.sections.paper}>
                            <ReceiptPaper size={paper} label={FIXTURE.receipt} copyMarker={FIXTURE.copyMarker}>
                                <PaperShop name={FIXTURE.shop} lines={FIXTURE.shopLines} />
                                {paper === 'a4' && <PaperTitle>{FIXTURE.notaTitle}</PaperTitle>}
                                <PaperRule />
                                <PaperItems>
                                    {PRODUCTS.map((item) => (
                                        <PaperItem key={item.id} name={item.name} sku={item.sku} detail={lineDetail(AMOUNTS.one, formatMoney(item.price))} total={formatMoney(item.price)} />
                                    ))}
                                </PaperItems>
                                <PaperRule />
                                <PaperRow label={FIXTURE.total} value={formatMoney(AMOUNTS.receiptTotal)} total={true} />
                            </ReceiptPaper>
                        </SpecimenSection>

                        <SpecimenSection id={'archetypes'} title={FIXTURE.sections.archetypes}>
                            <Archetypes />
                        </SpecimenSection>

                        <SpecimenSection id={'states'} title={FIXTURE.sections.states}>
                            <Loading />
                            <AnnounceBoth />
                            <EmptyState body={FIXTURE.emptyBody} />
                            <NoResults onClear={() => record(EVENTS.clear)} />
                            <KeyValueList items={cashSummary(UNKNOWN_COST_VALUE)} />
                            {NOTICE_STATES.map((state) => (
                                <div key={state} data-state-id={state}>
                                    <StateNotice state={state} />
                                </div>
                            ))}
                        </SpecimenSection>
                    </div>
                </PageContainer>
            </main>
            <ReviewDialog open={reviewOpen} onOpenChange={setReviewOpen} title={FIXTURE.reviewTitle} confirmLabel={FIXTURE.confirm} onConfirm={confirm}>
                <KeyValueList items={cashSummary(AMOUNTS.expected)} />
            </ReviewDialog>
            <UnsavedDialog open={unsavedOpen} onStay={() => setUnsavedOpen(false)} onLeave={() => (record(EVENTS.leave), setUnsavedOpen(false))} />
            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} title={FIXTURE.drawerTitle}>
                <ul className={'bk-rows'}>
                    {FIXTURE.drawerLinks.map((label) => (
                        <li key={label}>
                            <a className={'bk-menu__item'} href={TOP}>
                                {label}
                            </a>
                        </li>
                    ))}
                </ul>
            </Drawer>
            <output hidden={true} data-testid={'events'}>
                {events.join(' ')}
            </output>
        </AnnouncerProvider>
    );
}
