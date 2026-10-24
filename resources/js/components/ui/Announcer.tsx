import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { t } from '@/lib/i18n';

type Announce = (text: string, assertive?: boolean) => void;

const AnnouncerContext = createContext<Announce | null>(null);

/**
 * The two live regions of UXA-17, above every layer (z-index 110): polite
 * announcements wait for the reader, assertive ones interrupt. The shell
 * places one provider around the page (TASK-007).
 */
export function AnnouncerProvider({ children }: { children: ReactNode }) {
    const [polite, setPolite] = useState('');
    const [assertive, setAssertive] = useState('');
    // One timer per region, so that a polite and an assertive announcement made
    // close together both reach their region.
    const timers = useRef<{ polite: ReturnType<typeof setTimeout> | null; assertive: ReturnType<typeof setTimeout> | null }>({ polite: null, assertive: null });

    useEffect(
        () => () => {
            for (const timer of Object.values(timers.current)) {
                if (timer !== null) {
                    clearTimeout(timer);
                }
            }
        },
        [],
    );

    const announce = useCallback<Announce>((text, urgent = false) => {
        const set = urgent ? setAssertive : setPolite;
        const region = urgent ? 'assertive' : 'polite';
        const pending = timers.current[region];

        // Cleared first, so that the same text is announced again.
        set('');

        if (pending !== null) {
            clearTimeout(pending);
        }

        timers.current[region] = setTimeout(() => set(text), 60);
    }, []);

    return (
        <AnnouncerContext.Provider value={announce}>
            {children}
            <div className={'bk-live'} aria-live={'polite'} data-testid={'live-polite'}>
                {polite}
            </div>
            <div className={'bk-live'} aria-live={'assertive'} data-testid={'live-assertive'}>
                {assertive}
            </div>
        </AnnouncerContext.Provider>
    );
}

/** The announcer of the page; it is a defect to announce outside the provider. */
export function useAnnounce(): Announce {
    const announce = useContext(AnnouncerContext);

    if (announce === null) {
        throw new Error('Announcements need the AnnouncerProvider of the page (UXA-17).');
    }

    return announce;
}

/** The announcements of COPY_DECK section 18, already worded. */
export const announcements = {
    added: (product: string, quantity: string): string => t('ux.announce.added', { produk: product, jumlah: quantity }),
    unknownCode: (code: string): string => t('ux.announce.unknown_code', { kode: code }),
    loaded: (): string => t('ux.announce.loaded'),
    saved: (message: string): string => t('ux.announce.saved', { pesan: message }),
    refused: (message: string): string => t('ux.announce.refused', { pesan: message }),
    uncertain: (message: string): string => t('ux.announce.uncertain', { pesan: message }),
    sessionWarning: (duration: string): string => t('ux.announce.session_warning', { durasi: duration }),
} as const;
