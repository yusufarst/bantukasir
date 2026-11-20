import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Section } from '@/components/ui/Card';
import { KeyValueList, type KeyValue } from '@/components/ui/Data';
import { LoadFailed, Loading, StateMark } from '@/components/ui/States';
import { StatusWord } from '@/components/ui/Status';
import { AppShell } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import { INCIDENTS_PAGE, itemAge, itemValue, ITEMS, SETTINGS_PAGE, STATUS_PROP, type ItemId, type StatusItem, type SystemStatusRead } from '@/lib/operations/status';

// UXP-611 "Status sistem" (ROUTE_CONTRACTS RP-611; COPY_DECK section 11.11; INTERACTION_INVENTORY UXI-374 to
// UXI-376; NAVIGATION_CONTRACTS UXP-611; UI_STATES section 6: UXS-01, UXS-22, UXS-43; OPERATIONS_RUNBOOK
// section 5; REQ-OPS-04; TASK-039 scope item 2), the Owner's alone (CAP-32): the five items read in one
// request, stacked, each with its value as the server judged it. An item the server could not prove shows
// "Belum Terverifikasi" with no passing look, and a stale success only its age. "Muat ulang" (UXI-374)
// reads the status again by name: while it reads, the items wait (UXS-01); a read that fails, or that the
// server could not make, is UXS-22 with "Coba lagi", never the earlier items. "Insiden rekonsiliasi"
// (UXI-375) leads to UXP-414 while an incident is open. The way back is "Kembali ke Pengaturan" (UXI-376).

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

type Read = 'read' | 'reading' | 'failed';

export default function Status({ status }: { status: SystemStatusRead | null }) {
    const title = t('config.system_status.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <StatusPage title={title} status={status} />
        </AppShell>
    );
}

function StatusPage({ title, status }: { title: string; status: SystemStatusRead | null }) {
    const [read, setRead] = useState<Read>('read');

    const reload = useCallback((): void => {
        const failed = (): boolean => {
            setRead('failed');

            return false;
        };

        setRead('reading');
        router.reload({ only: [STATUS_PROP], onSuccess: () => setRead('read'), onHttpException: failed, onNetworkError: failed });
    }, []);

    const shown = read === 'read' ? status : null;

    return (
        <PageContainer width={'form'}>
            <PageHeader
                title={title}
                back={{ href: SETTINGS_PAGE, page: t('config.settings.title') }}
                actions={<Button label={t('ux.action.reload')} variant={'secondary'} icon={'refresh'} busy={read === 'reading'} onClick={reload} />}
            />
            <Section title={title} titleHidden={true}>
                {read === 'reading' && <Loading lines={5} />}
                {read !== 'reading' && shown === null && <LoadFailed onRetry={reload} />}
                {shown !== null && <KeyValueList items={ITEMS.map((entry): KeyValue => ({ key: entry.id, label: t(entry.label), value: <ItemValue id={entry.id} item={shown[entry.id]} incidentsOpen={shown.incidentsOpen} /> }))} />}
            </Section>
        </PageContainer>
    );
}

function ItemValue({ id, item, incidentsOpen }: { id: ItemId; item: StatusItem; incidentsOpen: boolean }) {
    const value = item.state === 'UNVERIFIED' ? null : itemValue(id, item);
    const age = itemAge(item);

    return (
        <div className={'bk-stack'} data-item={id} data-state={value === null ? 'UNVERIFIED' : item.state}>
            {value === null ? <StateMark kind={'unverified'} /> : <StatusWord tone={item.state === 'OK' ? 'success' : 'danger'} label={value} />}
            {age !== null && item.state !== 'FAILED' && <span className={'bk-meta'}>{age}</span>}
            {id === 'reconciliation' && incidentsOpen && (
                <div className={'bk-actions'}>
                    <ButtonLink href={INCIDENTS_PAGE} label={t('inventory.incidents.title')} variant={'secondary'} />
                </div>
            )}
        </div>
    );
}
