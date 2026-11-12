import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button, type ButtonVariant } from '@/components/ui/Button';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StateNotice } from '@/components/ui/States';
import { useCommand } from '@/lib/commands/useCommand';
import { useExportRequest, type PrintCommand } from '@/lib/documents/requests';
import { t } from '@/lib/i18n';

// The print and export controls of the documents (UXI-090, UXI-117, UXI-141, UXI-142; INTERACTION_CONTRACTS,
// "RC-44 to RC-47"; UI_STATES UXS-06, UXS-44; UXA-10; TASK-031 scope items 2, 3 and 5). Enter never prints
// or exports. A print records its request (RC-44 or RC-45), then the print view opens in its own tab:
// the press opens the tab, the only moment a browser allows it; it gets the view's address once the
// request committed and closes if it did not; without a tab the view takes this one. Each print is a new
// request, and the control starts again after each, so that the page stays for another print. Print and
// export are exempt from the block of NC-13 and have no uncertain panel: a failure is UXS-44, asked again
// by "Coba lagi" — the same request after an uncertain answer, which answers the same print request.

const FAILED_STATE: StateId = 'UXS-44';

interface PrintControlProps {
    readonly command: PrintCommand;
    readonly payload: Readonly<Record<string, unknown>>;
    readonly label: string;
    readonly variant: ButtonVariant;
    /** The id of the hint that the button names, "Pastikan struk benar-benar tercetak." (UXI-090). */
    readonly describedBy?: string;
}

export function PrintControl(props: PrintControlProps) {
    const [run, setRun] = useState(0);

    return <PrintRun key={run} {...props} onPrinted={() => setRun((current) => current + 1)} />;
}

function PrintRun({ command, payload, label, variant, describedBy, onPrinted }: PrintControlProps & { readonly onPrinted: () => void }) {
    const control = useCommand(command);
    const { phase } = control;
    const tab = useRef<Window | null>(null);
    const busy = phase.kind === 'submitting';
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;
    const failed = phase.kind !== 'idle' && phase.kind !== 'submitting' && phase.kind !== 'committed';

    useEffect(() => {
        if (!busy && !committed && tab.current !== null) {
            tab.current.close();
            tab.current = null;
        }
    }, [busy, committed]);

    useEffect(() => {
        if (!committed) {
            return;
        }

        if (location === undefined) {
            tab.current?.close();
        } else if (tab.current === null) {
            router.visit(location);
        } else {
            tab.current.location.href = location;
        }

        tab.current = null;
        onPrinted();
    }, [committed]);

    const open = (): void => {
        tab.current = window.open('', '_blank');
    };

    const print = (): void => {
        if (phase.kind === 'idle') {
            open();
            control.submit(payload);
        }
    };

    // The same request after an uncertain or throttled answer; a held one is an earlier epoch's or
    // another's and a page that must be read again, so the page is read again; after any other failure
    // the control starts again with a new request.
    const retry = (): void => {
        if (phase.kind === 'uncertain' || phase.kind === 'throttled') {
            open();
            control.resend();
        } else if (phase.kind === 'held' || phase.kind === 'reload' || phase.kind === 'recent_auth') {
            window.location.reload();
        } else {
            onPrinted();
        }
    };

    return (
        <>
            <Button label={label} variant={variant} icon={'printer'} enterActivates={false} busy={busy} describedBy={describedBy} onClick={failed ? retry : print} />
            {failed && <StateNotice state={FAILED_STATE} actions={<Button label={t('ux.action.retry')} variant={'secondary'} enterActivates={false} onClick={retry} />} />}
        </>
    );
}

/** UXI-142 "Unduh CSV": RC-46, whose answer is the file (DOCUMENT_CONTRACTS DC-03). */
export function ExportControl({ sessionId, number }: { readonly sessionId: number; readonly number: string }) {
    const request = useExportRequest(`laporan-sesi-${number.replace(/[^A-Za-z0-9-]/g, '-')}.csv`);

    return (
        <>
            <Button label={t('cash.session_detail.download_csv')} variant={'secondary'} enterActivates={false} busy={request.busy} onClick={() => request.download({ sessionId })} />
            {request.failed && <StateNotice state={FAILED_STATE} actions={<Button label={t('ux.action.retry')} variant={'secondary'} enterActivates={false} onClick={() => request.download({ sessionId })} />} />}
        </>
    );
}
