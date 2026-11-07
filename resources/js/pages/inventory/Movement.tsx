import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Committed, StateMark } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { CORRECT_CAPABILITY } from '@/lib/costing/costs';
import { CorrectionButton } from '@/lib/costing/Parts';
import { formatCost, formatCount, formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { ReversalButton } from '@/lib/inventory/Reversal';
import {
    HISTORY_PAGE,
    HISTORY_SHELL,
    ISSUES_PAGE,
    movementPage,
    openerOf,
    reasonWord,
    RECEIVINGS_PAGE,
    REQUESTS_PAGE,
    REVERSE_CAPABILITY,
    REVERSIBLE_TYPES,
    signedQuantity,
    sourceLink,
    typeWord,
    type MovementSource,
    type MovementType,
} from '@/lib/inventory/stock';

// UXP-403 "Detail mutasi stok" (ROUTE_CONTRACTS RP-403; COPY_DECK section 9.4; INTERACTION_INVENTORY
// UXI-224, UXI-225 and UXI-229; NAVIGATION_CONTRACTS UXP-403; UI_STATES section 6; BR-INV-02,
// BR-INV-44; CAP-14, CAP-20; TASK-022 scope item 2): one movement with its type, product,
// signed quantity, time, who recorded it, its reference, its reason and its source, a receiving
// document or a manual issue (TASK-023), as a link (UXI-224); for the Owner alone its cost
// evidence (UXI-225): the status, the total or
// "Biaya perlu dilengkapi pemilik", never Rp0 for UNKNOWN (UXS-24), and a zero's evidence or
// reason, as it is after the Owner's corrections, with how many there were, and "Koreksi biaya" or
// "Lengkapi biaya" (UXI-226; TASK-025), disabled with its reason while a cost correction of the
// user is uncertain (UXS-13; NC-13). "Kembali ke …" names the page that opened it, UXP-402 by
// default (UXI-229). Its reversal links: the REVERSAL that cancelled it, or the movement a REVERSAL
// cancels (COPY_DECK section 9.4); and for the Owner "Batalkan mutasi" (UXI-227; TASK-026) for an
// OPENING, a RECEIPT or a MANUAL_ISSUE, disabled with its reason when it is reversed, while a
// reversal of the user is uncertain, or for a RECEIPT or a MANUAL_ISSUE while the onboarding freeze
// is on. UXI-228, its OPENING during onboarding followed by the replacement, comes with TASK-035.

/** UXP-504 returns here. */
const OPENER = 'movement';

/** The signed-in user that every page shares (TASK-007). */
interface SharedProps {
    readonly auth: { readonly user: { readonly capabilities: readonly string[] } };
    readonly [key: string]: unknown;
}

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
    /** The REVERSAL that cancelled this movement (TASK-026). */
    readonly reversedBy: MovementLink | null;
    /** The movement that this REVERSAL cancels (TASK-026). */
    readonly reverses: MovementLink | null;
}

interface MovementLink {
    readonly id: number;
    readonly at: string;
}

interface CostEvidence {
    readonly evidenceId: number;
    readonly status: 'KNOWN' | 'UNKNOWN';
    readonly total: string | null;
    readonly zeroNote: string | null;
    /** How many corrections the Owner made (TASK-025). */
    readonly corrections: number;
}

export default function Movement({ movement, costEvidence, postingFrozen }: { movement: Movement; costEvidence?: CostEvidence | null; postingFrozen?: boolean }) {
    const title = t('inventory.movement.title');

    return (
        <AppShell title={title} {...HISTORY_SHELL}>
            <MovementPage title={title} movement={movement} costEvidence={costEvidence} postingFrozen={postingFrozen ?? false} />
        </AppShell>
    );
}

/** A link to the other movement of a reversal pair, with its words (COPY_DECK section 9.4). */
function pairLink(link: MovementLink, text: string) {
    return (
        <a className={'bk-table-link'} href={movementPage(link.id)}>
            {text}
        </a>
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

function MovementPage({ title, movement, costEvidence, postingFrozen }: { title: string; movement: Movement; costEvidence: CostEvidence | null | undefined; postingFrozen: boolean }) {
    const [back] = useState(backOf);
    // "Biaya disimpan. Laporan sudah dihitung ulang." after the success of UXP-504, and "Mutasi dibatalkan." after UXP-411, which lead here (UXS-04).
    const [note] = useState(takeSavedNote);
    const capabilities = usePage<SharedProps>().props.auth.user.capabilities;
    const corrects = capabilities.includes(CORRECT_CAPABILITY);
    // UXI-227, the Owner's: shown for an OPENING, a RECEIPT or a MANUAL_ISSUE only (BR-INV-36).
    const reverses = capabilities.includes(REVERSE_CAPABILITY) && REVERSIBLE_TYPES.includes(movement.type);
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
        ...(movement.reverses === null ? [] : [{ key: 'reverses', label: t('inventory.movement.source'), value: pairLink(movement.reverses, t('inventory.movement.reverses', { waktu: formatDateTime(movement.reverses.at) })) }]),
        ...(movement.reversedBy === null ? [] : [{ key: 'reversed', label: t('inventory.type.reversal'), value: pairLink(movement.reversedBy, t('inventory.movement.reversed_by', { waktu: formatDateTime(movement.reversedBy.at) })) }]),
    ];

    return (
        <PageContainer width={'form'}>
            <PageHeader title={title} subtitle={t('inventory.movement.subtitle', { jenis: typeWord(movement.type), produk: movement.product.name })} back={back} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                <Section title={title} titleHidden={true}>
                    <div className={'bk-stack'}>
                        <KeyValueList items={items} />
                        {reverses && (
                            <div className={'bk-actions'}>
                                <ReversalButton movementId={movement.id} type={movement.type} reversed={movement.reversedBy !== null} frozen={postingFrozen} opener={OPENER} />
                            </div>
                        )}
                    </div>
                </Section>
                {costEvidence !== undefined && costEvidence !== null && <EvidenceSection evidence={costEvidence} corrects={corrects} />}
            </div>
        </PageContainer>
    );
}

/**
 * UXI-225, the Owner's: the status, the total, and a zero's evidence or reason, as they are after the
 * Owner's corrections, with how many there were (BR-COST-02 to BR-COST-04, BR-COST-20); and UXI-226,
 * "Koreksi biaya" or "Lengkapi biaya" (CAP-19).
 */
function EvidenceSection({ evidence, corrects }: { evidence: CostEvidence; corrects: boolean }) {
    const zero = evidence.status === 'KNOWN' && evidence.total !== null && /^0(\.0+)?$/.test(evidence.total);
    const status = evidence.status === 'UNKNOWN' ? t('costing.status.unknown') : zero ? t('costing.status.zero') : t('costing.status.known');
    const items: KeyValue[] = [
        { key: 'status', label: t('catalog.field.status'), value: status },
        { key: 'total', label: t('costing.evidence.total'), value: evidence.total === null ? <StateMark kind={'cost_unknown'} /> : <span className={'bk-num'}>{formatCost(evidence.total)}</span> },
        ...(evidence.zeroNote === null ? [] : [{ key: 'zero', label: t('costing.field.zero_evidence'), value: evidence.zeroNote }]),
        ...(evidence.corrections === 0 ? [] : [{ key: 'corrections', label: t('costing.evidence.corrections'), value: <span className={'bk-num'}>{formatCount(evidence.corrections)}</span> }]),
    ];

    return (
        <Section title={t('costing.evidence.title')}>
            <div className={'bk-stack'}>
                <KeyValueList items={items} />
                {corrects && (
                    <div className={'bk-actions'}>
                        <CorrectionButton evidenceId={evidence.evidenceId} cost={{ status: evidence.status, amount: evidence.total }} opener={OPENER} />
                    </div>
                )}
            </div>
        </Section>
    );
}
