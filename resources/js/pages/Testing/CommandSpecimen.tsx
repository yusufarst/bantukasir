import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { CommandForm } from '@/components/ui/Field';
import { AppShell } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { familyLabel } from '@/lib/commands/families';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { t } from '@/lib/i18n';

// The command client's specimen (TASK-009 scope item 8): a signed-in page, only
// in the local and testing environments, whose one command is the probe
// operation of the test harness (`testing.probe`, in the family of the
// Owner's review note; tests/Harness/Commands/Probe.php). The browser suite
// proves on it, against the real pipeline, every client state with the
// faults of TEST_STRATEGY TS-22, the block of NC-13 across devices (TS-23)
// and the recovery case of the L5 critical paths (EXECUTION_CONTEXT EXC-34).
// Its final action "Simpan" sends a fixed synthetic note; a committed probe
// leads to its result page, as a command's success does (NC-08).

const PROBE = { operation: 'testing.probe', family: 'review_note' } as const;

/** The synthetic payload of the probe: a note that names nothing. */
const PAYLOAD = { note: 'Catatan uji' };

export default function CommandSpecimen() {
    const title = familyLabel(PROBE.family);

    return (
        <AppShell title={title}>
            <ProbeCommand title={title} />
        </AppShell>
    );
}

function ProbeCommand({ title }: { title: string }) {
    const control = useCommand(PROBE);
    const { phase, blockedBy } = control;
    const location = phase.kind === 'committed' ? phase.result?.location : undefined;

    useEffect(() => {
        if (location !== undefined) {
            router.visit(location);
        }
    }, [location]);

    const blocked = blockedBy !== null && phase.kind !== 'submitting';
    const reason = blocked ? (
        <>
            {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;

    return (
        <CommandLayout header={<PageHeader title={title} />}>
            <Card>
                <CommandForm>
                    <div className={'bk-actions'}>
                        <Button
                            label={t('ux.action.save')}
                            size={'lg'}
                            enterActivates={false}
                            busy={phase.kind === 'submitting'}
                            disabledReason={reason}
                            onClick={() => control.submit(PAYLOAD)}
                        />
                        {(phase.kind === 'rejected' || phase.kind === 'not_committed') && (
                            <Button label={t('ux.action.edit_again')} variant={'secondary'} onClick={control.startAgain} />
                        )}
                    </div>
                </CommandForm>
            </Card>
            <CommandPanels control={control} />
        </CommandLayout>
    );
}
