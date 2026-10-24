// The nonce of the content security policy (SECURITY_MODEL SEC-HDR-03): the
// server writes it on <meta name="csp-nonce">, and the styles that libraries
// inject at run time receive it — the scroll lock of dialogs and the drawer
// (react-remove-scroll, through get-nonce) and Inertia's progress indicator
// (the nonce option of createInertiaApp). The browser hides a nonce from the
// attribute after parsing, so it is read from the element's nonce property.
import { setNonce } from 'get-nonce';

export function readCspNonce(doc: Document): string | undefined {
    const meta = doc.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]');
    const nonce = meta?.nonce ?? '';

    return nonce === '' ? undefined : nonce;
}

/** Gives the nonce to the libraries that inject styles; returns it for Inertia. */
export function installCspNonce(doc: Document): string | undefined {
    const nonce = readCspNonce(doc);

    if (nonce !== undefined) {
        setNonce(nonce);
    }

    return nonce;
}
