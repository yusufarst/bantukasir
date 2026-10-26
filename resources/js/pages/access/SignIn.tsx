import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Field, PasswordInput, TextInput } from '@/components/ui/Field';
import { StateNotice } from '@/components/ui/States';
import { PreAuthLayout } from '@/layouts/Page';
import { AUTH_STATES, problemOf, throttledBody, type AuthProblem } from '@/lib/authAnswer';
import { t } from '@/lib/i18n';
import { useOnline } from '@/lib/online';

// RP-001, UXP-001 "Masuk" (KS-01; INTERACTION_INVENTORY UXI-039 to UXI-041;
// COPY_DECK section 6.2; TASK-006). Pre-authentication: no shell, no business
// data, no codename. One uniform failure (UXS-42), whatever was wrong; the
// email stays and the password is always cleared (NAVIGATION_CONTRACTS UXP-001,
// SEC-CH-03). No "remember me" and no password-recovery link (SEC-AUTH-07,
// FD-04). Enter submits the form, the exception of UXA-10 for authentication
// forms. Success is a full page load to the role's home (SEC-AUTH-10), which
// the server answers.

type Outcome = { readonly kind: 'failed' } | AuthProblem;

export default function SignIn({ notice }: { notice: 'signed_out' | null }) {
    const form = useForm({ email: '', password: '' });
    const [outcome, setOutcome] = useState<Outcome | null>(null);
    const online = useOnline();
    const email = useRef<HTMLInputElement | null>(null);
    const password = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        email.current?.focus();
    }, []);

    const setEmail = (event: ChangeEvent<HTMLInputElement>): void => form.setData('email', event.target.value);
    const setPassword = (value: string): void => form.setData('password', value);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        setOutcome(null);
        form.post('/login', {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                setOutcome({ kind: 'failed' });
                password.current?.focus();
            },
            onHttpException: (response) => {
                setOutcome(problemOf(response));

                return false;
            },
            onNetworkError: () => {
                setOutcome({ kind: 'lost' });

                return false;
            },
            onFinish: () => form.reset('password'),
        });
    };

    const title = t('access.sign_in.title');
    const reload = <Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />;

    return (
        <PreAuthLayout>
            <Head title={t('ux.shell.document_title_public', { halaman: title })} />
            <h1 className={'bk-title'} tabIndex={-1}>
                {title}
            </h1>
            {notice === 'signed_out' && outcome === null && <StateNotice state={AUTH_STATES.signedOut} />}
            {(!online || outcome?.kind === 'lost') && <StateNotice state={AUTH_STATES.offline} />}
            {outcome?.kind === 'failed' && <StateNotice state={AUTH_STATES.signInFailed} />}
            {outcome?.kind === 'throttled' && <StateNotice state={AUTH_STATES.throttled} body={throttledBody(outcome.seconds)} />}
            {outcome?.kind === 'reload' && <StateNotice state={AUTH_STATES.reload} actions={reload} />}
            <form className={'bk-form'} noValidate={true} onSubmit={submit}>
                <Field label={t('access.sign_in.email')} required={true}>
                    <TextInput ref={email} size={'lg'} type={'email'} inputMode={'email'} autoComplete={'username'} spellCheck={false} value={form.data.email} onChange={setEmail} />
                </Field>
                <Field label={t('access.sign_in.password')} required={true}>
                    <PasswordInput ref={password} size={'lg'} autoComplete={'current-password'} value={form.data.password} onValueChange={setPassword} />
                </Field>
                <Button type={'submit'} label={t('access.sign_in.submit')} size={'lg'} block={true} busy={form.processing} />
            </form>
        </PreAuthLayout>
    );
}
