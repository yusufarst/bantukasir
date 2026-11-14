import { formatDateTime, formatMoney } from '@/lib/format';
import { t } from '@/lib/i18n';
import { conditionWord, payoutMethodWord, payoutSourceWord, positive, returnedWord, type Condition, type Correction } from '@/lib/sales/refunds';

// A committed correction of a Sale as the Owner reads it (COPY_DECK section 7.7, UXI-120; TASK-034): the
// refund's money with its method, its source and its time; each line's goods returned, with their condition
// and time; and the reason. UXP-202 lists every correction of the Sale so (UXI-120); UXP-204 shows the one it
// recorded so beneath its instruction (UXI-133). The original Sale stays as it was (BR-RFD-12).

const SALEABLE: Condition = 'SALEABLE';

const NOT_SALEABLE: Condition = 'NOT_SALEABLE';

/** The goods a correction returned, one entry per line and condition, in the order of the Sale. */
function goodsOf(correction: Correction): readonly { readonly key: string; readonly text: string }[] {
    const time = formatDateTime(correction.recordedAt);
    const goods: { key: string; text: string }[] = [];

    for (const entry of correction.lines) {
        for (const [condition, quantity] of [
            [SALEABLE, entry.returnedSaleable],
            [NOT_SALEABLE, entry.returnedNotSaleable],
        ] as const) {
            if (positive(quantity, entry.quantityPrecision)) {
                goods.push({
                    key: `${String(entry.saleLineId)}-${condition}`,
                    text: t('sales.detail.return_entry', { jumlah: returnedWord(quantity, entry), produk: entry.name, kondisi: conditionWord(condition), waktu: time }),
                });
            }
        }
    }

    return goods;
}

export function CorrectionEntries({ correction }: { correction: Correction }) {
    const refund = correction.refund;
    const money = refund === null ? null : t('sales.detail.refund_entry', { uang: formatMoney(refund.amount), cara: payoutMethodWord(refund.method), sumber: payoutSourceWord(refund), waktu: formatDateTime(correction.recordedAt) });

    return (
        <ul className={'bk-stack'} role={'list'}>
            {money !== null && <li>{money}</li>}
            {goodsOf(correction).map((entry) => (
                <li key={entry.key}>{entry.text}</li>
            ))}
            <li>{t('sales.detail.refund_reason', { alasan: correction.reason })}</li>
        </ul>
    );
}
