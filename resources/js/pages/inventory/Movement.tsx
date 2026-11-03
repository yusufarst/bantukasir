import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { StateMark } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { formatCost, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { HISTORY_PAGE, HISTORY_SHELL, ISSUES_PAGE, openerOf, reasonWord, RECEIVINGS_PAGE, REQUESTS_PAGE, signedQuantity, sourceLink, typeWord, type MovementSource, type MovementType } from '@/lib/inventory/stock';

// UXP-403 "Detail mutasi stok" (ROUTE_CONTRACTS RP-403; COPY_DECK section 9.4; INTERACTION_INVENTORY
// UXI-224, UXI-225 and UXI-229; NAVIGATION_CONTRACTS UXP-403; UI_STATES section 6; BR-INV-02,
// BR-INV-44; CAP-14, CAP-20; TASK-022 scope item 2): one movement with its type, product,
// signed quantity, time, who recorded it, its reference, its reason and its source, a receiving
// document or a manual issue (TASK-023), as a link (UXI-224); for the Owner alone its cost
// evidence (UXI-225): the status, the total or
// "Biaya perlu dilengkapi pemilik", never Rp0 for UNKNOWN (UXS-24), and a zero's evidence or
// reason. "Kembali ke …" names the page that opened it, UXP-402 by default (UXI-229). The
// reversal and the cost correction (UXI-226 to UXI-228) are TASK-025's and TASK-026's.

interface Movement {
    readonly id: number;
    readonly type: MovementType;
    readonly quantity: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
    readonly recordedAt: string;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly actorDisplayName: string;
    readonly reference: string | null;
    /** A manual issue's reason (BR-INV-19). */
    readonly reasonCode: string | null;
    readonly source: MovementSource | null;
}

interface CostEvidence {
    readonly status: 'KNOWN' | 'UNKNOWN';
    readonly total: string | null;
    readonly zeroNote: string | null;
}

export default function Movement({ movement, costEvidence }: { movement: Movement; costEvidence?: CostEvidence | null }) {
    const title = t('inventory.movement.title');

    return (
        <AppShell title={title} {...HISTORY_SHELL}>
            <MovementPage title={title} movement={movement} costEvidence={costEvidence} />
        </AppShell>
    );
}

/** The way back of UXI-229: the page that opened this one when it is one of the contract's, else UXP-402. */
function backOf(): { readonly href: string; readonly page: string } {
    const opener = openerOf(document.referrer, window.location.origin);
    const path = opener?.pathname ?? '';

    if (opener !== null && path.startsWith(`${RECEIVINGS_PAGE}/`) && /^\/stock\/receivings\/[0-9]+$/.test(path)) {
        return { href: path, page: t('inventory.receipt_detail.title') };
    }

    if (opener !== null && path.startsWith(`${ISSUES_PAGE}/`) && /^\/stock\/issues\/[0-9]+$/.test(path)) {
        return { href: path, page: t('inventory.issue_detail.title') };
    }

    if (opener !== null && path === REQUESTS_PAGE) {
        return { href: REQUESTS_PAGE, page: t('commands.unresolved.title') };
    }

    return { href: opener !== null && path === HISTORY_PAGE ? `${path}${opener.search}` : HISTORY_PAGE, page: t('inventory.history.title') };
}

function MovementPage({ title, movement, costEvidence }: { title: string; movement: Movement; costEvidence: CostEvidence | null | undefined }) {
    const [back] = useState(backOf);
    const link = movement.source === null ? null : sourceLink(movement.source);

    const items: KeyValue[] = [
        { key: 'type', label: t('inventory.movement.type'), value: typeWord(movement.type) },
        { key: 'product', label: t('inventory.movement.product'), value: movement.product.name },
        { key: 'quantity', label: t('inventory.movement.quantity'), value: <span className={'bk-num'}>{signedQuantity(movement.quantity, movement.quantityPrecision, movement.baseUnit)}</span> },
        { key: 'time', label: t('inventory.movement.time'), value: formatDateTime(movement.recordedAt) },
        { key: 'actor', label: t('inventory.movement.actor'), value: movement.actorDisplayName },
        ...(movement.reasonCode === null ? [] : [{ key: 'reason', label: t('inventory.movement.reason'), value: reasonWord(movement.reasonCode) }]),
        ...(movement.reference === null ? [] : [{ key: 'reference', label: t('inventory.movement.reference'), value: movement.reference }]),
        ...(link === null
            ? []
            : [
                  {
                      key: 'source',
                      label: t('inventory.movement.source'),
                      value: (
                          <a className={'bk-table-link'} href={link.href}>
                              {link.label}
                          </a>
                      ),
                  },
              ]),
    ];

    return (
        <PageContainer width={'form'}>
            <PageHeader title={title} subtitle={t('inventory.movement.subtitle', { jenis: typeWord(movement.type), produk: movement.product.name })} back={back} />
            <div className={'bk-stack'}>
                <Section title={title} titleHidden={true}>
                    <KeyValueList items={items} />
                </Section>
                {costEvidence !== undefined && costEvidence !== null && <EvidenceSection evidence={costEvidence} />}
            </div>
        </PageContainer>
    );
}

/** UXI-225, the Owner's: the status, the total, and a zero's evidence or reason (BR-COST-02 to BR-COST-04). */
function EvidenceSection({ evidence }: { evidence: CostEvidence }) {
    const zero = evidence.status === 'KNOWN' && evidence.total !== null && /^0(\.0+)?$/.test(evidence.total);
    const status = evidence.status === 'UNKNOWN' ? t('costing.status.unknown') : zero ? t('costing.status.zero') : t('costing.status.known');
    const items: KeyValue[] = [
        { key: 'status', label: t('catalog.field.status'), value: status },
        { key: 'total', label: t('costing.evidence.total'), value: evidence.total === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(evidence.total)}</span> },
        ...(evidence.zeroNote === null ? [] : [{ key: 'zero', label: t('costing.field.zero_evidence'), value: evidence.zeroNote }]),
    ];

    return (
        <Section title={t('costing.evidence.title')}>
            <KeyValueList items={items} />
        </Section>
    );
}
