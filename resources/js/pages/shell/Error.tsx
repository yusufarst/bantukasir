import { Head, usePage } from '@inertiajs/react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { STATE_CATALOGUE } from '@/components/ui/stateCatalogue';
import { AppShell, type SignedIn } from '@/layouts/AppShell';
import { PageContainer, PageHeader, PreAuthLayout } from '@/layouts/Page';
import { ERROR_PAGE_STATES, retrySeconds, type ErrorPageState } from '@/lib/answers';
import { throttledBody } from '@/lib/authAnswer';
import { t } from '@/lib/i18n';

// The generic, translated error page (SECURITY_MODEL SEC-CH-10; TECHNICAL_RULES
// TR-ERR-02; UI_STATES UXS-07, UXS-08, UXS-10, UXS-12, UXS-22; TASK-007 scope
// items 5 and 9), for a page loaded or visited with GET that the server
// refused or could not answer (App\Http\Errors\ErrorAnswer). It names no
// internal detail and, for NOT_FOUND, never whether the object exists: one
// text for missing and outside the actor's scope (TR-ERR-03). Its way back is
// the role's home (NC-12) — RN-01, which leads a visitor without a sign-in
// session to the sign-in page. A signed-in user sees it in the shell.

const HOME = '/';

export default function ErrorPage({ state, retryAfter }: { state: ErrorPageState; retryAfter: number | null }) {
    const signedIn = usePage<{ auth: SignedIn | null }>().props.auth;
    const entry = STATE_CATALOGUE[ERROR_PAGE_STATES[state]];
    const title = t(entry.title);
    const body = state === 'throttled' ? throttledBody(retrySeconds(retryAfter)) : entry.body === undefined ? undefined : t(entry.body);
    const reload = (): void => window.location.reload();

    const action =
        state === 'forbidden' || state === 'not_found' ? (
            <ButtonLink href={HOME} label={t('ux.action.back_home')} variant={'primary'} />
        ) : state === 'reload' ? (
            <Button label={t('ux.action.reload')} onClick={reload} />
        ) : state === 'load_failed' ? (
            <Button label={t('ux.action.retry')} onClick={reload} />
        ) : undefined;

    const content = (
        <div className={'bk-stack'}>
            <PageHeader title={title} />
            <Card>
                <div className={'bk-stack'}>
                    {body !== undefined && <p>{body}</p>}
                    {action !== undefined && <div className={'bk-actions'}>{action}</div>}
                </div>
            </Card>
        </div>
    );

    if (signedIn !== null) {
        return (
            <AppShell title={title}>
                <PageContainer width={'form'}>{content}</PageContainer>
            </AppShell>
        );
    }

    return (
        <>
            <Head title={t('ux.shell.document_title_public', { halaman: title })} />
            <PreAuthLayout>{content}</PreAuthLayout>
        </>
    );
}
