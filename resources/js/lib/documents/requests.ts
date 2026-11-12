import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CLIENT_WAIT_MS, countsAsActivity, envelopeOf, freeze, USER_REQUEST_EVENT, xsrfToken, type FrozenRequest } from '@/lib/commands/client';

// The print and export requests of the documents (INTERACTION_CONTRACTS, "RC-44 to RC-47"; ROUTE_CONTRACTS
// RC-44 to RC-46, RP-103P, RP-206P; DOCUMENT_CONTRACTS DC-01 to DC-03; UI_STATES UXS-44; TASK-031 scope
// items 2, 3 and 5). A print request is recorded first, then the print view opens in its own tab, which
// the press opens — the only moment a browser allows it — and which gets its address once the request
// committed, closes if it did not, and without which the view takes this tab; the page stays for
// another print. An export's answer is the file itself, which the browser saves. Print and export are
// exempt from the block of NC-13 and have no uncertain panel: a failure is UXS-44 and is asked again, a
// repeat of the same request answering the same print request or the same file (BR-CASH-22).

/** RC-44, RC-45 and RC-46 with their family (INTERACTION_CONTRACTS section 1.4). */
export const PRINT_RECEIPT_COMMAND = { operation: 'document.print_receipt', family: 'print' } as const;

export const PRINT_SESSION_REPORT_COMMAND = { operation: 'document.print_session_report', family: 'print' } as const;

export const EXPORT_SESSION_REPORT_COMMAND = { operation: 'document.export_session_report', family: 'print' } as const;

/** The state of an export request (UXI-142). */
export interface ExportRequest {
    readonly busy: boolean;
    readonly failed: boolean;
    /** Asks for the file: a new request, or after a failure the same one again, which returns the same file. */
    readonly download: (payload: Readonly<Record<string, unknown>>) => void;
}

interface SharedProps {
    readonly recoveryEpoch: string | null;
    readonly [key: string]: unknown;
}

/** The file name of an attachment's Content-Disposition, or the fallback. */
export function attachmentName(disposition: string | null, fallback: string): string {
    const match = disposition === null ? null : /filename="?([^";]+)"?/.exec(disposition);

    return match?.[1] ?? fallback;
}

/**
 * An export request of RC-46 (UXI-142): the CSV of DC-03 is the answer's body, saved by the browser
 * under the name the server gives. A failed request keeps its frozen request, so that "Unduh CSV"
 * asks for the same file again (BR-CASH-22); a successful one starts the next press anew.
 */
export function useExportRequest(fallbackName: string): ExportRequest {
    const epoch = usePage<SharedProps>().props.recoveryEpoch;
    const [busy, setBusy] = useState(false);
    const [failed, setFailed] = useState(false);
    const frozen = useRef<FrozenRequest | null>(null);
    const live = useRef(true);

    useEffect(() => {
        live.current = true;

        return () => {
            live.current = false;
        };
    }, []);

    const download = (payload: Readonly<Record<string, unknown>>): void => {
        if (busy || epoch === null) {
            return;
        }

        const request = frozen.current ?? freeze(EXPORT_SESSION_REPORT_COMMAND, payload, {}, epoch);
        frozen.current = request;
        setBusy(true);
        setFailed(false);

        void requestFile(request).then((file) => {
            if (!live.current) {
                return;
            }

            setBusy(false);

            if (file === null) {
                setFailed(true);

                return;
            }

            frozen.current = null;
            save(file.blob, attachmentName(file.disposition, fallbackName));
        });
    };

    return { busy, failed, download };
}

/** Sends the frozen export and reads its file, or null when no file came back within the wait. */
async function requestFile(frozen: FrozenRequest): Promise<{ blob: Blob; disposition: string | null } | null> {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), CLIENT_WAIT_MS);

    try {
        const answer = await fetch(`/commands/${frozen.operation}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'text/csv, application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify(envelopeOf(frozen)),
            signal: controller.signal,
        });

        if (countsAsActivity(answer.status)) {
            window.dispatchEvent(new Event(USER_REQUEST_EVENT));
        }

        const csv = (answer.headers.get('Content-Type') ?? '').startsWith('text/csv');

        return (answer.status === 200 || answer.status === 201) && csv ? { blob: await answer.blob(), disposition: answer.headers.get('Content-Disposition') } : null;
    } catch {
        return null;
    } finally {
        window.clearTimeout(timer);
    }
}

/** Saves a file through a link of its own, which the browser follows as a download. */
function save(blob: Blob, name: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
}
