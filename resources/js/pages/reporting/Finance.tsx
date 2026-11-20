import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { SegmentedControl } from '@/components/ui/Choices';
import { Figure, Figures, type ReadValue } from '@/components/ui/Data';
import { Field, TextInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import { CostIncomplete, EmptyState, ErrorSummary, LoadFailed, Loading, type FieldError } from '@/components/ui/States';
import { TrendChart, type TrendPoint } from '@/components/ui/TrendChart';
import { AppShell } from '@/layouts/AppShell';
import { PageHeader, ReportLayout } from '@/layouts/Page';
import { WORKLIST_PAGE } from '@/lib/costing/costs';
import { formatCost, formatCount, formatDate, formatDateTime, formatMoney, formatPercent, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import {
    FINANCE_SHELL,
    financeAddress,
    PERIODS,
    rangeErrors,
    rememberedAddress,
    rememberPeriod,
    REPORT_PROP,
    type AppliedPeriod,
    type FinanceReportRead,
    type PeriodKind,
    type RangeErrors,
} from '@/lib/reporting/finance';

// UXP-502 "Laporan keuangan" (KEY_SCREENS KS-10; ROUTE_CONTRACTS RP-502; COPY_DECK sections 10.1 and 10.3;
// INTERACTION_INVENTORY UXI-308 to UXI-312; NAVIGATION_CONTRACTS UXP-502; UI_STATES section 6: UXS-01, UXS-02,
// UXS-05, UXS-07, UXS-09, UXS-20, UXS-22, UXS-25; INTERACTION_CONTRACTS section 3.4; ADAPTIVE_AND_ACCESSIBILITY
// UXA-19; D-018; BR-COST-34 to BR-COST-41; TASK-040 scope item 2), the Owner's alone (CAP-20). The period choice
// (UXI-308) reads the report of its period at once; "Rentang Kustom" (UXI-309) shows the two dates, which
// "Terapkan" checks at their fields before anything is fetched and "Batal" leaves for the period applied before
// (BR-COST-37). Completeness stands before the figures; while cost is incomplete the sales and payment totals
// still show, HPP and gross profit are marked "Data biaya belum lengkap" with the counts and the link
// "Biaya perlu dilengkapi" (UXI-312), and the margin is hidden (UXS-25). The trend (UXI-310) is one series,
// net sales per Asia/Jakarta business day, reachable with the keyboard, and "Tampilkan sebagai tabel" (UXI-311)
// shows the same data. Everything comes from the server's one snapshot, whose time is the as-of line; a read
// that fails is UXS-22 with "Coba lagi", never zero (TR-TX-07). The applied period stands in the address and
// is kept for the sign-in session.

interface FinanceProps {
    readonly period: AppliedPeriod;
    /** Today in Asia/Jakarta at the request: the last date a custom range may end on. */
    readonly today: string;
    readonly report: FinanceReportRead | null;
}

type Read = 'read' | 'reading' | 'failed';

const FROM_ID = 'finance-from';

const TO_ID = 'finance-to';

/** The five periods wrap by the space they have (KS-10). */
const PERIOD_LAYOUT = 'wrap';

export default function Finance(props: FinanceProps) {
    const title = t('reporting.finance.title');

    return (
        <AppShell title={title} {...FINANCE_SHELL}>
            <FinancePage title={title} {...props} />
        </AppShell>
    );
}

function FinancePage({ title, period, today, report }: FinanceProps & { title: string }) {
    const [read, setRead] = useState<Read>('read');

    // The period kept for the sign-in session: an address without a period opens on it (NAVIGATION_CONTRACTS UXP-502).
    useEffect(() => {
        const kept = window.location.search === '' ? rememberedAddress() : null;

        if (kept !== null && kept !== financeAddress(period)) {
            router.visit(kept, { replace: true, preserveScroll: true });

            return;
        }

        rememberPeriod(period);
    }, [period]);

    const failed = (): boolean => {
        setRead('failed');

        return false;
    };

    const visit = (next: AppliedPeriod): void => {
        setRead('reading');
        router.visit(financeAddress(next), { preserveScroll: true, onHttpException: failed, onNetworkError: failed });
    };

    const retry = useCallback((): void => {
        const refused = (): boolean => {
            setRead('failed');

            return false;
        };

        setRead('reading');
        router.reload({ only: [REPORT_PROP], onSuccess: () => setRead('read'), onHttpException: refused, onNetworkError: refused });
    }, []);

    const shown = read === 'read' ? report : null;

    return (
        <ReportLayout
            header={<PageHeader title={title} subtitle={shown === null ? undefined : t('reporting.as_of', { waktu: formatDateTime(shown.asOf) })} />}
            period={<PeriodChoice applied={period} today={today} shownRange={shown?.period ?? null} busy={read === 'reading'} onApply={visit} />}
            completeness={shown !== null && !shown.empty ? <Completeness report={shown} /> : undefined}
            figures={
                read === 'reading' ? (
                    <Loading lines={4} />
                ) : shown === null ? (
                    <LoadFailed onRetry={retry} />
                ) : shown.empty ? (
                    <EmptyState body={t('reporting.finance.empty')} />
                ) : (
                    <FinanceFigures report={shown} />
                )
            }
            chart={shown !== null && !shown.empty ? <Trend report={shown} /> : undefined}
        />
    );
}

/**
 * UXI-308 and UXI-309: the five periods; a preset reads its report at once, "Rentang Kustom" opens its dates.
 */
function PeriodChoice({
    applied,
    today,
    shownRange,
    busy,
    onApply,
}: {
    applied: AppliedPeriod;
    today: string;
    shownRange: { readonly from: string; readonly to: string } | null;
    busy: boolean;
    onApply: (period: AppliedPeriod) => void;
}) {
    const [choice, setChoice] = useState<PeriodKind>(applied.kind);
    const [from, setFrom] = useState(applied.from ?? shownRange?.from ?? '');
    const [to, setTo] = useState(applied.to ?? shownRange?.to ?? '');
    const [errors, setErrors] = useState<RangeErrors | null>(null);

    const choose = (kind: PeriodKind): void => {
        setChoice(kind);
        setErrors(null);

        if (kind !== 'custom') {
            onApply({ kind, from: null, to: null });
        }
    };

    const apply = (): void => {
        const found = rangeErrors(from, to, today);

        if (found.from !== undefined || found.to !== undefined) {
            setErrors(found);

            return;
        }

        setErrors(null);
        onApply({ kind: 'custom', from, to });
    };

    // "Batal" keeps the period applied before, and its dates (BR-COST-37).
    const cancel = (): void => {
        setChoice(applied.kind);
        setFrom(applied.from ?? shownRange?.from ?? '');
        setTo(applied.to ?? shownRange?.to ?? '');
        setErrors(null);
    };

    const summary: FieldError[] = [];

    if (errors?.from !== undefined) {
        summary.push({ fieldId: FROM_ID, label: t('reporting.period.start'), message: t(errors.from) });
    }

    if (errors?.to !== undefined) {
        summary.push({ fieldId: TO_ID, label: t('reporting.period.end'), message: t(errors.to) });
    }

    return (
        <section className={'bk-card bk-section'}>
            <SegmentedControl legend={t('reporting.period.label')} layout={PERIOD_LAYOUT} options={PERIODS.map((entry) => ({ value: entry.kind, label: t(entry.label) }))} value={choice} onValueChange={choose} />
            {choice === 'custom' && (
                <div className={'bk-stack'}>
                    {summary.length > 0 && <ErrorSummary errors={summary} />}
                    <div className={'bk-custom'}>
                        <Field id={FROM_ID} label={t('reporting.period.start')} error={errors?.from === undefined ? undefined : t(errors.from)}>
                            <TextInput type={'date'} max={today} value={from} onChange={(event) => setFrom(event.target.value)} />
                        </Field>
                        <Field id={TO_ID} label={t('reporting.period.end')} error={errors?.to === undefined ? undefined : t(errors.to)}>
                            <TextInput type={'date'} max={today} value={to} onChange={(event) => setTo(event.target.value)} />
                        </Field>
                        <div className={'bk-actions'}>
                            <Button label={t('ux.action.apply')} variant={'primary'} busy={busy} onClick={apply} />
                            <Button label={t('ux.action.cancel')} variant={'secondary'} onClick={cancel} />
                        </div>
                    </div>
                </div>
            )}
            {shownRange !== null && <p className={'bk-meta'}>{t('reporting.period.range', { awal: formatDate(shownRange.from), akhir: formatDate(shownRange.to) })}</p>}
        </section>
    );
}

/** Completeness before the figures (BR-COST-38): the counts and the link while incomplete (UXS-25, UXI-312). */
function Completeness({ report }: { report: FinanceReportRead }) {
    const { complete, unknownLines, markedProducts, lastCorrectionAt } = report.completeness;
    const correction = lastCorrectionAt === null ? t('reporting.no_correction') : t('reporting.last_correction', { waktu: formatDateTime(lastCorrectionAt) });

    if (complete) {
        return (
            <Notice tone={'success'} title={t('reporting.complete')}>
                <p>{correction}</p>
            </Notice>
        );
    }

    return (
        <CostIncomplete action={<ButtonLink href={WORKLIST_PAGE} label={t('costing.worklist.title')} variant={'secondary'} />}>
            <p>{t('reporting.unknown_lines', { jumlah: formatCount(unknownLines) })}</p>
            <p>{t('reporting.marked_products', { jumlah: formatCount(markedProducts) })}</p>
            <p>{correction}</p>
        </CostIncomplete>
    );
}

function loaded(text: string): ReadValue {
    return { status: 'loaded', text };
}

/**
 * The figures of BR-COST-34 (DESIGN_SYSTEM 8.12): sales and payment totals always; HPP and gross profit marked
 * incomplete while cost is incomplete; the margin only when cost is complete and net sales are above zero; never
 * net profit (BR-COST-41).
 */
function FinanceFigures({ report }: { report: FinanceReportRead }) {
    const { figures } = report;
    const complete = report.completeness.complete;

    return (
        <Section title={t('reporting.finance.title')} titleHidden={true}>
            <div className={'bk-stack'}>
                <Figures>
                    <Figure label={t('reporting.figure.gross_sales')} value={loaded(formatMoney(figures.grossSales))} hint={t('reporting.figure.gross_sales_help')} />
                    <Figure label={t('reporting.figure.refunds')} value={loaded(formatMoney(figures.refunds))} />
                    <Figure label={t('reporting.figure.net_sales')} value={loaded(formatMoney(figures.netSales))} hint={t('reporting.figure.net_sales_help')} />
                    <Figure label={t('reporting.figure.cogs')} value={loaded(formatCost(figures.cogs))} incomplete={!complete} hint={complete ? t('reporting.figure.cogs_help') : undefined} />
                    <Figure label={t('reporting.figure.gross_profit')} value={loaded(formatCost(figures.grossProfit))} incomplete={!complete} hint={complete ? t('reporting.figure.gross_profit_help') : undefined} />
                    {complete && figures.margin !== null && <Figure label={t('reporting.figure.margin')} value={loaded(formatPercent(figures.margin))} hint={t('reporting.figure.margin_help')} />}
                </Figures>
                {complete && figures.margin === null && <p className={'bk-meta'}>{t('reporting.margin_hidden_zero')}</p>}
                <Figures>
                    <Figure label={t('reporting.figure.sale_count')} value={loaded(formatCount(figures.saleCount))} />
                    <Figure label={t('reporting.figure.payments_cash')} value={loaded(formatMoney(figures.paymentsCash))} />
                    <Figure label={t('reporting.figure.payments_transfer')} value={loaded(formatMoney(figures.paymentsTransfer))} />
                    <Figure label={t('reporting.figure.refund_payouts_cash')} value={loaded(formatMoney(figures.refundPayoutsCash))} />
                    <Figure label={t('reporting.figure.refund_payouts_transfer')} value={loaded(formatMoney(figures.refundPayoutsTransfer))} />
                    <Figure label={t('reporting.figure.quantity_sold')} value={loaded(formatQuantity(figures.quantitySold, 3))} />
                    <Figure label={t('reporting.figure.quantity_returned')} value={loaded(formatQuantity(figures.quantityReturned, 3))} />
                </Figures>
            </div>
        </Section>
    );
}

/** UXI-310 and UXI-311: net sales per business day of the period, from the same snapshot (REQ-RPT-06, UXA-19). */
function Trend({ report }: { report: FinanceReportRead }) {
    const netSales = t('reporting.figure.net_sales');
    const points: TrendPoint[] = report.trend.map((day) => ({ date: formatDate(day.date), value: day.netSales, valueText: formatMoney(day.netSales) }));

    return (
        <Section title={t('reporting.finance.trend')}>
            <TrendChart
                title={t('reporting.finance.trend_label', { ukuran: netSales })}
                points={points}
                pointText={(point) => t('reporting.finance.trend_point', { tanggal: point.date, nilai: point.valueText })}
                showTableLabel={t('reporting.finance.show_table')}
                showChartLabel={t('reporting.finance.show_chart')}
                dateHeader={t('reporting.finance.table_date')}
                valueHeader={netSales}
            />
        </Section>
    );
}
