import { useId, useRef, type KeyboardEvent, type ReactNode, type Ref } from 'react';
import { Button } from './Button';
import { Field, TextInput } from './Field';
import { Icon } from './Icon';
import { ENTER_BLOCKED_ATTRIBUTE, SHORTCUTS, withShortcut } from './shortcuts';
import { useShortcut } from './useShortcut';

// The till workspace primitives of DESIGN_SYSTEM 8.14, which TASK-029
// assembles into UXP-101: search with scan capture and F2, results with stock
// and price, cart lines with a quantity stepper and remove, and the total
// with "Bayar" (F8) fixed. The cart is a labelled region that scrolls inside
// its own area (UXA-13, UXA-15); below 760 px of container width it follows
// the results and the total with "Bayar" is fixed at the bottom (UXA-25).

export function TillWorkspace({ work, cart }: { work: ReactNode; cart: ReactNode }) {
    return (
        <div className={'bk-till-host'}>
            <div className={'bk-till'}>
                <div className={'bk-till__work'}>{work}</div>
                {cart}
            </div>
        </div>
    );
}

/**
 * The search field that is a scan target (UXA-11): a scan is ordinary
 * keyboard input that ends with Enter, which runs the lookup and submits
 * nothing (UXA-10); an empty terminator and a repeated Enter of a held key
 * run nothing (BR-CAT-29); F2 focuses and selects it (UXA-09); the down arrow
 * moves to the first result. The page gives it the focus on arrival and
 * after every cart change.
 */
export function ScanField({
    label,
    hint,
    value,
    onValueChange,
    onLookup,
    onArrowDown,
    error,
    inputRef,
}: {
    label: string;
    hint?: string;
    value: string;
    onValueChange: (value: string) => void;
    onLookup: (code: string) => void;
    onArrowDown?: () => void;
    error?: ReactNode;
    inputRef?: Ref<HTMLInputElement>;
}) {
    const own = useRef<HTMLInputElement | null>(null);

    useShortcut('search', own, () => {
        if (own.current === null) {
            return false;
        }

        own.current.focus();
        own.current.select();

        return true;
    });

    const setRef = (element: HTMLInputElement | null): void => {
        own.current = element;

        if (typeof inputRef === 'function') {
            inputRef(element);
        } else if (inputRef !== null && inputRef !== undefined) {
            inputRef.current = element;
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'ArrowDown' && onArrowDown !== undefined) {
            event.preventDefault();
            onArrowDown();

            return;
        }

        if (event.key === 'Enter' && !event.ctrlKey) {
            event.preventDefault();

            if (!event.repeat && value.trim() !== '') {
                onLookup(value.trim());
            }
        }
    };

    const name = withShortcut(label, 'search');

    return (
        <Field label={label} hint={hint} error={error}>
            <TextInput
                ref={setRef}
                size={'lg'}
                icon={'scan'}
                type={'search'}
                autoComplete={'off'}
                enterKeyHint={'search'}
                spellCheck={false}
                aria-label={name}
                aria-keyshortcuts={SHORTCUTS.search.aria}
                value={value}
                onChange={(event) => onValueChange(event.target.value)}
                onKeyDown={onKeyDown}
            />
        </Field>
    );
}

/** The results of a search: the arrow keys move between them; Enter or a click adds the focused product (UXA-09). */
export function ProductResults({ label, children }: { label: string; children: ReactNode }) {
    const onKeyDown = (event: KeyboardEvent<HTMLUListElement>): void => {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        const buttons = [...event.currentTarget.querySelectorAll<HTMLButtonElement>('.bk-result')];
        const index = buttons.findIndex((button) => button === document.activeElement);
        const next = buttons[event.key === 'ArrowDown' ? Math.min(buttons.length - 1, index + 1) : Math.max(0, index - 1)];

        if (next !== undefined) {
            event.preventDefault();
            next.focus();
        }
    };

    return (
        <ul className={'bk-results'} aria-label={label} onKeyDown={onKeyDown}>
            {children}
        </ul>
    );
}

/**
 * One result: its name (two lines at most, the full name in the accessible name), stock, SKU and price;
 * on the till also its thumbnail, an accessible name that says what the row does, and the visible word of
 * that action beside the price (UXI-067).
 */
export function ProductResult({
    name,
    meta,
    price,
    onAdd,
    disabledReason,
    thumb,
    accessibleName,
    addLabel,
}: {
    name: string;
    meta: ReactNode;
    price: string;
    onAdd: () => void;
    disabledReason?: string;
    thumb?: ReactNode;
    accessibleName?: string;
    addLabel?: string;
}) {
    const reasonId = useId();

    return (
        <li>
            <button
                type={'button'}
                className={'bk-result'}
                data-thumb={thumb !== undefined}
                aria-label={accessibleName}
                aria-disabled={disabledReason === undefined ? undefined : true}
                aria-describedby={disabledReason === undefined ? undefined : reasonId}
                onClick={() => (disabledReason === undefined ? onAdd() : undefined)}
            >
                {thumb}
                <span>
                    <span className={'bk-result__name bk-clamp-2'}>{name}</span>
                    <span className={'bk-result__meta'}>{meta}</span>
                    {disabledReason !== undefined && (
                        <span className={'bk-reason'} id={reasonId}>
                            {disabledReason}
                        </span>
                    )}
                </span>
                <span className={'bk-result__price'}>
                    {price}
                    {addLabel !== undefined && disabledReason === undefined && (
                        <span className={'bk-result__add'} aria-hidden={true}>
                            {addLabel}
                        </span>
                    )}
                </span>
            </button>
        </li>
    );
}

/** The cart: a labelled region; its lines scroll inside it while the total and "Bayar" stay fixed. */
export function CartRegion({ label, head, children, foot }: { label: string; head: ReactNode; children: ReactNode; foot: ReactNode }) {
    return (
        <section className={'bk-cart'} aria-label={label}>
            <div className={'bk-cart__head'}>{head}</div>
            <ul className={'bk-cart__lines'}>{children}</ul>
            <div className={'bk-cart__foot'}>{foot}</div>
        </section>
    );
}

/**
 * A cart line: the name in full, its unit price, the line total, the stepper and remove; on the till
 * also what the line must say — a price change, the stock available, a reason (`notes`) — and whether
 * it is marked for that or was just added (`marked`, `flash`).
 */
export function CartLine({
    name,
    meta,
    total,
    controls,
    totalLabel,
    notes,
    marked = false,
    flash = false,
    lineRef,
}: {
    name: string;
    meta: ReactNode;
    total: string;
    controls: ReactNode;
    totalLabel?: string;
    notes?: ReactNode;
    marked?: boolean;
    flash?: boolean;
    lineRef?: Ref<HTMLLIElement>;
}) {
    return (
        <li className={'bk-line'} data-marked={marked} data-flash={flash} ref={lineRef}>
            <div className={'bk-line__top'}>
                <div>
                    <p className={'bk-line__name'}>{name}</p>
                    <p className={'bk-line__meta'}>{meta}</p>
                </div>
                <p className={'bk-line__total'}>
                    {totalLabel !== undefined && <span className={'bk-line__sub'}>{totalLabel}</span>}
                    <span>{total}</span>
                </p>
            </div>
            <div className={'bk-line__controls'}>{controls}</div>
            {notes}
        </li>
    );
}

/**
 * The quantity stepper of a cart line: 44 px buttons, the quantity as a
 * decimal field when the product allows decimals (UXA-14). The labels name
 * the line's product (COPY_DECK CW-11). Plain Enter in the field commits the
 * quantity; Ctrl+Enter is left to the page's confirm binding, because the
 * stepper carries ENTER_BLOCKED_ATTRIBUTE (shortcuts.ts), so its field must
 * never act on Ctrl+Enter itself.
 */
export function QuantityStepper({
    label,
    value,
    onValueChange,
    onCommit,
    onIncrement,
    onDecrement,
    incrementLabel,
    decrementLabel,
    canDecrement,
    decimal = false,
    onEnter,
    decrementDescribedBy,
    invalid = false,
    describedBy,
}: {
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    onCommit: () => void;
    onIncrement: () => void;
    onDecrement: () => void;
    incrementLabel: string;
    decrementLabel: string;
    canDecrement: boolean;
    decimal?: boolean;
    /** After the commit of a plain Enter: the till returns the focus to its search field (UXA-09). */
    onEnter?: () => void;
    /** The id of the reason why "−" cannot act (UXS-13). */
    decrementDescribedBy?: string;
    /** The quantity is refused, with its message at `describedBy` (UXS-05). */
    invalid?: boolean;
    describedBy?: string;
}) {
    return (
        <span className={'bk-stepper'} {...{ [ENTER_BLOCKED_ATTRIBUTE]: '' }}>
            <button
                type={'button'}
                aria-label={decrementLabel}
                aria-disabled={canDecrement ? undefined : true}
                aria-describedby={canDecrement ? undefined : decrementDescribedBy}
                onClick={() => (canDecrement ? onDecrement() : undefined)}
            >
                <Icon name={'minus'} />
            </button>
            <input
                type={'text'}
                inputMode={decimal ? 'decimal' : 'numeric'}
                aria-label={label}
                aria-invalid={invalid ? true : undefined}
                aria-describedby={describedBy}
                value={value}
                onChange={(event) => onValueChange(event.target.value)}
                onBlur={onCommit}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.ctrlKey) {
                        event.preventDefault();
                        onCommit();
                        onEnter?.();
                    }
                }}
            />
            <button type={'button'} aria-label={incrementLabel} onClick={onIncrement}>
                <Icon name={'plus'} />
            </button>
        </span>
    );
}

/** The total of the cart and "Bayar" (F8) below it, at the foot of the cart. */
export function CartTotal({ label, value, payLabel, onPay, payDisabledReason }: { label: string; value: string; payLabel: string; onPay: () => void; payDisabledReason?: ReactNode }) {
    return (
        <>
            <p className={'bk-total'}>
                <span className={'bk-total__label'}>{label}</span>
                <span className={'bk-total__value'}>{value}</span>
            </p>
            <Button label={payLabel} size={'xl'} block={true} shortcut={'pay'} onClick={onPay} disabledReason={payDisabledReason} />
        </>
    );
}
