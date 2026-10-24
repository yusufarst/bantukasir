import * as RadioGroup from '@radix-ui/react-radio-group';
import * as Toggle from '@radix-ui/react-toggle';
import { useId, useRef, type ReactNode } from 'react';
import { Icon } from './Icon';
import type { IconName } from './icons';
import { withShortcut, type ShortcutId } from './shortcuts';
import { useShortcut } from './useShortcut';

export interface SegmentedOption<V extends string> {
    readonly value: V;
    readonly label: string;
    readonly icon?: IconName;
    /** Alt+1 and Alt+2 choose the payment methods (UXA-09). */
    readonly shortcut?: ShortcutId;
    readonly disabled?: boolean;
}

/**
 * The segmented control of DESIGN_SYSTEM 8.4 for two or three exclusive
 * options — the payment method, the receipt size, the cash register — with
 * radio semantics: Tab enters the group, the arrow keys move and choose.
 * Built on the Radix radio group of shadcn/ui.
 */
export function SegmentedControl<V extends string>({
    legend,
    options,
    value,
    onValueChange,
    size = 'md',
}: {
    legend: string;
    options: readonly SegmentedOption<V>[];
    value: V | null;
    onValueChange: (value: V) => void;
    size?: 'md' | 'xl';
}) {
    const legendId = useId();

    return (
        <div className={'bk-field'}>
            <span className={'bk-label bk-legend'} id={legendId}>
                {legend}
            </span>
            <RadioGroup.Root
                className={'bk-seg'}
                data-size={size}
                data-count={options.length}
                aria-labelledby={legendId}
                value={value ?? ''}
                onValueChange={(next) => {
                    const chosen = options.find((option) => option.value === next);

                    if (chosen !== undefined) {
                        onValueChange(chosen.value);
                    }
                }}
            >
                {options.map((option) => (
                    <SegmentedItem key={option.value} option={option} size={size} onChoose={onValueChange} />
                ))}
            </RadioGroup.Root>
        </div>
    );
}

function SegmentedItem<V extends string>({ option, size, onChoose }: { option: SegmentedOption<V>; size: 'md' | 'xl'; onChoose: (value: V) => void }) {
    const ref = useRef<HTMLButtonElement | null>(null);

    useShortcut(
        option.shortcut,
        ref,
        () => {
            if (option.disabled === true) {
                return false;
            }

            onChoose(option.value);
            ref.current?.focus();

            return true;
        },
        option.disabled !== true,
    );

    return (
        <RadioGroup.Item
            ref={ref}
            className={'bk-seg__opt'}
            value={option.value}
            disabled={option.disabled}
            aria-label={option.shortcut === undefined ? undefined : withShortcut(option.label, option.shortcut)}
        >
            {option.icon !== undefined && <Icon name={option.icon} size={size === 'xl' ? 'lg' : 'md'} />}
            <span>{option.label}</span>
            <span className={'bk-seg__check'}>
                <Icon name={'check'} size={'sm'} />
            </span>
        </RadioGroup.Item>
    );
}

/** A filter chip of DESIGN_SYSTEM 8.4: a toggle button (aria-pressed) with a check icon. */
export function FilterChip({ label, pressed, onPressedChange }: { label: string; pressed: boolean; onPressedChange: (pressed: boolean) => void }) {
    return (
        <Toggle.Root className={'bk-chip'} pressed={pressed} onPressedChange={onPressedChange}>
            <Icon name={'check'} size={'sm'} />
            <span>{label}</span>
        </Toggle.Root>
    );
}

/** The chips of one filter, labelled as a group. */
export function FilterChips({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className={'bk-chips'} role={'group'} aria-label={label}>
            {children}
        </div>
    );
}
