import { formatCount } from '@/lib/format';

/**
 * The numbered steps of a guided flow (DESIGN_SYSTEM 8.15), such as Tutup
 * sesi kas: preview, then count. The current step is marked by text weight,
 * its tint and aria-current; colour never carries it alone.
 */
export function Steps({ label, steps, current }: { label: string; steps: readonly string[]; current: number }) {
    return (
        <ol className={'bk-steps'} aria-label={label}>
            {steps.map((step, index) => (
                <li key={step} aria-current={index === current ? 'step' : undefined} data-done={index < current}>
                    <span className={'bk-steps__n'} aria-hidden={true}>
                        {formatCount(index + 1)}
                    </span>
                    <span>{step}</span>
                </li>
            ))}
        </ol>
    );
}
