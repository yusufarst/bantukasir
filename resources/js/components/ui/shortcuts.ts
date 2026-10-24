// The shortcut registry (DESIGN_SYSTEM section 11; ADAPTIVE_AND_ACCESSIBILITY
// UXA-09 and UXA-10). The shortcuts are provisional (D-075) and confirmed at
// gate DEVICES; later pages bind them through useShortcut() or a Button's
// `shortcut`, never with their own key listeners.
//
// - A binding belongs to the page, or to the open dialog or drawer that holds
//   its scope element: while a layer is open only the top layer's bindings
//   act, so Esc, F2 and F8 of the page wait (UXA-08, UXA-11).
// - Ctrl+Enter activates the visible, usable confirm binding of the top
//   scope. A press that is held or repeats is ignored, and after one
//   activation the next needs a new press — Enter or Control released first —
//   so one press never opens a review and confirms it (UXA-09).
// - Enter alone activates nothing here (UXA-10): see Button's `enterActivates`.

/** The shortcuts of DESIGN_SYSTEM section 11. */
export type ShortcutId = 'search' | 'pay' | 'method_cash' | 'method_transfer' | 'exact_cash' | 'confirm' | 'back';

interface ShortcutDefinition {
    /** The key as COPY_DECK section 5.1 writes it in an accessible name. */
    readonly keycap: string;
    /** The same key in the syntax of aria-keyshortcuts. */
    readonly aria: string;
    readonly matches: (event: KeyboardEvent) => boolean;
}

const plain = (event: KeyboardEvent): boolean => !event.ctrlKey && !event.altKey && !event.metaKey && !event.shiftKey;

export const SHORTCUTS: Readonly<Record<ShortcutId, ShortcutDefinition>> = {
    search: { keycap: 'F2', aria: 'F2', matches: (event) => event.key === 'F2' && plain(event) },
    pay: { keycap: 'F8', aria: 'F8', matches: (event) => event.key === 'F8' && plain(event) },
    method_cash: { keycap: 'Alt+1', aria: 'Alt+1', matches: (event) => event.altKey && !event.ctrlKey && !event.metaKey && (event.key === '1' || event.code === 'Digit1') },
    method_transfer: { keycap: 'Alt+2', aria: 'Alt+2', matches: (event) => event.altKey && !event.ctrlKey && !event.metaKey && (event.key === '2' || event.code === 'Digit2') },
    exact_cash: { keycap: 'Alt+U', aria: 'Alt+U', matches: (event) => event.altKey && !event.ctrlKey && !event.metaKey && (event.key.toLowerCase() === 'u' || event.code === 'KeyU') },
    confirm: { keycap: 'Ctrl+Enter', aria: 'Control+Enter', matches: (event) => event.key === 'Enter' && event.ctrlKey && !event.altKey && !event.metaKey },
    back: { keycap: 'Esc', aria: 'Escape', matches: (event) => event.key === 'Escape' && plain(event) },
};

/**
 * The accessible name of a control with a shortcut: its visible label, then
 * the shortcut in parentheses, "Bayar (F8)" (COPY_DECK CW-11 and 5.1).
 */
export function withShortcut(label: string, shortcut: ShortcutId | undefined): string {
    return shortcut === undefined ? label : `${label} (${SHORTCUTS[shortcut].keycap})`;
}

export interface ShortcutBinding {
    readonly id: ShortcutId;
    /** Runs the action; returns false when the control cannot act now. */
    readonly run: () => boolean;
    /** An element of the binding's scope: inside a dialog or drawer, or the page. */
    readonly scope: () => Element | null;
}

/** The open layers, dialogs and drawers, carry this attribute (Dialog.tsx, Drawer.tsx). */
export const LAYER_ATTRIBUTE = 'data-bk-layer';

const bindings: ShortcutBinding[] = [];
let armed = true;
let listening: Document | null = null;

function topLayer(doc: Document): Element | null {
    const layers = doc.querySelectorAll(`[${LAYER_ATTRIBUTE}]`);

    return layers.length === 0 ? null : (layers[layers.length - 1] ?? null);
}

function inScope(binding: ShortcutBinding, layer: Element | null): boolean {
    const element = binding.scope();

    if (element === null || !element.isConnected) {
        return false;
    }

    const owner = element.closest(`[${LAYER_ATTRIBUTE}]`);

    return layer === null ? owner === null : owner === layer;
}

function visible(element: Element | null): boolean {
    return element instanceof HTMLElement && element.offsetParent !== null;
}

/**
 * Marks a container whose text fields block Enter but never act on Ctrl+Enter:
 * the command form (CommandForm) and the quantity stepper (QuantityStepper).
 * An input inside such a container must never act on Ctrl+Enter, because the
 * registry takes Ctrl+Enter from it as the confirm shortcut even when its
 * default was prevented (BLOCKS_ENTER_ONLY).
 */
export const ENTER_BLOCKED_ATTRIBUTE = 'data-bk-enter-blocked';

/**
 * The controls that never act on Ctrl+Enter, though they may block it: a
 * radio item, whose Radix handler prevents the default of Enter, Ctrl+Enter
 * included, and acts on no Enter; a field of a command form, which prevents
 * every Enter, so that no Enter submits the form, and acts on none; and the
 * field of the quantity stepper, which acts on plain Enter only (it commits
 * the quantity). Ctrl+Enter still confirms from these when their default was
 * prevented (UXA-09). A control that acts on Enter, Ctrl+Enter included, and
 * then prevents its default — a menu button, which opens its menu, or a menu
 * item, which runs itself — has taken the key, and the registry yields to it,
 * so that one press does one thing (UXA-09).
 */
const BLOCKS_ENTER_ONLY = `[role="radio"], [${ENTER_BLOCKED_ATTRIBUTE}] input`;

function blocksEnterOnly(target: EventTarget | null): boolean {
    return target instanceof Element && target.matches(BLOCKS_ENTER_ONLY);
}

function onKeyDown(event: KeyboardEvent): void {
    if (event.isComposing) {
        return;
    }

    const id = (Object.keys(SHORTCUTS) as ShortcutId[]).find((candidate) => SHORTCUTS[candidate].matches(event));

    // A handler that took the key wins: every shortcut yields to it, except
    // Ctrl+Enter from a control that only blocks Enter (blocksEnterOnly).
    if (id === undefined || (event.defaultPrevented && !(id === 'confirm' && blocksEnterOnly(event.target)))) {
        return;
    }

    const doc = (event.target instanceof Node ? event.target.ownerDocument : null) ?? listening;

    if (doc === null) {
        return;
    }

    if (id === 'confirm' && (event.repeat || !armed)) {
        event.preventDefault();

        return;
    }

    if (id === 'back' && event.target instanceof Element && event.target.closest('textarea, select, input[type="search"]') !== null) {
        return;
    }

    const layer = topLayer(doc);
    // The latest binding of the top scope wins, and only a visible control acts.
    const binding = [...bindings].reverse().find((candidate) => candidate.id === id && inScope(candidate, layer) && visible(candidate.scope()));

    if (binding === undefined) {
        return;
    }

    if (binding.run()) {
        event.preventDefault();

        if (id === 'confirm') {
            armed = false;
        }
    }
}

function onKeyUp(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === 'Control') {
        armed = true;
    }
}

/** Adds a binding; returns the function that removes it. */
export function registerShortcut(binding: ShortcutBinding, doc: Document = document): () => void {
    if (listening !== doc) {
        if (listening !== null) {
            listening.removeEventListener('keydown', onKeyDown);
            listening.removeEventListener('keyup', onKeyUp);
        }

        doc.addEventListener('keydown', onKeyDown);
        doc.addEventListener('keyup', onKeyUp);
        listening = doc;
    }

    bindings.push(binding);

    return () => {
        const index = bindings.indexOf(binding);

        if (index !== -1) {
            bindings.splice(index, 1);
        }
    };
}

/** For tests: forgets every binding and the state of the repeat guard. */
export function resetShortcutsForTests(): void {
    bindings.length = 0;
    armed = true;
}
