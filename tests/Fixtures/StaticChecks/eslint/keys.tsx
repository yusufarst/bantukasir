// Compliant sample of react/jsx-no-literals: every text comes from a key.
declare function t(key: string): string;

export function Keys() {
    return (
        <div title={t('sample.greeting.title')}>
            {t('sample.greeting.title')}
            <span>·</span>
        </div>
    );
}
