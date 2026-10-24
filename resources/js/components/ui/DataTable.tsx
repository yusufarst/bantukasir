import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface Column<Row> {
    readonly id: string;
    readonly header: string;
    /** Numbers are right-aligned and never wrapped (DESIGN_SYSTEM 8.11). */
    readonly numeric?: boolean;
    /** The row's name, shown as the card's head below 640 px of container width. */
    readonly isHead?: boolean;
    /** The actions of the row, which name their row in their accessible names. */
    readonly isAction?: boolean;
    readonly cell: (row: Row) => ReactNode;
}

/** A cell; the row's head is a row header, labelled as the card's head below 640 px. */
function TableCell<Row>({ column, row }: { column: Column<Row>; row: Row }) {
    const head = column.isHead === true;
    const classes = cn(column.numeric === true && 'is-num', head && 'is-head', column.isAction === true && 'is-action');
    const content = (
        <>
            {!head && column.isAction !== true && (
                <span className={'bk-table__label'} aria-hidden={true}>
                    {column.header}
                </span>
            )}
            <span>{column.cell(row)}</span>
        </>
    );

    return head ? (
        <th scope={'row'} className={classes}>
            {content}
        </th>
    ) : (
        <td className={classes}>{content}</td>
    );
}

/**
 * A table of DESIGN_SYSTEM 8.11: a header row with scope; below 640 px of its
 * container's width each row becomes a card with labelled values. Only a
 * table marked `wide` — stock history, the logs, a CSV preview — scrolls
 * inside its own container instead, with its first column kept visible
 * (UXA-12). The labels of the card are in the markup, never read from an
 * attribute.
 */
export function DataTable<Row>({
    caption,
    captionHidden = true,
    columns,
    rows,
    rowKey,
    wide = false,
}: {
    caption: string;
    captionHidden?: boolean;
    columns: readonly Column<Row>[];
    rows: readonly Row[];
    rowKey: (row: Row) => string;
    wide?: boolean;
}) {
    return (
        <div className={'bk-table-host'} data-wide={wide} tabIndex={wide ? 0 : undefined} role={wide ? 'region' : undefined} aria-label={wide ? caption : undefined}>
            <table className={'bk-table'}>
                <caption className={captionHidden ? 'bk-sr-only' : 'bk-h3'}>{caption}</caption>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column.id} scope={'col'} className={cn(column.numeric === true && 'is-num', column.isAction === true && 'is-action')}>
                                {column.isAction === true ? <span className={'bk-sr-only'}>{column.header}</span> : column.header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map((column) => (
                                <TableCell key={column.id} column={column} row={row} />
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
