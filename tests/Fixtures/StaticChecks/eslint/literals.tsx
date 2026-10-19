// Negative sample of react/jsx-no-literals (ADR-005 item 5): text and attribute
// literals instead of translation keys.
export function Literals() {
    return (
        <div title="Judul">
            Teks
            <input placeholder="Cari" aria-label={'Label'} />
            {'Teks lain'}
        </div>
    );
}
