import { useSyncExternalStore } from 'react';

// The width classes of UXA-02 (DESIGN_SYSTEM 7.1, DV-01): compact below 600
// CSS px, medium from 600 to 1023, expanded from 1024. The values are
// provisional (D-075) and confirmed at gate DEVICES. Layout follows the width;
// input behaviour follows the pointer, never the width alone.

export type WidthClass = 'compact' | 'medium' | 'expanded';

export const MEDIUM_FROM = 600;
export const EXPANDED_FROM = 1024;

const MEDIUM_QUERY = `(min-width: ${MEDIUM_FROM}px)`;
const EXPANDED_QUERY = `(min-width: ${EXPANDED_FROM}px)`;
const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';

export function widthClassOf(width: number): WidthClass {
    return width >= EXPANDED_FROM ? 'expanded' : width >= MEDIUM_FROM ? 'medium' : 'compact';
}

function subscribe(queries: readonly string[]) {
    return (onChange: () => void): (() => void) => {
        const lists = queries.map((query) => window.matchMedia(query));

        lists.forEach((list) => list.addEventListener('change', onChange));

        return () => lists.forEach((list) => list.removeEventListener('change', onChange));
    };
}

const subscribeWidth = subscribe([MEDIUM_QUERY, EXPANDED_QUERY]);
const subscribePointer = subscribe([FINE_POINTER_QUERY]);

/** The current width class of the viewport. */
export function useWidthClass(): WidthClass {
    return useSyncExternalStore(subscribeWidth, () =>
        window.matchMedia(EXPANDED_QUERY).matches ? 'expanded' : window.matchMedia(MEDIUM_QUERY).matches ? 'medium' : 'compact',
    );
}

/** Whether the pointer is fine and can hover, as on the PC kasir (UXA-01, UXA-24). */
export function useFinePointer(): boolean {
    return useSyncExternalStore(subscribePointer, () => window.matchMedia(FINE_POINTER_QUERY).matches);
}
