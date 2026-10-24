import { useEffect, useRef, type RefObject } from 'react';
import { registerShortcut, type ShortcutId } from './shortcuts';

/**
 * Binds a shortcut of the registry to an action while the component is
 * mounted and `enabled`. `scope` is an element of the binding's place — the
 * control itself, or a field — so that the registry knows whether it is in
 * the page or in an open dialog. `run` returns false when nothing was done.
 */
export function useShortcut(id: ShortcutId | undefined, scope: RefObject<Element | null>, run: () => boolean, enabled = true): void {
    const latest = useRef(run);

    useEffect(() => {
        latest.current = run;
    });

    useEffect(() => {
        if (id === undefined || !enabled) {
            return undefined;
        }

        return registerShortcut({ id, run: () => latest.current(), scope: () => scope.current });
    }, [id, enabled, scope]);
}
