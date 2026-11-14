import { ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Notice } from '@/components/ui/Notice';
import { Committed } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import { CorrectionEntries } from '@/lib/sales/Corrections';
import type { Correction } from '@/lib/sales/refunds';
import { salePage } from '@/lib/sales/sales';

// UXP-204 "Pengembalian tercatat" (ROUTE_CONTRACTS RP-204; COPY_DECK sections 7.7 and 7.9; INTERACTION_INVENTORY
// UXI-133 and UXI-134; INTERACTION_CONTRACTS section 2.6; NAVIGATION_CONTRACTS UXP-204; UI_STATES section 6;
// SALES_CASH BR-RFD-17, BR-CASH-19; D-046, D-068; TASK-034 scope item 3): the committed correction. For a
// drawer-funded refund its first and largest element is the instruction to take the amount from the named
// drawer now and hand it to the customer, with the guidance to do so before the next opening count of that
// drawer (UXI-133): the cash leaves the drawer only now, after the commit. Otherwise the refund or the return
// is said recorded. Then the payout's reference, the session the cash came from, the entries of the money and
// the goods and the reason; "Kembali ke Detail penjualan" (UXI-134), the page's one way back, leads to UXP-202:
// no link stands above the instruction (NAVIGATION_CONTRACTS UXP-204).

const DESTINATION: DestinationId = 'sales';

interface RefundRecordedProps {
    readonly sale: { readonly id: number; readonly number: string };
    readonly correction: Correction;
}

export default function RefundRecorded({ sale, correction }: RefundRecordedProps) {
    const title = t('sales.refund_result.title');
    const refund = correction.refund;
    const drawer = refund?.drawer ?? null;
    const back = salePage(sale.id);
    const facts: KeyValue[] = [
        ...(refund === null ? [] : [{ key: 'reference', label: t('sales.detail.reference'), value: refund.reference }]),
        ...(drawer === null ? [] : [{ key: 'session', label: t('cash.sessions.title'), value: t('sales.detail.session_link', { nomor: drawer.sessionNumber }) }]),
    ];

    return (
        <AppShell title={title} current={DESTINATION}>
            <PageContainer width={'form'}>
                <div className={'bk-stack'}>
                    <PageHeader title={title} subtitle={sale.number} />
                    {refund !== null && drawer !== null ? (
                        <Notice tone={'warning'} urgency={'status'} title={t('sales.refund_result.take_cash', { uang: formatMoney(refund.amount), laci: drawer.registerLabel })}>
                            <p>{t('sales.refund_result.take_cash_before')}</p>
                        </Notice>
                    ) : (
                        <Committed message={refund === null ? t('sales.refund_result.return_recorded') : t('sales.refund_result.recorded', { uang: formatMoney(refund.amount) })} />
                    )}
                    <Section title={t('sales.detail.refunds')}>
                        <div className={'bk-stack'}>
                            {facts.length > 0 && <KeyValueList items={facts} />}
                            <CorrectionEntries correction={correction} />
                        </div>
                    </Section>
                    <div className={'bk-actions'}>
                        <ButtonLink href={back} label={t('ux.action.back_to', { halaman: t('sales.detail.title') })} variant={'primary'} size={'lg'} />
                    </div>
                </div>
            </PageContainer>
        </AppShell>
    );
}
