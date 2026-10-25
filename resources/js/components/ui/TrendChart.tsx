import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { parseMoney, unitsAt } from '@/lib/exact';
import { useAnnounce } from './Announcer';
import { Button } from './Button';
import { DataTable, type Column } from './DataTable';

export interface TrendPoint {
    /** The Asia/Jakarta business day, already formatted (COPY_DECK 1.3). */
    readonly date: string;
    /** Net sales of the day as a canonical money string (ADR-006). */
    readonly value: string;
    /** The same amount, formatted. */
    readonly valueText: string;
}

const HEIGHT = 240;
const PAD_LEFT = 12;
const PAD_RIGHT = 12;
const PAD_TOP = 16;
const PAD_BOTTOM = 16;
const GRID_LINES = 4;

/**
 * The vertical position of a value, computed in whole rupiah with BigInt,
 * never as a float (ADR-006). The range always holds zero, so it is empty
 * only when every value of the period is zero: the series then lies on the
 * baseline, where zero is drawn, not at the top of the plot.
 */
export function yOf(value: bigint, min: bigint, max: bigint): string {
    if (max === min) {
        return String(HEIGHT - PAD_BOTTOM);
    }

    const plot = BigInt(HEIGHT - PAD_TOP - PAD_BOTTOM);

    return String(BigInt(PAD_TOP) + ((max - value) * plot) / (max - min));
}

/**
 * The trend chart of DESIGN_SYSTEM 8.13 (UXA-19): one series, net sales per
 * Asia/Jakarta business day, in the shop's accent with a light area and grid
 * lines. It is reachable with Tab; the arrow keys, Home and End move between
 * the points; each point shows and announces its date and value on pointer,
 * focus or touch; and the toggle shows the same data as a table. The page
 * gives the texts: the title, the toggle's labels, the table's headers and
 * the point's wording (reporting.finance.*).
 */
export function TrendChart({
    title,
    points,
    pointText,
    showTableLabel,
    showChartLabel,
    dateHeader,
    valueHeader,
}: {
    title: string;
    points: readonly TrendPoint[];
    pointText: (point: TrendPoint) => string;
    showTableLabel: string;
    showChartLabel: string;
    dateHeader: string;
    valueHeader: string;
}) {
    const announce = useAnnounce();
    const host = useRef<HTMLDivElement | null>(null);
    const [width, setWidth] = useState(720);
    const [active, setActive] = useState<number | null>(null);
    const [asTable, setAsTable] = useState(false);
    const tipId = useId();

    useEffect(() => {
        const element = host.current;

        if (element === null || typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const observer = new ResizeObserver((entries) => {
            const measured = entries[0]?.contentRect.width ?? 0;

            if (measured > 0) {
                setWidth(Math.max(240, Math.round(measured)));
            }
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [asTable]);

    const values = points.map((point) => unitsAt(parseMoney(point.value), 0));
    const max = values.reduce((highest, value) => (value > highest ? value : highest), 0n);
    const min = values.reduce((lowest, value) => (value < lowest ? value : lowest), 0n);
    const last = points.length - 1;
    const xOf = (index: number): string => String(PAD_LEFT + (last <= 0 ? (width - PAD_LEFT - PAD_RIGHT) / 2 : ((width - PAD_LEFT - PAD_RIGHT) * index) / last));
    const coordinates = values.map((value, index) => ({ x: xOf(index), y: yOf(value, min, max) }));
    const line = coordinates.map((point) => `${point.x},${point.y}`).join(' ');
    const base = yOf(0n, min, max);
    const area = coordinates.length === 0 ? '' : `M${coordinates[0]?.x ?? '0'},${base} L${line.split(' ').join(' L')} L${coordinates[last]?.x ?? '0'},${base} Z`;
    const grid = Array.from({ length: GRID_LINES + 1 }, (_, index) => String(PAD_TOP + ((HEIGHT - PAD_TOP - PAD_BOTTOM) * index) / GRID_LINES));

    const show = (index: number, speak: boolean): void => {
        const point = points[index];

        if (point === undefined) {
            return;
        }

        setActive(index);

        if (speak) {
            announce(pointText(point));
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>): void => {
        const current = active ?? last;
        const next = { ArrowRight: Math.min(last, current + 1), ArrowLeft: Math.max(0, current - 1), Home: 0, End: last }[event.key];

        if (next !== undefined) {
            event.preventDefault();
            show(next, true);
        }
    };

    const activePoint = active === null ? undefined : points[active];
    const viewBox = `0 0 ${width} ${HEIGHT}`;
    const columns: readonly Column<TrendPoint>[] = [
        { id: 'date', header: dateHeader, isHead: true, cell: (point) => point.date },
        { id: 'value', header: valueHeader, numeric: true, cell: (point) => point.valueText },
    ];

    return (
        <div className={'bk-chart'}>
            <div className={'bk-actions'}>
                <Button
                    label={asTable ? showChartLabel : showTableLabel}
                    variant={'secondary'}
                    aria-pressed={asTable}
                    onClick={() => setAsTable((value) => !value)}
                />
            </div>
            {asTable ? (
                <DataTable caption={title} columns={columns} rows={points} rowKey={(point) => point.date} />
            ) : (
                <>
                    <div
                        ref={host}
                        className={'bk-chart__plot'}
                        tabIndex={0}
                        role={'group'}
                        aria-label={title}
                        aria-describedby={activePoint === undefined ? undefined : tipId}
                        onKeyDown={onKeyDown}
                        onFocus={() => show(active ?? last, true)}
                        onPointerLeave={() => (host.current?.contains(document.activeElement) === true ? undefined : setActive(null))}
                        data-testid={'trend-chart'}
                    >
                        <svg viewBox={viewBox} aria-hidden={true} focusable={false}>
                            {grid.map((y) => (
                                <line key={y} className={'bk-chart__grid'} x1={PAD_LEFT} x2={width - PAD_RIGHT} y1={y} y2={y} />
                            ))}
                            {area !== '' && <path className={'bk-chart__area'} d={area} />}
                            <polyline className={'bk-chart__line'} points={line} />
                            {coordinates.map((point, index) => (
                                <g key={index}>
                                    <circle className={'bk-chart__pt'} cx={point.x} cy={point.y} r={4} data-active={index === active} />
                                    <circle
                                        className={'bk-chart__hit'}
                                        cx={point.x}
                                        cy={point.y}
                                        r={14}
                                        onPointerMove={() => show(index, false)}
                                        onPointerDown={() => show(index, true)}
                                    />
                                </g>
                            ))}
                        </svg>
                    </div>
                    <div className={'bk-chart__range'} aria-hidden={true}>
                        <span>{points[0]?.date}</span>
                        <span>{points[last]?.date}</span>
                    </div>
                    {activePoint !== undefined && (
                        <p className={'bk-chart__tip'} id={tipId} data-testid={'trend-tip'}>
                            {pointText(activePoint)}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
