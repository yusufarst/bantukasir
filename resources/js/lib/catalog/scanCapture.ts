import { useEffect, type RefObject } from 'react';
import { LAYER_ATTRIBUTE } from '@/components/ui/shortcuts';

// Scan capture in the catalogue's contexts (ADAPTIVE_AND_ACCESSIBILITY UXA-11;
// BR-CAT-28, BR-CAT-32; TASK-017 scope item 2): a scanner is a keyboard, and no timing
// decides whether input is a barcode. A key that would type text while no field holds
// the focus moves the focus to the page's scan field, which then receives it; a key
// typed in another field stays ordinary typing there. Capture pauses while a dialog or
// a drawer is open and while a menu holds the focus, and keystrokes then go only to the
// focused control.

/** What holds the focus when a key arrives. */
export type FocusKind = 'field' | 'menu' | 'other';

/** Whether a key moves the focus to the scan field. */
export function shouldCapture(event: { readonly key: string; readonly ctrlKey: boolean; readonly metaKey: boolean; readonly altKey: boolean }, focus: FocusKind, layerOpen: boolean): boolean {
    return !layerOpen && focus === 'other' && event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey;
}

function focusKindOf(element: Element | null): FocusKind {
    if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement || (element instanceof HTMLElement && element.isContentEditable)) {
        return 'field';
    }

    return element?.closest('[role="menu"]') === null || element === null ? 'other' : 'menu';
}

/** While `enabled`, the page's text keys reach this field, as UXA-11 says. */
export function useScanCapture(field: RefObject<HTMLInputElement | null>, enabled = true): void {
    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        const onKeyDown = (event: KeyboardEvent): void => {
            const layerOpen = document.querySelector(`[${LAYER_ATTRIBUTE}]`) !== null;

            if (shouldCapture(event, focusKindOf(document.activeElement), layerOpen)) {
                field.current?.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown, true);

        return () => document.removeEventListener('keydown', onKeyDown, true);
    }, [field, enabled]);
}
