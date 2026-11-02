import { useState } from 'react';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Committed } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { takeSavedNote } from '@/lib/access/users';
import { formatDateTime, formatQuantity } from '@/lib/format';
import { t } from '@/lib/i18n';
import { ISSUES_PAGE, ISSUES_SHELL, movementPage, openerOf, reasonWord, REQUESTS_PAGE } from '@/lib/inventory/stock';

// UXP-409 "Detail barang keluar" (ROUTE_CONTRACTS RP-409; COPY_DECK section 9.10; INTERACTION_INVENTORY
// UXI-265 and UXI-267; NAVIGATION_CONTRACTS UXP-409; UI_STATES section 6; BR-INV-18 to BR-INV-22;
// CAP-13; TASK-023 scope item 4): one confirmed manual issue — its time, reason, note and who
// confirmed it — and its lines, each with its quantity and its movement (UXI-265). No cost is
// shown: an allocation is the Owner's figure of the reports (ADR-008 section 6). "Batalkan mutasi"
// (UXI-266) is TASK-026's. "Kembali ke …" names the page that opened it when that is UXP-403 or
// UXP-006, otherwise UXP-407 with its filters; after the success of UXP-408, UXP-407 (UXI-267).

interface ManualIssue {
    readonly id: number;
    readonly confirmedAt: string;
    readonly reasonCode: string;
    readonly note: string | null;
    readonly lineCount: number;
    readonly actorDisplayName: string;
}

interface Line {
    readonly lineNo: number;
    readonly product: { readonly id: number; readonly name: string; readonly sku: string };
    readonly movementId: number;
    readonly quantity: string;
    readonly baseUnit: string;
    readonly quantityPrecision: number;
}

export default function Issue({ issue, lines }: { issue: ManualIssue; lines: readonly Line[] }) {
    const title = t('inventory.issue_detail.title');

    return (
        <AppShell title={title} {...ISSUES_SHELL}>
            <IssuePage title={title} issue={issue} lines={lines} />
        </AppShell>
    );
}

/** The name of a line's link to its movement (UXI-265), which names the line's product. */
function movementLinkName(line: Line): string {
    return `${t('inventory.movement.title')} ${line.product.name}`;
}

/** The way back of UXI-267. */
function backOf(): { readonly href: string; readonly page: string } {
    const opener = openerOf(document.referrer, window.location.origin);
    const path = opener?.pathname ?? '';

    if (opener !== null && /^\/stock\/movements\/[0-9]+$/.test(path)) {
        return { href: path, page: t('inventory.movement.title') };
    }

    if (opener !== null && path === REQUESTS_PAGE) {
        return { href: REQUESTS_PAGE, page: t('commands.unresolved.title') };
    }

    return { href: opener !== null && path === ISSUES_PAGE ? `${path}${opener.search}` : ISSUES_PAGE, page: t('inventory.issues.title') };
}

function IssuePage({ title, issue, lines }: { title: string; issue: ManualIssue; lines: readonly Line[] }) {
    const [back] = useState(backOf);
    const [note] = useState(takeSavedNote);

    const items: KeyValue[] = [
        { key: 'time', label: t('inventory.issues.col_time'), value: formatDateTime(issue.confirmedAt) },
        { key: 'reason', label: t('inventory.issues.col_reason'), value: reasonWord(issue.reasonCode) },
        ...(issue.note === null ? [] : [{ key: 'note', label: t('inventory.issue_new.note'), value: issue.note }]),
        { key: 'actor', label: t('inventory.issues.col_actor'), value: issue.actorDisplayName },
    ];

    const columns: readonly Column<Line>[] = [
        { id: 'product', header: t('inventory.line.product'), isHead: true, cell: (line) => line.product.name },
        { id: 'quantity', header: t('inventory.line.quantity'), numeric: true, cell: (line) => formatQuantity(line.quantity, line.quantityPrecision, line.baseUnit) },
        {
            id: 'movement',
            header: t('inventory.movement.title'),
            isAction: true,
            cell: (line) => (
                <a className={'bk-table-link'} href={movementPage(line.movementId)} aria-label={movementLinkName(line)}>
                    {t('inventory.movement.title')}
                </a>
            ),
        },
    ];

    return (
        <PageContainer>
            <PageHeader title={title} subtitle={t('inventory.issue_detail.subtitle', { waktu: formatDateTime(issue.confirmedAt) })} back={back} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                <Section title={title} titleHidden={true}>
                    <KeyValueList items={items} />
                </Section>
                <DataTable caption={title} columns={columns} rows={lines} rowKey={(line) => String(line.lineNo)} />
            </div>
        </PageContainer>
    );
}
