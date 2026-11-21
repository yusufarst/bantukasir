import { ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { Figure, Figures, type ReadValue } from '@/components/ui/Data';
import { Notice } from '@/components/ui/Notice';
import { CostIncomplete } from '@/components/ui/States';
import { WORKLIST_PAGE } from '@/lib/costing/costs';
import { formatCost, formatCount, formatDateTime, formatMoney, formatPercent, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { FinanceFigures } from '@/lib/reporting/finance';

// The parts of the Owner's reports (COPY_DECK section 10.1; DESIGN_SYSTEM 8.12; BR-COST-34 to BR-COST-41;
// BR-RPT-01): the completeness before the figures and the figures themselves, which Laporan keuangan (UXP-502,
// TASK-040) and Rekap harian (UXP-501, TASK-041) show alike from the server's one computation.

/** The completeness of a report's cost figures (BR-COST-38). */
export interface ReportCompleteness {
    readonly complete: boolean;
    readonly unknownLines: number;
    readonly markedProducts: number;
    readonly lastCorrectionAt: string | null;
}

/** Completeness before the figures (BR-COST-38): the counts and the link while incomplete (UXS-25, UXI-312). */
export function Completeness({ completeness }: { completeness: ReportCompleteness }) {
    const { complete, unknownLines, markedProducts, lastCorrectionAt } = completeness;
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
 * net profit (BR-COST-41). The section's title is the page's, for assistive technology only.
 */
export function ReportFigures({ title, figures, complete }: { title: string; figures: FinanceFigures; complete: boolean }) {
    return (
        <Section title={title} titleHidden={true}>
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
