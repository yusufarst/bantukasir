import type { ComponentType } from 'react';
import { PaperRow, PaperRule, PaperShop, PaperTitle, ReceiptPaper } from '@/components/ui/ReceiptPaper';
import { refundsText, registerText } from '@/lib/cash/sessions';
import { formatCount, formatDateTime, formatMoney, formatVariance } from '@/lib/format';
import { t } from '@/lib/i18n';

// The session report of DOCUMENT_CONTRACTS DC-02 as a template version of the code base (ARC-44, ARC-45;
// BR-CASH-19, BR-CASH-20; COPY_DECK section 17.2; TASK-031 scope items 1 and 2): rendered on A4 from the
// CashSessionReportSnapshot only, rows 1 to 12 in their order, never a cost, a margin or a profit, and
// without a copy marker. A template a report names is never changed in place: a change is a new version
// beside `session_report.1` in SESSION_REPORT_TEMPLATES.

/** The report as its snapshot froze it (App\Domain\Documents\SessionReports\SessionReportDocuments). */
export interface SessionReportSnapshot {
    readonly sessionId: number;
    readonly number: string;
    readonly registerCode: string;
    readonly registerLabel: string;
    readonly responsible: string;
    readonly openedAt: string;
    readonly cutoffAt: string;
    readonly closedAt: string;
    readonly openingCash: string;
    readonly saleCount: number;
    readonly saleValue: string;
    readonly cashPayments: string;
    readonly transferPayments: string;
    readonly drawerRefunds: string;
    readonly expectedCash: string;
    readonly physicalCash: string;
    readonly variance: string;
    readonly closedBy: string;
    readonly closeReason: string | null;
    readonly notes: string | null;
    readonly template: string;
}

/** The shop's name and document identity of the revision the report froze (DC-02 row 1). */
export interface SessionReportIssuer {
    readonly name: string;
    readonly identity: string | null;
}

interface SessionReportProps {
    readonly report: SessionReportSnapshot;
    readonly issuer: SessionReportIssuer;
    readonly label: string;
}

/** `session_report.1`: DC-02 rows 1 to 12. */
export function SessionReportV1({ report, issuer, label }: SessionReportProps) {
    return (
        <ReceiptPaper size={'a4'} label={label}>
            <PaperShop name={issuer.name} lines={issuer.identity === null ? [] : [issuer.identity]} />
            <PaperTitle>{t('documents.session_report.title')}</PaperTitle>
            <p>{t('documents.session_report.number', { nomor: report.number })}</p>
            <PaperRule />
            <PaperRow label={t('documents.session_report.register')} value={registerText(report.registerCode, report.registerLabel)} />
            <PaperRow label={t('documents.session_report.responsible')} value={report.responsible} />
            <PaperRow label={t('documents.session_report.opened_at')} value={formatDateTime(report.openedAt)} />
            <PaperRow label={t('documents.session_report.cutoff')} value={formatDateTime(report.cutoffAt)} />
            <PaperRow label={t('documents.session_report.closed_at')} value={formatDateTime(report.closedAt)} />
            <PaperRule />
            <PaperRow label={t('documents.session_report.opening_cash')} value={formatMoney(report.openingCash)} />
            <PaperRow label={t('documents.session_report.sale_count')} value={formatCount(report.saleCount)} />
            <PaperRow label={t('documents.session_report.sale_value')} value={formatMoney(report.saleValue)} />
            <PaperRow label={t('documents.session_report.cash_payments')} value={formatMoney(report.cashPayments)} />
            <PaperRow label={t('documents.session_report.transfer_payments')} value={formatMoney(report.transferPayments)} />
            <PaperRow label={t('documents.session_report.drawer_refunds')} value={refundsText(report.drawerRefunds)} />
            <PaperRule />
            <PaperRow label={t('documents.session_report.expected_cash')} value={formatMoney(report.expectedCash)} />
            <PaperRow label={t('documents.session_report.physical_cash')} value={formatMoney(report.physicalCash)} />
            <PaperRow label={t('documents.session_report.variance')} value={formatVariance(report.variance)} total={true} />
            <PaperRule />
            <PaperRow label={t('documents.session_report.closed_by')} value={report.closedBy} />
            {report.closeReason !== null && <PaperRow label={t('documents.session_report.close_reason')} value={report.closeReason} />}
            {report.notes !== null && <PaperRow label={t('documents.session_report.notes')} value={report.notes} />}
            <PaperRule />
            <p>{t('documents.session_report.time_zone')}</p>
        </ReceiptPaper>
    );
}

/** Every session report template of the code base, by the version a report names (ARC-45). */
export const SESSION_REPORT_TEMPLATES: Readonly<Record<string, ComponentType<SessionReportProps>>> = {
    'session_report.1': SessionReportV1,
};

/** The report by the template it names; a version the code base does not know is a defect. */
export function SessionReportDocument(props: SessionReportProps) {
    const Template = SESSION_REPORT_TEMPLATES[props.report.template];

    if (Template === undefined) {
        throw new Error(`The session report template ${props.report.template} is not in the code base (ARC-45).`);
    }

    return <Template {...props} />;
}
