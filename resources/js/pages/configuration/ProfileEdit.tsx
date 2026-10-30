import { router } from '@inertiajs/react';
import { useEffect, useId, useState, type ChangeEvent } from 'react';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { CommandForm, Field, TextInput } from '@/components/ui/Field';
import { Icon } from '@/components/ui/Icon';
import type { StateId } from '@/components/ui/stateCatalogue';
import { StateNotice } from '@/components/ui/States';
import { AppShell, useLeaveGuard, useUnsavedChanges } from '@/layouts/AppShell';
import { CommandLayout, PageHeader } from '@/layouts/Page';
import { ACCENT_NAMES, TEXT_FIELDS, markSaved, type Revision, type TextField } from '@/lib/configuration/profile';
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
// multipart. A refusal shows its reason at its field (UXS-05) — a refused logo
// with the reason of SECURITY_MODEL section 13 — and a revision changed since
// the page loaded is UXS-17. Success leads to UXP-607 with the new version in
// force. Leaving with changes asks first (UXS-45).

const PROFILE_COMMAND = { operation: 'business_profile.change', family: 'profile', recentAuth: true } as const;

const PROFILE = '/settings/business-profile';

/** The destination of the navigation that holds the page (UXI-008). */
const DESTINATION: DestinationId = 'settings';

/** A revision changed since the page loaded (UXS-17). */
const STALE_STATE: StateId = 'UXS-17';

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

    const logoLabel = `${ids}-logo-label`;
    const logoButton = `${ids}-logo-button`;
    const logoHint = `${ids}-logo-hint`;
    const logoErrorId = `${ids}-logo-error`;
    const logoLabelledBy = `${logoLabel} ${logoButton}`;
    const logoError = errorOf(`${PAYLOAD_PREFIX}logo`);
    const logoDescribedBy = logoError === undefined ? logoHint : `${logoErrorId} ${logoHint}`;
    const accentGroup = `${ids}-accent`;

    return (
        <CommandLayout header={<PageHeader title={title} back={{ href: PROFILE, page: t('config.profile.title'), onNavigate: guard }} />}>
            {refusal?.code === 'STALE_VERSION' && (
                <StateNotice state={STALE_STATE} actions={<Button label={t('ux.action.reload')} variant={'secondary'} onClick={() => window.location.reload()} />} />
            )}
            <Card>
                <CommandForm>
                    {TEXT_FIELDS.map(([field, label]) => {
                        const hint = HINTS[field];
                        const error = errorOf(PAYLOAD_PREFIX + field);

                        return (
                            <Field key={field} label={t(label)} optional={field !== 'shopName'} required={field === 'shopName'} hint={hint === undefined ? undefined : t(hint)} error={error}>
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
                        {(phase.kind === 'rejected' || phase.kind === 'not_committed') && <Button label={t('ux.action.edit_again')} variant={'quiet'} onClick={control.startAgain} />}
                    </div>
                </CommandForm>
            </Card>
            <CommandPanels control={control} committed={t('config.profile_edit.success')} />
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
