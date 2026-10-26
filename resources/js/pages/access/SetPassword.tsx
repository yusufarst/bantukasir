import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Field, PasswordInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import { Committed, ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { PreAuthLayout } from '@/layouts/Page';
import { AUTH_STATES, problemOf, throttledBody, type AuthProblem } from '@/lib/authAnswer';
import { t } from '@/lib/i18n';
import { forgetLinkFragment, linkFragment } from '@/lib/linkFragment';
import { useOnline } from '@/lib/online';

// RP-002, UXP-002 "Atur kata sandi" (INTERACTION_INVENTORY UXI-042 to UXI-044;
// COPY_DECK section 6.3; SECURITY_MODEL SEC-ACC-05, SEC-ACC-06; TASK-006). The
// account and the token come from the link's fragment, which app.tsx read and
// removed before the page started (lib/linkFragment.ts); they stay in this
// page's memory and are posted in the body of RA-06 only. One text for a first
// password, a reset and a link of an operator procedure, so that the page
// reveals nothing about the link; every failure of the link is the one
// LINK_INVALID (UXS-41). Enter submits the form (UXA-10 exception). After a
// success the link "Masuk" becomes the primary action (COPY_DECK section 6.3).

type Outcome = { readonly kind: 'invalid' } | AuthProblem;

type FieldName = 'password' | 'password_confirmation';

const FIELD_IDS: Readonly<Record<FieldName, string>> = { password: 'set-password-new', password_confirmation: 'set-password-repeat' };

const SIGN_IN = '/login';

export default function SetPassword({ passwordSet }: { passwordSet: boolean }) {
    const form = useForm({ password: '', password_confirmation: '' });
    const [outcome, setOutcome] = useState<Outcome | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Readonly<Partial<Record<FieldName, string>>>>({});
    const online = useOnline();
    const first = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        if (passwordSet) {
            forgetLinkFragment();
        } else {
            first.current?.focus();
        }
    }, [passwordSet]);

    const setPassword = (value: string): void => form.setData('password', value);
    const setRepeat = (value: string): void => form.setData('password_confirmation', value);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        const link = linkFragment();

        setOutcome(null);
        setFieldErrors({});
        form.transform((data) => ({ user: link?.user ?? '', token: link?.token ?? '', ...data }));
        form.post('/set-password', {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                if (errors.form !== undefined) {
                    setOutcome({ kind: 'invalid' });

                    return;
                }

                setFieldErrors({ password: errors.password, password_confirmation: errors.password_confirmation });
            },
            onHttpException: (response) => {
                setOutcome(problemOf(response));

                return false;
            },
            onNetworkError: () => {
                setOutcome({ kind: 'lost' });

                return false;
            },
            onFinish: () => form.reset(),
        });
    };

    const labels: Readonly<Record<FieldName, string>> = { password: t('access.set_password.new'), password_confirmation: t('access.set_password.repeat') };
    const summary: FieldError[] = [];

    for (const field of ['password', 'password_confirmation'] as const) {
        const message = fieldErrors[field];

        if (message !== undefined) {
            summary.push({ fieldId: FIELD_IDS[field], label: labels[field], message });
        }
    }

    const title = t('access.set_password.title');
    const reload = <Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />;
    const toSignIn = passwordSet ? (
        <ButtonLink href={SIGN_IN} label={t('access.sign_in.title')} variant={'primary'} size={'lg'} />
    ) : (
        <ButtonLink href={SIGN_IN} label={t('access.set_password.to_sign_in')} variant={'quiet'} size={'lg'} />
    );

    return (
        <PreAuthLayout>
            <Head title={t('ux.shell.document_title_public', { halaman: title })} />
            <h1 className={'bk-title'} tabIndex={-1}>
                {title}
            </h1>
            {!online && <StateNotice state={AUTH_STATES.offline} />}
            {passwordSet ? (
                <Committed message={t('access.set_password.success')} />
            ) : outcome?.kind === 'invalid' ? (
                <StateNotice state={AUTH_STATES.linkInvalid} />
            ) : (
                <>
                    <p>{t('access.set_password.intro')}</p>
                    {outcome?.kind === 'throttled' && <StateNotice state={AUTH_STATES.throttled} body={throttledBody(outcome.seconds)} />}
                    {outcome?.kind === 'reload' && <StateNotice state={AUTH_STATES.reload} actions={reload} />}
                    {outcome?.kind === 'lost' && (
                        <Notice tone={'warning'} urgency={'alert'}>
                            <p>{t('access.set_password.lost_answer')}</p>
                        </Notice>
                    )}
                    {summary.length > 0 && <ErrorSummary errors={summary} />}
                    <form className={'bk-form'} noValidate={true} onSubmit={submit}>
                        <Field id={FIELD_IDS.password} label={labels.password} required={true} hint={t('access.password_rule')} error={fieldErrors.password}>
                            <PasswordInput ref={first} size={'lg'} autoComplete={'new-password'} value={form.data.password} onValueChange={setPassword} />
                        </Field>
                        <Field id={FIELD_IDS.password_confirmation} label={labels.password_confirmation} required={true} error={fieldErrors.password_confirmation}>
                            <PasswordInput size={'lg'} autoComplete={'new-password'} value={form.data.password_confirmation} onValueChange={setRepeat} />
                        </Field>
                        <Button type={'submit'} label={t('access.set_password.submit')} size={'lg'} block={true} busy={form.processing} />
                    </form>
                </>
            )}
            {toSignIn}
        </PreAuthLayout>
    );
}
