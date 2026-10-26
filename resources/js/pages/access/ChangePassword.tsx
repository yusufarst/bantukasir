import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent, type MouseEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { UnsavedDialog } from '@/components/ui/Dialog';
import { Field, PasswordInput } from '@/components/ui/Field';
import { Notice } from '@/components/ui/Notice';
import { ErrorSummary, StateNotice, type FieldError } from '@/components/ui/States';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { AUTH_STATES, problemOf, throttledBody, type AuthProblem } from '@/lib/authAnswer';
import { t } from '@/lib/i18n';
import { useOnline } from '@/lib/online';

// RP-004, UXP-004 "Ubah kata sandi" (INTERACTION_INVENTORY UXI-047 to UXI-050;
// COPY_DECK section 6.5; NAVIGATION_CONTRACTS UXP-004; TASK-006). The current
// password, the new one and its repetition, sent to RA-05; a wrong current
// password is one uniform message (UXS-42); the password fields are never kept
// after an answer (SEC-CH-03). "Batal" and "Kembali ke Akun saya" lead to
// UXP-003 with the unsaved-changes guard (UXS-45), "Tetap di halaman ini" being
// the default. Success lands on UXP-003 with its message: the user's other
// sign-in sessions have ended (SEC-AUTH-11). Enter submits the form (UXA-10
// exception for authentication forms).

type Outcome = { readonly kind: 'refused' } | AuthProblem;

type FieldName = 'current_password' | 'password' | 'password_confirmation';

const FIELD_NAMES: readonly FieldName[] = ['current_password', 'password', 'password_confirmation'];

const FIELD_IDS: Readonly<Record<FieldName, string>> = {
    current_password: 'change-password-current',
    password: 'change-password-new',
    password_confirmation: 'change-password-repeat',
};

const ACCOUNT = '/account';

export default function ChangePassword() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const [outcome, setOutcome] = useState<Outcome | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Readonly<Partial<Record<FieldName, string>>>>({});
    const [leaving, setLeaving] = useState(false);
    const online = useOnline();
    const first = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        first.current?.focus();
    }, []);

    const entered = form.data.current_password !== '' || form.data.password !== '' || form.data.password_confirmation !== '';

    const guard = (event: MouseEvent<HTMLAnchorElement>): void => {
        if (entered) {
            event.preventDefault();
            setLeaving(true);
        }
    };

    const setCurrent = (value: string): void => form.setData('current_password', value);
    const setPassword = (value: string): void => form.setData('password', value);
    const setRepeat = (value: string): void => form.setData('password_confirmation', value);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        setOutcome(null);
        setFieldErrors({});
        form.put('/user/password', {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                if (errors.form !== undefined) {
                    setOutcome({ kind: 'refused' });
                    first.current?.focus();

                    return;
                }

                setFieldErrors({ current_password: errors.current_password, password: errors.password, password_confirmation: errors.password_confirmation });
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

    const labels: Readonly<Record<FieldName, string>> = {
        current_password: t('access.change_password.current'),
        password: t('access.set_password.new'),
        password_confirmation: t('access.set_password.repeat'),
    };
    const summary: FieldError[] = [];

    for (const field of FIELD_NAMES) {
        const message = fieldErrors[field];

        if (message !== undefined) {
            summary.push({ fieldId: FIELD_IDS[field], label: labels[field], message });
        }
    }

    const title = t('access.change_password.title');
    const back = { href: ACCOUNT, page: t('access.account.title'), onNavigate: guard };
    const reload = <Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />;
    const leave = (): void => window.location.assign(ACCOUNT);

    return (
        <main className={'bk-page'}>
            <Head title={t('ux.shell.document_title_public', { halaman: title })} />
            <PageContainer width={'form'}>
                <div className={'bk-stack'}>
                    <PageHeader title={title} back={back} />
                    {!online && <StateNotice state={AUTH_STATES.offline} />}
                    {outcome?.kind === 'refused' && <StateNotice state={AUTH_STATES.currentPasswordRefused} />}
                    {outcome?.kind === 'throttled' && <StateNotice state={AUTH_STATES.throttled} body={throttledBody(outcome.seconds)} />}
                    {outcome?.kind === 'reload' && <StateNotice state={AUTH_STATES.reload} actions={reload} />}
                    {outcome?.kind === 'lost' && (
                        <Notice tone={'warning'} urgency={'alert'}>
                            <p>{t('access.change_password.lost_answer')}</p>
                        </Notice>
                    )}
                    {summary.length > 0 && <ErrorSummary errors={summary} />}
                    <Card>
                        <form className={'bk-form'} noValidate={true} onSubmit={submit}>
                            <Field id={FIELD_IDS.current_password} label={labels.current_password} required={true} error={fieldErrors.current_password}>
                                <PasswordInput ref={first} autoComplete={'current-password'} value={form.data.current_password} onValueChange={setCurrent} />
                            </Field>
                            <Field id={FIELD_IDS.password} label={labels.password} required={true} hint={t('access.password_rule')} error={fieldErrors.password}>
                                <PasswordInput autoComplete={'new-password'} value={form.data.password} onValueChange={setPassword} />
                            </Field>
                            <Field id={FIELD_IDS.password_confirmation} label={labels.password_confirmation} required={true} error={fieldErrors.password_confirmation}>
                                <PasswordInput autoComplete={'new-password'} value={form.data.password_confirmation} onValueChange={setRepeat} />
                            </Field>
                            <div className={'bk-actions'}>
                                <Button type={'submit'} label={t('access.set_password.submit')} busy={form.processing} />
                                <ButtonLink href={ACCOUNT} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                            </div>
                        </form>
                    </Card>
                </div>
            </PageContainer>
            <UnsavedDialog open={leaving} onStay={() => setLeaving(false)} onLeave={leave} />
        </main>
    );
}
