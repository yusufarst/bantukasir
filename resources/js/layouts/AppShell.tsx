import { Head, router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, useSyncExternalStore, type MouseEvent, type ReactNode } from 'react';
import { AnnouncerProvider, announcements, useAnnounce } from '@/components/ui/Announcer';
import { Button, ButtonLink } from '@/components/ui/Button';
import { Dialog, Drawer, UnsavedDialog } from '@/components/ui/Dialog';
import { Icon } from '@/components/ui/Icon';
import type { IconName } from '@/components/ui/icons';
import { Menu, type MenuEntry } from '@/components/ui/Menu';
import { Notice } from '@/components/ui/Notice';
import { LoadFailed, Loading, StaleRead, StateNotice } from '@/components/ui/States';
import type { StateId } from '@/components/ui/stateCatalogue';
import { useWidthClass } from '@/layouts/widthClass';
import { NO_CONNECTION, retrySeconds, stateOfStatus } from '@/lib/answers';
import { clearBrowserData, followOtherTab, leaveSignedInSession, listenToOtherTabs, reportActivity, shareDeadlines, signOut } from '@/lib/browserSession';
import { USER_REQUEST_EVENT } from '@/lib/commands/client';
import { countOf, refreshIndicators, useShellRefresh, useUnresolved } from '@/lib/commands/unresolved';
import { formatCount, formatDateTime, formatDuration, formatTimeOfDay } from '@/lib/format';
import { t } from '@/lib/i18n';
import { DESTINATIONS, SUB_DESTINATIONS, navigationFor, subNavigationFor, type AreaId, type DestinationId, type RoleCode, type SubDestinationId } from '@/lib/navigation';
import { useOnline } from '@/lib/online';
import { SIGN_OUT_WAIT_SECONDS, afterActivity, deadlinesOf, laterOf, mayReportActivity, timeLeft, viewAt, type Deadlines, type SessionTimes } from '@/lib/sessionTimes';

// UXP-000, the application shell (DESIGN_SYSTEM 7.2; INFORMATION_ARCHITECTURE
// sections 4 and 5; NAVIGATION_CONTRACTS UXP-000; INTERACTION_INVENTORY
// UXI-022 to UXI-038; COPY_DECK sections 4 and 6.1; ADAPTIVE_AND_ACCESSIBILITY
// UXA-03, UXA-13, UXA-21; SECURITY_MODEL SEC-SESS-02, SEC-CH-04; TASK-007).
// Every protected page renders inside it: the side navigation with labels at
// medium and expanded width, the icon rail on the till pages, the compact top
// bar with the drawer, the sub-navigation, the account menu with "Akun saya"
// and "Keluar", the banner region, the landmarks and the document title. It
// mirrors both limits of the sign-in session with its own timers, warns before
// the idle limit (UXS-23, "Tetap masuk"), tells when the 12-hour limit ends,
// and clears the history key and the browser's data at sign-out, at either
// limit and on a change of user; from the start of a sign-out in any tab of
// the browser, every signed-in page hides until its next page loads; a page
// restored from the back-forward cache is fetched again. TASK-010 adds the
// shop's initials and name at the top of the side navigation and the drawer,
// the shop's name in the document title, and its accent (app.tsx). TASK-009
// adds the shell's indicators
// (RD-03): the account-menu item "Permintaan belum pasti" with its number
// (UXI-025), the banner (UXI-029) and the sign-out warning (UXI-027,
// UXI-028), read from the references of this browser and the user's
// receipts, with the last data kept when a read fails (UXS-21).

/** The shared props of a signed-in page (SEC-CH-02; App\DTO\Access\SignedInData). */
export interface SignedIn {
    readonly user: { readonly id: number; readonly displayName: string; readonly roles: readonly RoleCode[]; readonly capabilities: readonly string[] };
    readonly session: SessionTimes;
}

/**
 * The shop of a signed-in page (SEC-CH-02 as TASK-010 amends it;
 * App\DTO\Configuration\ShopData): the name and initials of the
 * BusinessProfile in force, null before the bootstrap, and its accent, which
 * app.tsx applies before the page renders.
 */
export interface Shop {
    readonly name: string | null;
    readonly initials: string | null;
    readonly accent: string;
}

/** The shop as the shell shows it: its initials and name (DESIGN_SYSTEM 7.2). */
export interface ShopBrand {
    readonly name: string;
    readonly initials: string;
}

/** The shop's brand of the shell, or null before the bootstrap. */
export function brandOf(shop: Shop | null | undefined): ShopBrand | null {
    return shop === null || shop === undefined || shop.name === null || shop.initials === null ? null : { name: shop.name, initials: shop.initials };
}

/** The document title: ":halaman — :toko" with the shop's name, the page label alone without it (UXA-13). */
export function documentTitle(title: string, brand: ShopBrand | null): string {
    return brand === null ? t('ux.shell.document_title_public', { halaman: title }) : t('ux.shell.document_title', { halaman: title, toko: brand.name });
}

/** A primary destination as the frame shows it. */
export interface NavItem {
    readonly id: string;
    readonly label: string;
    readonly href: string;
    readonly icon: IconName;
    readonly current: boolean;
    /** The badge's number and its accessible name (UXI-006, UXI-017). */
    readonly badge?: { readonly count: string; readonly label: string };
}

/** An item of a sub-navigation as the frame shows it. */
export interface SubNavItem {
    readonly id: string;
    readonly label: string;
    readonly href: string;
    readonly current: boolean;
    readonly badge?: { readonly count: string; readonly label: string };
}

export type ShellLayout = 'side' | 'rail';

// ---------------------------------------------------------------------------
// The unsaved-changes guard (UXI-038, UXS-45; NAVIGATION_CONTRACTS NC-03)
// ---------------------------------------------------------------------------

interface LeaveGuard {
    readonly mark: (dirty: boolean, body: string | undefined) => void;
    readonly onLink: (event: MouseEvent<HTMLAnchorElement>) => void;
    readonly attempt: (proceed: () => void) => void;
}

const LeaveGuardContext = createContext<LeaveGuard | null>(null);

/**
 * A create, edit or command page tells the shell whether data was entered and
 * not sent; leaving it then asks first, "Tetap di halaman ini" being the
 * default (UXS-45). `body` is the page's own text of what will be lost.
 */
export function useUnsavedChanges(dirty: boolean, body?: string): void {
    const guard = useContext(LeaveGuardContext);

    useEffect(() => {
        guard?.mark(dirty, body);

        return () => guard?.mark(false, undefined);
    }, [guard, dirty, body]);
}

/** The click guard of a link that leaves the page: the page's back and cancel links use it. */
export function useLeaveGuard(): (event: MouseEvent<HTMLAnchorElement>) => void {
    const guard = useContext(LeaveGuardContext);

    return guard?.onLink ?? (() => undefined);
}

function isModifiedClick(event: MouseEvent<HTMLAnchorElement>): boolean {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
}

// ---------------------------------------------------------------------------
// The sign-in session's timers, one per document (SEC-SESS-02)
// ---------------------------------------------------------------------------

let deadlines: Deadlines | null = null;
let idleLimit = 0;
let lastUserRequestAt = 0;
let knownUserId: number | null = null;
let installed = false;
let leaving = false;
/** A sign-out has started in this browser: the page shows nothing of the user until the next page loads. */
let hidden = false;
const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

function setDeadlines(next: Deadlines, share: boolean): void {
    deadlines = next;
    notify();

    if (share && knownUserId !== null) {
        shareDeadlines(knownUserId, next.idleAt, next.absoluteAt);
    }
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function hidePage(): void {
    hidden = true;
    notify();
}

/** Leaves once, whichever timer, tab or answer ends the sign-in session first. */
function leaveOnce(): void {
    if (!leaving) {
        leaving = true;
        leaveSignedInSession();
    }
}

/**
 * Signs out once (UXI-026, UXI-035): the page hidden at once, and nothing
 * else in this document — a timer, another tab, a second click — acts on the
 * sign-in session until the server's answer loads the next page.
 */
function signOutOnce(): void {
    if (!leaving) {
        leaving = true;
        hidePage();
        signOut();
    }
}

/**
 * Another tab has started a sign-out: this tab's history key and storage are
 * cleared and its page hidden at once, but it loads the application's address
 * only when that tab reports the server's answer, because until then the
 * sign-in session is still valid and that address answers with the home and
 * the user's data (SEC-CH-04, THR-18; pull request #11's review). Without
 * that report — the tab closed, its connection lost — it loads it after
 * SIGN_OUT_WAIT_SECONDS, and the server's answer then is the truth: a report
 * that comes later still reaches the page it loaded.
 */
function hideForOtherTab(): void {
    if (leaving || hidden) {
        return;
    }

    clearBrowserData();
    hidePage();
    window.setTimeout(followOnce, SIGN_OUT_WAIT_SECONDS * 1000);
}

function installDocumentListeners(): void {
    if (installed) {
        return;
    }

    installed = true;
    lastUserRequestAt = Date.now();

    // A user-initiated visit counts as activity; a background one never does (RT-10).
    router.on('start', (event) => {
        if (event.detail.visit.headers['X-Background-Request'] !== '1') {
            lastUserRequestAt = Date.now();
        }
    });

    // A request of the command client that the user started — "Kirim ulang",
    // "Cek status", a final action — counted as user activity on the server:
    // the idle limit counts from its answer, as after "Tetap masuk", so that
    // the timer never ends a page whose sign-in session the server kept
    // (SEC-SESS-02; pull request #15's review). Background requests never
    // send it (RT-10).
    window.addEventListener(USER_REQUEST_EVENT, () => {
        lastUserRequestAt = Date.now();

        if (deadlines !== null && !leaving) {
            setDeadlines(afterActivity(deadlines, Date.now(), idleLimit), true);
        }
    });

    // While the user types in a form, at most one user-activity request a minute.
    document.addEventListener(
        'input',
        () => {
            const now = Date.now();

            if (deadlines === null || !mayReportActivity(now, lastUserRequestAt)) {
                return;
            }

            lastUserRequestAt = now;
            void reportActivity().then((answer) => {
                if (answer === 'kept' && deadlines !== null) {
                    setDeadlines(afterActivity(deadlines, Date.now(), idleLimit), true);
                } else if (answer === 'ended') {
                    leaveOnce();
                }
            });
        },
        true,
    );

    // A page restored from the back-forward cache is fetched again, which
    // needs a valid sign-in session (SEC-CH-04).
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // Another tab of this browser started a sign-out, ended the sign-in
    // session, or learned its deadlines. Deadlines of another user mean that
    // this tab's user is no longer the browser's: another person has signed
    // in, so nothing of this tab's user may stay (SEC-CH-04, THR-18; review
    // finding R-27).
    listenToOtherTabs(hideForOtherTab, followOnce, (userId, idleAt, absoluteAt) => {
        if (knownUserId === null) {
            return;
        }

        if (userId !== knownUserId) {
            followOnce();

            return;
        }

        setDeadlines(laterOf(deadlines, { idleAt, absoluteAt }), false);
    });
}

/** Leaves once because another tab ended the sign-in session, or because another user signed in there. */
function followOnce(): void {
    if (!leaving) {
        leaving = true;
        followOtherTab();
    }
}

/** The current time, every second. */
function useNow(): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    return now;
}

/**
 * Feeds the timers with each answer's limits, checks for a change of user and
 * ends the sign-in session in the browser when a limit is reached.
 */
function useSignInSession(signedIn: SignedIn): { now: number; current: Deadlines | null } {
    useEffect(() => {
        installDocumentListeners();
    }, []);

    useEffect(() => {
        if (knownUserId !== null && knownUserId !== signedIn.user.id) {
            // A change of user: nothing of the earlier one may stay (SEC-CH-04).
            clearBrowserData();
            window.location.reload();

            return;
        }

        knownUserId = signedIn.user.id;
        idleLimit = signedIn.session.idleLimit;
        setDeadlines(deadlinesOf(signedIn.session, Date.now()), true);
    }, [signedIn]);

    const current = useSyncExternalStore(subscribe, () => deadlines);
    const now = useNow();

    useEffect(() => {
        if (current !== null && viewAt(now, current).kind === 'ended') {
            leaveOnce();
        }
    }, [now, current]);

    return { now, current };
}

/** The sign-in-ending dialog (UXS-23; UXI-034 "Tetap masuk", UXI-035 "Keluar"). */
function SessionWarning({ secondsLeft, onSignOut }: { secondsLeft: number | null; onSignOut: () => void }) {
    const announce = useAnnounce();
    const open = secondsLeft !== null;
    const [sending, setSending] = useState(false);
    const left = timeLeft(secondsLeft ?? 0);
    const duration = formatDuration(left.amount, left.unit);
    const latest = useRef(duration);

    latest.current = duration;

    // Announced once when it opens, not at every second (UXA-17).
    useEffect(() => {
        if (open) {
            announce(announcements.sessionWarning(latest.current), true);
        }
    }, [open, announce]);

    const stay = (): void => {
        if (sending) {
            return;
        }

        setSending(true);
        lastUserRequestAt = Date.now();
        void reportActivity().then((answer) => {
            setSending(false);

            if (answer === 'kept' && deadlines !== null) {
                setDeadlines(afterActivity(deadlines, Date.now(), idleLimit), true);
            } else if (answer === 'ended') {
                leaveOnce();
            }
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? undefined : stay())}
            title={t('ux.state.session_warning.title')}
            description={<p>{t('ux.state.session_warning.body', { durasi: duration })}</p>}
            actions={
                <>
                    <Button label={t('access.sign_out')} variant={'secondary'} size={'lg'} onClick={onSignOut} />
                    <Button label={t('ux.state.session_warning.stay')} size={'lg'} busy={sending} data-initial-focus={true} onClick={stay} />
                </>
            }
        />
    );
}

// ---------------------------------------------------------------------------
// The frame
// ---------------------------------------------------------------------------

function NavList({ items, kind, onNavigate }: { items: readonly NavItem[]; kind: 'side' | 'rail'; onNavigate: (event: MouseEvent<HTMLAnchorElement>) => void }) {
    return (
        <ul className={kind === 'rail' ? 'bk-rail__list' : 'bk-nav__list'} role={'list'}>
            {items.map((item) => (
                <li key={item.id}>
                    <a className={kind === 'rail' ? 'bk-rail__link' : 'bk-nav__link'} href={item.href} aria-current={item.current ? 'page' : undefined} onClick={onNavigate}>
                        <Icon name={item.icon} size={'lg'} />
                        {kind === 'rail' ? <span className={'bk-tip'}>{item.label}</span> : <span className={'bk-nav__label'}>{item.label}</span>}
                        {item.badge !== undefined && (
                            <>
                                <span className={kind === 'rail' ? 'bk-rail__dot' : 'bk-badge'} aria-hidden={true}>
                                    {item.badge.count}
                                </span>
                                <span className={'bk-sr-only'}>{item.badge.label}</span>
                            </>
                        )}
                    </a>
                </li>
            ))}
        </ul>
    );
}

function SubNavigation({ area, items, compact, onNavigate }: { area: string; items: readonly SubNavItem[]; compact: boolean; onNavigate: (event: MouseEvent<HTMLAnchorElement>) => void }) {
    const label = t('ux.nav.sub', { halaman: area });

    if (compact) {
        const current = items.find((item) => item.current) ?? items[0];
        const entries = items.map((item): MenuEntry => ({ kind: 'link', label: item.label, href: item.href, onNavigate }));

        return (
            <nav className={'bk-submenu'} aria-label={label}>
                <Menu label={current?.label ?? area} variant={'secondary'} triggerClassName={SUBMENU_TRIGGER} items={entries} />
            </nav>
        );
    }

    return (
        <nav className={'bk-subnav'} aria-label={label}>
            <ul className={'bk-subnav__list'} role={'list'}>
                {items.map((item) => (
                    <li key={item.id}>
                        <a className={'bk-subnav__link'} href={item.href} aria-current={item.current ? 'page' : undefined} onClick={onNavigate}>
                            <span>{item.label}</span>
                            {item.badge !== undefined && (
                                <>
                                    <span className={'bk-badge'} aria-hidden={true}>
                                        {item.badge.count}
                                    </span>
                                    <span className={'bk-sr-only'}>{item.badge.label}</span>
                                </>
                            )}
                        </a>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

type AccountPlace = 'side' | 'rail' | 'top';

/** Where the account menu opens from each place of its button: up from the foot of the side navigation. */
const ACCOUNT_MENU_SIDE: Readonly<Record<AccountPlace, 'top' | 'right' | 'bottom'>> = { side: 'top', rail: 'right', top: 'bottom' };

const ACCOUNT_MENU_ALIGN: Readonly<Record<AccountPlace, 'start' | 'end'>> = { side: 'start', rail: 'start', top: 'end' };

const ACCOUNT_TRIGGER = 'bk-account__btn';

const SUBMENU_TRIGGER = 'bk-submenu__btn';

/**
 * The account menu (UXI-023), the same for every role: "Akun saya" (UXI-024)
 * and "Keluar" (UXI-026). Its button shows the user icon in the avatar's
 * circle, and the display name beside it in the side navigation; its
 * accessible name "Menu akun :nama" contains every text it shows (WCAG 2.5.3),
 * which initials drawn as text would not.
 */
function AccountMenu({
    name,
    kind,
    unresolved,
    onNavigate,
    onSignOut,
}: {
    name: string;
    kind: AccountPlace;
    unresolved: number | null;
    onNavigate: (event: MouseEvent<HTMLAnchorElement>) => void;
    onSignOut: () => void;
}) {
    const items: MenuEntry[] = [
        { kind: 'link', label: t('access.account.title'), icon: 'user', href: '/account', onNavigate },
        // UXI-025, only while the user has an unresolved request (TASK-009).
        ...(unresolved !== null && unresolved > 0
            ? [{ kind: 'link', label: t('commands.unresolved.menu_item', { jumlah: formatCount(unresolved) }), icon: 'warning', href: UNRESOLVED_PAGE, onNavigate } satisfies MenuEntry]
            : []),
        { kind: 'separator' },
        { kind: 'action', label: t('access.sign_out'), icon: 'logout', danger: true, onSelect: onSignOut },
    ];

    return (
        <div className={'bk-account'}>
            <Menu
                label={t('ux.shell.account_menu', { nama: name })}
                iconOnly={true}
                variant={'quiet'}
                side={ACCOUNT_MENU_SIDE[kind]}
                align={ACCOUNT_MENU_ALIGN[kind]}
                triggerClassName={ACCOUNT_TRIGGER}
                items={items}
            >
                <span className={'bk-avatar'} aria-hidden={true}>
                    <Icon name={'user'} />
                </span>
                {kind === 'side' && <span className={'bk-account__name'}>{name}</span>}
            </Menu>
        </div>
    );
}

/**
 * The shop's initials and name at the top of the side navigation and of the
 * drawer (DESIGN_SYSTEM 7.2; TASK-010): the initials are decoration beside the
 * name, which is read.
 */
function Brand({ brand }: { brand: ShopBrand }) {
    return (
        <div className={'bk-brand'}>
            <span className={'bk-brand__mark'} aria-hidden={true}>
                {brand.initials}
            </span>
            <span className={'bk-brand__name'}>{brand.name}</span>
        </div>
    );
}

/**
 * The frame of the shell, without its data: the specimen gives it synthetic
 * destinations (TASK-007), the application its registered ones (AppShell).
 */
export function ShellFrame({
    title,
    layout = 'side',
    accountName,
    brand = null,
    items,
    subNav,
    banners,
    unresolved = null,
    onNavigate,
    onSignOut,
    children,
}: {
    title: string;
    layout?: ShellLayout;
    accountName: string;
    /** The shop's initials and name (TASK-010), null before the bootstrap. */
    brand?: ShopBrand | null;
    items: readonly NavItem[];
    subNav?: { readonly area: string; readonly items: readonly SubNavItem[] };
    banners?: ReactNode;
    /** The number of the user's unresolved requests for the account menu (UXI-025), null while unknown. */
    unresolved?: number | null;
    onNavigate: (event: MouseEvent<HTMLAnchorElement>) => void;
    onSignOut: () => void;
    children: ReactNode;
}) {
    const width = useWidthClass();
    const compact = width === 'compact';
    const [drawer, setDrawer] = useState(false);
    const navLabel = t('ux.nav.main');
    const hasNav = items.length > 0;
    const sub = subNav !== undefined && subNav.items.length > 0 ? <SubNavigation area={subNav.area} items={subNav.items} compact={compact} onNavigate={onNavigate} /> : null;
    const main = (
        <main id={'main'} className={'bk-main'} tabIndex={-1}>
            {sub}
            {children}
        </main>
    );

    if (compact) {
        return (
            <div className={'bk-app bk-app--compact'} data-layout={'none'}>
                <div className={'bk-col'}>
                    <header className={'bk-topbar'}>
                        {hasNav ? (
                            <Button label={t('ux.nav.menu_open')} icon={'menu'} iconOnly={true} variant={'quiet'} aria-expanded={drawer} aria-haspopup={'dialog'} onClick={() => setDrawer(true)} />
                        ) : (
                            <span aria-hidden={true} />
                        )}
                        <p className={'bk-topbar__title'}>{title}</p>
                        <AccountMenu name={accountName} kind={'top'} unresolved={unresolved} onNavigate={onNavigate} onSignOut={onSignOut} />
                    </header>
                    {banners !== undefined && <aside className={'bk-banners'}>{banners}</aside>}
                    {main}
                </div>
                {hasNav && (
                    <Drawer open={drawer} onOpenChange={setDrawer} title={navLabel}>
                        {brand !== null && <Brand brand={brand} />}
                        <nav className={'bk-nav'} aria-label={navLabel}>
                            <NavList items={items} kind={'side'} onNavigate={onNavigate} />
                        </nav>
                    </Drawer>
                )}
            </div>
        );
    }

    return (
        <div className={'bk-app'} data-layout={layout}>
            <header className={layout === 'rail' ? 'bk-rail' : 'bk-sidenav'}>
                {layout === 'side' && brand !== null && <Brand brand={brand} />}
                {hasNav && (
                    <nav className={'bk-nav'} aria-label={navLabel}>
                        <NavList items={items} kind={layout} onNavigate={onNavigate} />
                    </nav>
                )}
                <AccountMenu name={accountName} kind={layout} unresolved={unresolved} onNavigate={onNavigate} onSignOut={onSignOut} />
            </header>
            <div className={'bk-col'}>
                {banners !== undefined && <aside className={'bk-banners'}>{banners}</aside>}
                {main}
            </div>
        </div>
    );
}

/** The state of an answer that no page handled, shown in the banner region (TASK-007 scope item 9). */
interface Unhandled {
    readonly state: StateId;
    readonly retryAfter: bigint | null;
}

/** The states that the shell itself shows (UI_STATES section 6, UXP-000). */
const SHELL_STATES = {
    reload: 'UXS-10',
    throttled: 'UXS-12',
    offline: 'UXS-20',
    loadFailed: 'UXS-22',
    absoluteLimit: 'UXS-23-absolute',
} as const satisfies Readonly<Record<string, StateId>>;

function UnhandledNotice({ unhandled }: { unhandled: Unhandled }) {
    const reloadPage = (): void => window.location.reload();

    switch (unhandled.state) {
        case SHELL_STATES.reload:
            return <StateNotice state={SHELL_STATES.reload} actions={<Button label={t('ux.action.reload')} variant={'secondary'} onClick={reloadPage} />} />;
        case SHELL_STATES.throttled: {
            const body = t('ux.state.throttled.body', { durasi: formatDuration(unhandled.retryAfter ?? 60n, 'second') });

            return <StateNotice state={SHELL_STATES.throttled} body={body} />;
        }
        case SHELL_STATES.loadFailed:
            return <StateNotice state={SHELL_STATES.loadFailed} actions={<Button label={t('ux.action.retry')} variant={'secondary'} onClick={reloadPage} />} />;
        default:
            return <StateNotice state={unhandled.state} />;
    }
}

/** RP-006, the page of unresolved requests (TASK-009). */
const UNRESOLVED_PAGE = '/requests';

/**
 * The banner of UXI-029 (COPY_DECK section 6.1): while the user has an
 * unresolved request, from the references of this browser and the receipts
 * of the server, with "Buka Permintaan belum pasti"; not on that page itself.
 */
function UnresolvedBanner({ count, hidden: onPage, onNavigate }: { count: number | null; hidden: boolean; onNavigate: (event: MouseEvent<HTMLAnchorElement>) => void }) {
    if (count === null || count === 0 || onPage) {
        return null;
    }

    return (
        <Notice tone={'warning'} actions={<ButtonLink href={UNRESOLVED_PAGE} label={t('ux.action.open_unresolved')} variant={'secondary'} onClick={onNavigate} />}>
            <p>{t('commands.unresolved.banner')}</p>
        </Notice>
    );
}

/**
 * A failed read of the shell's indicators (INTERACTION_CONTRACTS section 3.3):
 * the last data stay, with their time (UXS-21); without an earlier read, the
 * area could not be loaded (UXS-22). Never a zero.
 */
function IndicatorsNotice({ asOf, failed }: { asOf: string | null; failed: boolean }) {
    if (!failed) {
        return null;
    }

    const retry = (): void => void refreshIndicators();

    return asOf === null ? <LoadFailed onRetry={retry} /> : <StaleRead at={formatDateTime(asOf)} onReload={retry} />;
}

/**
 * The sign-out warning (IC-10; UXI-027 "Buka Permintaan belum pasti", the
 * primary, and UXI-028 "Tetap keluar", which signs out and keeps the
 * references; SEC-CH-05; COPY_DECK section 6.1).
 */
function SignOutWarning({ open, onClose, onLeave }: { open: boolean; onClose: () => void; onLeave: () => void }) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? undefined : onClose())}
            title={t('access.sign_out_warning.title')}
            description={<p>{t('access.sign_out_warning.body')}</p>}
            actions={
                <>
                    <Button label={t('access.sign_out_warning.leave')} variant={'secondary'} onClick={onLeave} />
                    <ButtonLink href={UNRESOLVED_PAGE} label={t('ux.action.open_unresolved')} variant={'primary'} />
                </>
            }
        />
    );
}

/**
 * A signed-in page from the start of a sign-out in this browser until its next
 * page loads: nothing of the user, of the page or of its dialogs, only the
 * loading state UXS-01 (SEC-CH-04, THR-18; pull request #11's review).
 */
function SignOutCover({ title }: { title: string }) {
    return (
        <>
            <Head title={t('ux.shell.document_title_public', { halaman: title })} />
            <div className={'bk-app'} data-layout={'none'}>
                <div className={'bk-col'}>
                    <main id={'main'} className={'bk-main'} tabIndex={-1}>
                        <Loading />
                    </main>
                </div>
            </div>
        </>
    );
}

/**
 * The shell of a signed-in page, with the data of the sign-in session: the
 * page gives its title — the document title and the compact top bar's —
 * and, on the till, the rail layout; with its destination and area once
 * they exist.
 */
export function AppShell({
    title,
    layout = 'side',
    current,
    area,
    subCurrent,
    children,
}: {
    title: string;
    layout?: ShellLayout;
    current?: DestinationId;
    area?: AreaId;
    subCurrent?: SubDestinationId;
    children: ReactNode;
}) {
    const props = usePage<{ auth: SignedIn | null; shop?: Shop | null }>().props;
    const signedIn = props.auth;

    if (signedIn === null) {
        throw new Error('A page of the shell needs a sign-in session (SEC-CH-02).');
    }

    return (
        <AnnouncerProvider>
            <SignedInShell signedIn={signedIn} brand={brandOf(props.shop)} title={title} layout={layout} current={current} area={area} subCurrent={subCurrent}>
                {children}
            </SignedInShell>
        </AnnouncerProvider>
    );
}

function SignedInShell({
    signedIn,
    brand,
    title,
    layout,
    current,
    area,
    subCurrent,
    children,
}: {
    signedIn: SignedIn;
    brand: ShopBrand | null;
    title: string;
    layout: ShellLayout;
    current?: DestinationId;
    area?: AreaId;
    subCurrent?: SubDestinationId;
    children: ReactNode;
}) {
    const { now, current: times } = useSignInSession(signedIn);
    const online = useOnline();
    const [unhandled, setUnhandled] = useState<Unhandled | null>(null);
    const dirty = useRef<{ dirty: boolean; body: string | undefined }>({ dirty: false, body: undefined });
    const [leavingTo, setLeavingTo] = useState<{ proceed: () => void; body: string | undefined } | null>(null);
    const bypass = useRef(false);

    // An answer that no page handled: the error page of an Inertia answer
    // renders itself; anything else is shown here (TASK-007 scope item 9).
    useEffect(() => {
        const offException = router.on('httpException', (event) => {
            const response = event.detail.response;

            if (response.headers['x-inertia'] !== undefined) {
                return;
            }

            event.preventDefault();
            setUnhandled({ state: stateOfStatus(response.status), retryAfter: retrySeconds(response.headers['retry-after']) });
        });
        const offNetwork = router.on('networkError', (event) => {
            event.preventDefault();
            setUnhandled({ state: NO_CONNECTION, retryAfter: null });
        });
        const offBefore = router.on('before', (event) => {
            const visit = event.detail.visit;

            // A partial reload stays on the page, with the state of its form:
            // it leaves nothing (UXS-17 on UXP-608).
            if (bypass.current || !dirty.current.dirty || visit.method !== 'get' || visit.prefetch || visit.only.length > 0) {
                return;
            }

            event.preventDefault();
            setLeavingTo({ proceed: () => router.visit(visit.url), body: dirty.current.body });
        });

        return () => {
            offException();
            offNetwork();
            offBefore();
        };
    }, []);

    const attempt = useCallback((proceed: () => void): void => {
        if (dirty.current.dirty) {
            setLeavingTo({ proceed, body: dirty.current.body });
        } else {
            proceed();
        }
    }, []);

    const guard = useMemo<LeaveGuard>(
        () => ({
            mark: (isDirty, body) => {
                dirty.current = { dirty: isDirty, body };
            },
            onLink: (event) => {
                if (!dirty.current.dirty || isModifiedClick(event)) {
                    return;
                }

                event.preventDefault();
                const href = event.currentTarget.href;
                setLeavingTo({ proceed: () => window.location.assign(href), body: dirty.current.body });
            },
            attempt,
        }),
        [attempt],
    );

    // The shell's indicators (RD-03) and the user's unresolved requests: the
    // account-menu item, the banner and the sign-out warning (TASK-009).
    useShellRefresh(signedIn.user.id, leaveOnce);
    const requests = useUnresolved();
    const onUnresolvedPage = usePage().url.split('?')[0] === UNRESOLVED_PAGE;
    const [warnSignOut, setWarnSignOut] = useState(false);
    const unresolvedCount = countOf(requests.serverCount, requests.union);

    const pageHidden = useSyncExternalStore(subscribe, () => hidden);

    if (pageHidden) {
        return <SignOutCover title={title} />;
    }

    const view = times === null ? null : viewAt(now, times);
    const user = signedIn.user;
    const items: NavItem[] = navigationFor(user.roles, user.capabilities, DESTINATIONS).map(({ id, entry }) => ({
        id,
        label: t(entry.label),
        href: entry.href,
        icon: entry.icon,
        current: id === current,
    }));
    const subItems: SubNavItem[] =
        area === undefined
            ? []
            : subNavigationFor(area, user.roles, user.capabilities, SUB_DESTINATIONS[area]).map(({ id, entry }) => ({ id, label: t(entry.label), href: entry.href, current: id === subCurrent }));
    const areaEntry = area === undefined ? undefined : DESTINATIONS[area];
    // "Keluar" (UXI-026): with an unresolved request the sign-out warning opens
    // first (IC-10; SEC-CH-05).
    const onSignOut = (): void => attempt(() => ((unresolvedCount ?? 0) > 0 || requests.union.length > 0 ? setWarnSignOut(true) : signOutOnce()));

    const absoluteBody = t('ux.state.session_warning.absolute.body', { jam: formatTimeOfDay(signedIn.session.absoluteEndsAt) });
    const banners = (
        <>
            {!online && <StateNotice state={SHELL_STATES.offline} />}
            {unhandled !== null && <UnhandledNotice unhandled={unhandled} />}
            {view !== null && view.kind !== 'ended' && view.absoluteNotice && <StateNotice state={SHELL_STATES.absoluteLimit} body={absoluteBody} />}
            <UnresolvedBanner count={unresolvedCount} hidden={onUnresolvedPage} onNavigate={guard.onLink} />
            <IndicatorsNotice asOf={requests.asOf} failed={requests.failed} />
        </>
    );

    return (
        <LeaveGuardContext.Provider value={guard}>
            <Head title={documentTitle(title, brand)} />
            <ShellFrame
                title={title}
                layout={layout}
                accountName={user.displayName}
                brand={brand}
                items={items}
                subNav={areaEntry === undefined ? undefined : { area: t(areaEntry.label), items: subItems }}
                banners={banners}
                unresolved={unresolvedCount}
                onNavigate={guard.onLink}
                onSignOut={onSignOut}
            >
                {children}
            </ShellFrame>
            <SessionWarning secondsLeft={view?.kind === 'warning' ? view.secondsLeft : null} onSignOut={signOutOnce} />
            <SignOutWarning
                open={warnSignOut}
                onClose={() => setWarnSignOut(false)}
                onLeave={() => {
                    setWarnSignOut(false);
                    signOutOnce();
                }}
            />
            <UnsavedDialog
                open={leavingTo !== null}
                body={leavingTo?.body}
                onStay={() => setLeavingTo(null)}
                onLeave={() => {
                    const proceed = leavingTo?.proceed;

                    setLeavingTo(null);
                    bypass.current = true;
                    proceed?.();
                }}
            />
        </LeaveGuardContext.Provider>
    );
}
