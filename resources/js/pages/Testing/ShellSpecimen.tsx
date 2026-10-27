import { Head } from '@inertiajs/react';
import { AnnouncerProvider } from '@/components/ui/Announcer';
import { Card } from '@/components/ui/Card';
import { ShellFrame, type NavItem, type ShellLayout, type SubNavItem } from '@/layouts/AppShell';
import { PageContainer, PageHeader } from '@/layouts/Page';
import { PRIMARY_ORDER, SUB_ORDER, type AreaId, type Persona } from '@/lib/navigation';
import { SPECIMEN_AREAS, SPECIMEN_DESTINATIONS, SPECIMEN_PEOPLE, SPECIMEN_SUB_DESTINATIONS, SPECIMEN_TEXT } from './shellSpecimenFixtures';

// The specimen of the application shell (TASK-007), only in the local and
// testing environments: the frame of UXP-000 with every destination of a role,
// in the order of INFORMATION_ARCHITECTURE section 4, for the browser tests of
// the side navigation, the icon rail of the till pages (?layout=rail), the
// compact top bar and its drawer, and the sub-navigation of an area
// (?area=stock). ?peran chooses owner, ops, cashier or combined. Its links lead
// nowhere and "Keluar" does nothing: it is not a page of the application.

const PERSONAS: readonly Persona[] = ['owner', 'ops', 'cashier', 'combined'];

function chosen<T extends string>(values: readonly T[], value: string | null, fallback: T): T {
    return values.find((candidate) => candidate === value) ?? fallback;
}

export default function ShellSpecimen() {
    const query = new URLSearchParams(window.location.search);
    const persona = chosen(PERSONAS, query.get('peran'), 'owner');
    const layout: ShellLayout = query.get('layout') === 'rail' ? 'rail' : 'side';
    const area: AreaId | null = SPECIMEN_AREAS.find((candidate) => candidate === query.get('area')) ?? null;
    const order = PRIMARY_ORDER[persona];
    const current = area ?? order[0];

    const items: NavItem[] = order.map((id) => ({
        id,
        label: SPECIMEN_DESTINATIONS[id].label,
        href: '#',
        icon: SPECIMEN_DESTINATIONS[id].icon,
        current: id === current,
        badge: id === 'stock' ? { count: SPECIMEN_TEXT.badge, label: SPECIMEN_TEXT.badgeLabel } : undefined,
    }));

    const subOrder = area === null ? undefined : SUB_ORDER[area][persona];
    const subItems: SubNavItem[] = (subOrder ?? []).map((id, index) => ({ id, label: SPECIMEN_SUB_DESTINATIONS[id], href: '#', current: index === 0 }));

    return (
        <AnnouncerProvider>
            <Head title={SPECIMEN_TEXT.title} />
            <ShellFrame
                title={SPECIMEN_TEXT.title}
                layout={layout}
                accountName={SPECIMEN_PEOPLE[persona]}
                items={items}
                subNav={area === null ? undefined : { area: SPECIMEN_DESTINATIONS[area].label, items: subItems }}
                onNavigate={(event) => event.preventDefault()}
                onSignOut={() => undefined}
            >
                <PageContainer>
                    <div className={'bk-stack'}>
                        <PageHeader title={SPECIMEN_TEXT.title} />
                        <Card>
                            <p>{SPECIMEN_TEXT.body}</p>
                        </Card>
                    </div>
                </PageContainer>
            </ShellFrame>
        </AnnouncerProvider>
    );
}
