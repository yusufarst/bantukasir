import { router } from '@inertiajs/react';
import { useEffect, useId, useState, type ChangeEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card, Section } from '@/components/ui/Card';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import type { StateId } from '@/components/ui/stateCatalogue';
import { ErrorSummary, Loading, StateNotice, type FieldError } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { ACCENT_NAMES, RevisionFields, TEXT_FIELDS, markSaved, type Revision, type TextField } from '@/lib/configuration/profile';
import { CommandPanels } from '@/lib/commands/Panels';
import { useCommand } from '@/lib/commands/useCommand';
import { isAccentToken } from '@/lib/accent';
import { t } from '@/lib/i18n';
import type { DestinationId } from '@/lib/navigation';
import type { TranslationKey } from '@/types/translation-keys';

// UXP-608 "Ubah profil usaha" (ROUTE_CONTRACTS RP-608, RC-32; COPY_DECK
// section 11.8; INTERACTION_INVENTORY UXI-361 to UXI-366; NAVIGATION_CONTRACTS
// UXP-608; BR-CFG-01 to BR-CFG-04; TASK-010 scope items 4 and 5): the form
// of a new BusinessProfile version, with the revision in force as its expected
// version, the logo and the accent set named by the keys of TASK-002. "Simpan
// sebagai versi baru" is enabled once a field changed (UXS-13), never by
// Enter (UXA-10), and needs recent authentication (UXS-11): the shell's dialog
// sends the same frozen request after it. With a new logo the request is
// multipart. A refusal shows its reason at its field and in the summary at the
// top, which takes the focus (UXS-05) — a refused logo with the reason of
// SECURITY_MODEL section 13. A revision changed since the page loaded is
// UXS-17: the page reads the revision in force again and shows it beside the
// form, which keeps what the user reviewed, and the next save is expected
// against that revision. Success leads to UXP-607 with the new version in
// force. Leaving with changes asks first (UXS-45).

const PROFILE_COMMAND = { operation: 'business_profile.change', family: 'profile', recentAuth: true } as const;

const PROFILE = '/settings/business-profile';

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

/** A revision changed since the page loaded (UXS-17). */
const STALE_STATE: StateId = 'UXS-17';

const STALE_VERSION = 'STALE_VERSION';

const VALIDATION_FAILED = 'VALIDATION_FAILED';

/** The prop that a partial reload reads again after UXS-17: the revision in force. */
const CURRENT_PROP = 'current';

/** Whether the revision in force was read again after UXS-17. */
type CurrentRead = 'reading' | 'read' | 'failed';

/** The path of a payload field in a refusal's `fields`. */
const PAYLOAD_PREFIX = 'payload.';

/** The images a logo may be (BR-CFG-04); the server judges the content. */
const LOGO_TYPES = 'image/png,image/jpeg,image/webp';

/** The hints of the fields that have one (COPY_DECK section 11.8, UXI-361). */
const HINTS: Readonly<Partial<Record<TextField, TranslationKey>>> = {
    documentHeader: 'config.profile.header_text_help',
    documentFooter: 'config.profile.footer_text_help',
    documentIdentity: 'config.profile.document_label_help',
};

/** The longest text of each field (TECHNICAL_RULES section 11.3). */
const MAX_LENGTH: Readonly<Record<TextField, number>> = {
    shopName: 100,
    address: 500,
    contact: 200,
    email: 254,
    documentHeader: 500,
    documentFooter: 500,
    documentIdentity: 100,
};

type Texts = Record<TextField, string>;

/** The form's texts of a revision: an absent optional field is empty. */
function textsOf(revision: Revision): Texts {
    return {
        shopName: revision.shopName,
        address: revision.address ?? '',
        contact: revision.contact ?? '',
        email: revision.email ?? '',
        documentHeader: revision.documentHeader ?? '',
        documentFooter: revision.documentFooter ?? '',
        documentIdentity: revision.documentIdentity ?? '',
    };
}

export default function ProfileEdit({ current, accents }: { current: Revision; accents: readonly string[] }) {
    const title = t('config.profile_edit.title');

    return (
        <AppShell title={title} current={DESTINATION}>
            <ProfileForm title={title} current={current} accents={accents} />
        </AppShell>
    );
}

function ProfileForm({ title, current, accents }: { title: string; current: Revision; accents: readonly string[] }) {
    const control = useCommand(PROFILE_COMMAND);
    const { phase, blockedBy } = control;
    const guard = useLeaveGuard();
    const initial = textsOf(current);
    const [texts, setTexts] = useState<Texts>(initial);
    const [accent, setAccent] = useState(current.accentToken);
    const [logo, setLogo] = useState<File | null>(null);
    const ids = useId();

    const changed = logo !== null || accent !== current.accentToken || TEXT_FIELDS.some(([field]) => texts[field] !== initial[field]);
    const committed = phase.kind === 'committed';
    const location = committed ? phase.result?.location : undefined;

    // The guard first, so that it is cleared before the visit below.
    useUnsavedChanges(changed && !committed);

    useEffect(() => {
        if (location !== undefined) {
            markSaved();
            router.visit(location);
        }
    }, [location]);

    const refusal = phase.kind === 'rejected' ? phase.refusal : null;
    const staleRefusal = refusal?.code === STALE_VERSION ? refusal : null;
    const invalid = refusal?.code === VALIDATION_FAILED;
    const [currentRead, setCurrentRead] = useState<CurrentRead>('reading');

    // UXS-17: the current values, read again without leaving the page, so that
    // the state of the form stays (`router.reload` preserves it). Its prop
    // `current` then gives the next save its expected revision.
    useEffect(() => {
        if (staleRefusal === null) {
            return;
        }

        const failed = (): boolean => {
            setCurrentRead('failed');

            return false;
        };

        setCurrentRead('reading');
        router.reload({ only: [CURRENT_PROP], onSuccess: () => setCurrentRead('read'), onHttpException: failed, onNetworkError: failed });
    }, [staleRefusal]);

    const showCurrent = staleRefusal !== null && currentRead !== 'failed';
    const errorOf = (path: string): string | undefined => (refusal === null ? undefined : fieldError(refusal.fields[path]));
    const busy = phase.kind === 'submitting';
    const reason = !changed ? (
        t('ux.disabled.no_change')
    ) : blockedBy !== null && !busy ? (
        <>
            {t('ux.disabled.unresolved_family')} <a href={'/requests'}>{t('ux.action.open_unresolved')}</a>
        </>
    ) : undefined;

    const submit = (): void => {
        const payload: Record<string, unknown> = { accentToken: accent, logoAction: logo === null ? 'keep' : 'replace' };

        for (const [field] of TEXT_FIELDS) {
            payload[field] = field === 'shopName' || texts[field] !== '' ? texts[field] : null;
        }

        if (logo !== null) {
            payload.logo = logo;
        }

        control.submit(payload, { revisionId: current.id });
    };

    const chooseLogo = (event: ChangeEvent<HTMLInputElement>): void => {
        setLogo(event.target.files?.item(0) ?? null);
    };

    const idOf = (field: string): string => `${ids}-${field}`;
    const logoId = idOf('logo');
    const logoLabel = `${ids}-logo-label`;
    const logoButton = `${ids}-logo-button`;
    const logoHint = `${ids}-logo-hint`;
    const logoErrorId = `${ids}-logo-error`;
    const logoLabelledBy = `${logoLabel} ${logoButton}`;
    const logoError = errorOf(`${PAYLOAD_PREFIX}logo`);
    const logoDescribedBy = logoError === undefined ? logoHint : `${logoErrorId} ${logoHint}`;
    const accentGroup = `${ids}-accent`;

    // UXS-05: each message beside its field and in the summary at the top. A
    // refusal without a message this form shows still has the summary (F-4).
    const summary: FieldError[] = [];

    for (const [field, label] of TEXT_FIELDS) {
        const message = errorOf(PAYLOAD_PREFIX + field);

        if (message !== undefined) {
            summary.push({ fieldId: idOf(field), label: t(label), message });
        }
    }

    if (logoError !== undefined) {
        summary.push({ fieldId: logoId, label: t('config.profile.logo'), message: logoError });
    }

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: PROFILE, page: t('config.profile.title'), onNavigate: guard }} />}>
            {invalid && <ErrorSummary errors={summary} />}
            {staleRefusal !== null && (
                <StateNotice
                    state={STALE_STATE}
                    actions={currentRead === 'failed' ? <Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} /> : undefined}
                />
            )}
            {/* The form keeps its place, so that its file input keeps the chosen logo. */}
            <div className={showCurrent ? 'bk-grid-2' : undefined}>
                {showCurrent && (
                    <Section title={t('config.profile.current_version')}>{currentRead === 'read' ? <RevisionFields revision={current} /> : <Loading />}</Section>
                )}
                <Card>
                    <CommandForm>
                        {TEXT_FIELDS.map(([field, label]) => {
                            const hint = HINTS[field];
                            const error = errorOf(PAYLOAD_PREFIX + field);

                            return (
                                <Field
                                    key={field}
                                    id={idOf(field)}
                                    label={t(label)}
                                    optional={field !== 'shopName'}
                                    required={field === 'shopName'}
                                    hint={hint === undefined ? undefined : t(hint)}
                                    error={error}
                                >
                                    <TextInput
                                        type={field === 'email' ? 'email' : 'text'}
                                        value={texts[field]}
                                        maxLength={MAX_LENGTH[field]}
                                        autoComplete={'off'}
                                        onChange={(event) => setTexts({ ...texts, [field]: event.target.value })}
                                    />
                                </Field>
                            );
                        })}
                        <div className={'bk-field'} data-invalid={logoError !== undefined}>
                            <span className={'bk-label'} id={logoLabel}>
                                {t('config.profile.logo')}
                                <span className={'bk-label__opt'}> {t('ux.action.optional')}</span>
                            </span>
                            {current.logo !== null && logo === null && <img className={'bk-logo'} src={current.logo} alt={t('config.profile.logo')} />}
                            <span className={'bk-file'}>
                                <input
                                    id={logoId}
                                    type={'file'}
                                    accept={LOGO_TYPES}
                                    aria-labelledby={logoLabelledBy}
                                    aria-describedby={logoDescribedBy}
                                    aria-invalid={logoError === undefined ? undefined : true}
                                    onChange={chooseLogo}
                                />
                                <span className={'bk-btn bk-btn--secondary'} id={logoButton} aria-hidden={true}>
                                    {current.logo === null ? t('config.profile.choose_logo') : t('config.profile.replace_logo')}
                                </span>
                            </span>
                            {logo !== null && <p className={'bk-meta'}>{logo.name}</p>}
                            {logoError !== undefined && (
                                <p className={'bk-error'} id={logoErrorId}>
                                    <Icon name={'danger'} size={'sm'} />
                                    <span>{logoError}</span>
                                </p>
                            )}
                            <p className={'bk-hint'} id={logoHint}>
                                {t('config.profile.logo_rules')}
                            </p>
                        </div>
                        <fieldset className={'bk-field bk-accents'}>
                            <legend className={'bk-label'}>{t('config.profile.accent')}</legend>
                            {accents.filter(isAccentToken).map((token) => (
                                <label key={token} className={'bk-accent'} data-accent-option={token}>
                                    <input type={'radio'} name={accentGroup} value={token} checked={accent === token} onChange={() => setAccent(token)} />
                                    <span className={'bk-accent__dot'} aria-hidden={true} />
                                    <span className={'bk-accent__swatch'} data-accent={token} aria-hidden={true} />
                                    <span>{t(ACCENT_NAMES[token])}</span>
                                </label>
                            ))}
                        </fieldset>
                        <p className={'bk-hint'}>{t('config.profile_edit.version_help')}</p>
                        <div className={'bk-actions'}>
                            <Button label={t('config.profile_edit.submit')} size={'lg'} enterActivates={false} busy={busy} disabledReason={reason} onClick={submit} />
                            <ButtonLink href={PROFILE} label={t('ux.action.cancel')} variant={'secondary'} onClick={guard} />
                            {/* After UXS-17 the current values stay beside the form until the next save. */}
                            {((phase.kind === 'rejected' && staleRefusal === null) || phase.kind === 'not_committed') && (
                                <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />
                            )}
                        </div>
                    </CommandForm>
                </Card>
            </div>
            {/* UXS-05 has its summary above. */}
            {!invalid && <CommandPanels control={control} committed={t('config.profile_edit.success')} />}
        </CommandLayout>
    );
}

/** The message of a field's first refusal (COPY_DECK section 16), or none for a key this form does not show. */
function fieldError(entries: unknown): string | undefined {
    if (!Array.isArray(entries)) {
        return undefined;
    }

    const first: unknown = entries[0];

    if (typeof first !== 'object' || first === null || !('key' in first)) {
        return undefined;
    }

    const params: unknown = 'params' in first ? first.params : {};
    const param = (name: string): string => {
        if (typeof params !== 'object' || params === null || !(name in params)) {
            return '';
        }

        const value: unknown = Reflect.get(params, name);

        return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
    };

    switch (first.key) {
        case 'ux.validation.required':
            return t('ux.validation.required');
        case 'ux.validation.text_max':
            return t('ux.validation.text_max', { maks: param('maks') });
        case 'ux.validation.email':
            return t('ux.validation.email');
        case 'ux.validation.upload.image_type':
            return t('ux.validation.upload.image_type');
        case 'ux.validation.upload.too_large':
            return t('ux.validation.upload.too_large', { ukuran: param('ukuran') });
        case 'ux.validation.upload.too_many_pixels':
            return t('ux.validation.upload.too_many_pixels', { jumlah: param('jumlah') });
        case 'ux.validation.upload.animated':
            return t('ux.validation.upload.animated');
        case 'ux.validation.upload.unreadable':
            return t('ux.validation.upload.unreadable');
        default:
            return undefined;
    }
}
