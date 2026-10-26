import { useSyncExternalStore } from 'react';

// Whether the browser reports a network (UI_STATES UXS-20; TASK-006): pages show
// the no-connection notice while it reports none. A request that fails before
// it reaches the server is shown by the page itself.

function subscribe(onChange: () => void): () => void {
    window.addEventListener('online', onChange);
    window.addEventListener('offline', onChange);

    return () => {
        window.removeEventListener('online', onChange);
        window.removeEventListener('offline', onChange);
    };
}

export function useOnline(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );
}
