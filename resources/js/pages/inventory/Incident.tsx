import { router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { Dialog } from '@/components/ui/Dialog';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StatusWord } from '@/components/ui/Status';
import { Committed, StateNotice } from '@/components/ui/States';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { blockingRequest, committedText } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useUnresolved } from '@/lib/commands/unresolved';
import { useCommand } from '@/lib/commands/useCommand';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import { INCIDENTS_PAGE, INCIDENTS_SHELL, mismatchWord, REOPEN_COMMAND, statusLabel, type IncidentDetail } from '@/lib/inventory/incidents';
import { productHistory, REQUESTS_PAGE } from '@/lib/inventory/stock';

// UXP-415 "Detail insiden" (ROUTE_CONTRACTS RP-415 and RC-16; COPY_DECK section 9.16; INTERACTION_INVENTORY
// UXI-303 to UXI-305; NAVIGATION_CONTRACTS UXP-415; UI_STATES section 6; BR-INV-45, BR-INV-46; WF-27;
// TASK-036), the Owner's alone (CAP-18): when the reconciliation found it, what did not match — the label
// of the block's detail code (TASK-051 F-1) — and the blocked product, with "Riwayat stok" filtered to it (UXI-303). While the
// incident is open, "Buka kembali mutasi" (UXI-304) asks in its dialog, which Enter never confirms, that
// the facts were checked and reconciled first, and needs recent authentication (UXS-11); an unresolved
// reopening disables it with the way to UXP-006 (NC-13), and an uncertain one shows its panel. Once
// reopened, the page says so and shows the incident as reopened. "Kembali ke Insiden rekonsiliasi" (UXI-305).

const STALE_STATE: StateId = 'UXS-17';

const STALE_VERSION = 'STALE_VERSION';

interface IncidentProps {
    readonly incident: IncidentDetail;
}

export default function Incident(props: IncidentProps) {
    const title = t('inventory.incident_detail.title');

    return (
        <AppShell title={title} {...INCIDENTS_SHELL}>
            <IncidentView title={title} {...props} />
        </AppShell>
    );
}

/** Why "Buka kembali mutasi" waits for an unresolved request of its family, with the way to UXP-006 (NC-13; UXS-13). */
function unresolvedReason(): ReactNode {
    return (
        <>
            {t('ux.disabled.unresolved_family')} <a href={REQUESTS_PAGE}>{t('ux.action.open_unresolved')}</a>
        </>
    );
}

function IncidentView({ title, incident }: IncidentProps & { title: string }) {
    const [note, setNote] = useState<string | null>(null);
    const [stale, setStale] = useState(false);
    const [asking, setAsking] = useState(false);
    // A new key for each opening: its command starts again with a new intent.
    const [opening, setOpening] = useState(0);
    const unresolved = useUnresolved();
    const open = incident.status === 'BLOCKED';
    const reason = blockingRequest(unresolved.union, REOPEN_COMMAND.operation, REOPEN_COMMAND.family) === null ? undefined : unresolvedReason();

    // "Ditemukan", "Yang tidak cocok" — the label of the block's detail code, never the code (COPY_DECK section
    // 11.15.2; TASK-051 F-1, D-138) — and "Produk yang diblokir" (COPY_DECK section 9.16).
    const fields: readonly KeyValue[] = [
        { key: 'detected', label: t('inventory.incident.detected'), value: formatDateTime(incident.detectedAt) },
        { key: 'mismatch', label: t('inventory.incident.mismatch'), value: mismatchWord(incident.detailCode) },
        { key: 'product', label: t('inventory.incident.blocked_product'), value: `${incident.product.name} · ${incident.product.sku}` },
    ];

    const start = (): void => {
        setNote(null);
        setStale(false);
        setOpening((current) => current + 1);
        setAsking(true);
    };

    return (
        <PageContainer>
            <PageHeader title={title} subtitle={t('inventory.incident_detail.subtitle', { produk: incident.product.name })} back={{ href: INCIDENTS_PAGE, page: t('inventory.incidents.title') }} />
            <div className={'bk-stack'}>
                {note !== null && <Committed message={note} />}
                {stale && <StateNotice state={STALE_STATE} />}
                <Card>
                    <div className={'bk-stack'}>
                        <StatusWord tone={open ? 'danger' : 'success'} label={t(statusLabel(incident.status))} />
                        <KeyValueList items={fields} />
                        <div className={'bk-actions'}>
                            <ButtonLink href={productHistory(incident.product.id, 'incident')} label={t('inventory.history.title')} variant={'secondary'} />
                            {open && <Button label={t('inventory.incident.reopen')} variant={'primary'} enterActivates={false} disabledReason={reason} onClick={start} />}
                        </div>
                    </div>
                </Card>
                {asking && (
                    <ReopenRun
                        key={opening}
                        incident={incident}
                        onClose={() => setAsking(false)}
                        onDone={(text) => {
                            setAsking(false);
                            setNote(text);
                            router.reload();
                        }}
                        onStale={() => {
                            setAsking(false);
                            setStale(true);
                            router.reload();
                        }}
                    />
                )}
            </div>
        </PageContainer>
    );
}

/**
 * RC-16 from its dialog until the Owner confirms, then its panels — the recent-authentication dialog,
 * UXS-14 and UXS-15 with their actions, and a refusal's state. Its hook lives while it runs, so that a new
 * opening is a new intent (ADR-007 section 2). An incident reopened meanwhile (STALE_VERSION) reads the page
 * again (UXS-17).
 */
function ReopenRun({ incident, onClose, onDone, onStale }: { incident: IncidentDetail; onClose: () => void; onDone: (text: string) => void; onStale: () => void }) {
    const control = useCommand(REOPEN_COMMAND);
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
        // A request of the family that this run only adopted is another command's: the page says what is true of it.
        if (committed) {
            onDone(control.ownRequest ? t('inventory.incident.reopened') : committedText(control.target?.operation ?? ''));
        }
    }, [committed]);

    return (
        <>
            <Dialog
                open={asking}
                onOpenChange={(next) => (next ? undefined : onClose())}
                title={t('inventory.incident.reopen_confirm.title', { produk: incident.product.name })}
                description={<p>{t('inventory.incident.reopen_confirm.body')}</p>}
                dismissible={!busy}
                actions={
                    <>
                        <Button label={t('ux.action.cancel')} variant={'secondary'} unavailable={busy} data-initial-focus={true} onClick={onClose} />
                        <Button label={t('inventory.incident.reopen')} variant={'primary'} enterActivates={false} busy={busy} onClick={() => control.submit({ blockId: incident.id }, {})} />
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
