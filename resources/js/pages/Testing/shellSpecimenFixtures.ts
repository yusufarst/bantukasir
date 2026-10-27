import type { IconName } from '@/components/ui/icons';
import type { AreaId, DestinationId, Persona, SubDestinationId } from '@/lib/navigation';

// Synthetic data of the shell specimen (TASK-007), which exists only in the
// local and testing environments (SpecimenController). No destination of the
// navigation exists yet, so the specimen gives the shell every one of them,
// with the working labels of INFORMATION_ARCHITECTURE section 4 and the icons
// of the previews, to show the side navigation, the icon rail, the drawer and
// the sub-navigation of every role. The people of Toko Contoh are invented
// (KEY_SCREENS section 5); nothing here is product copy: each page Task
// registers its destination with its own key (resources/js/lib/navigation.ts).

export const SPECIMEN_DESTINATIONS: Readonly<Record<DestinationId, { readonly label: string; readonly icon: IconName }>> = {
    home: { label: 'Beranda', icon: 'home' },
    till: { label: 'Kasir', icon: 'till' },
    sales: { label: 'Penjualan', icon: 'receipt' },
    sessions: { label: 'Sesi kas', icon: 'drawer' },
    stock: { label: 'Stok', icon: 'boxes' },
    products: { label: 'Produk', icon: 'tag' },
    reports: { label: 'Laporan', icon: 'chart' },
    settings: { label: 'Pengaturan', icon: 'sliders' },
};

export const SPECIMEN_SUB_DESTINATIONS: Readonly<Record<SubDestinationId, string>> = {
    products: 'Produk',
    categories: 'Kategori',
    csv: 'Impor produk (CSV)',
    stock: 'Stok',
    history: 'Riwayat stok',
    receipts: 'Barang masuk',
    issues: 'Barang keluar',
    onboarding: 'Stok awal',
    incidents: 'Insiden rekonsiliasi',
    daily: 'Rekap harian',
    sessions: 'Sesi kas',
    finance: 'Laporan keuangan',
    worklist: 'Biaya perlu dilengkapi',
};

export const SPECIMEN_PEOPLE: Readonly<Record<Persona, string>> = {
    owner: 'Hendra',
    ops: 'Agus',
    cashier: 'Rina',
    combined: 'Dewi',
};

export const SPECIMEN_AREAS: readonly AreaId[] = ['products', 'stock', 'reports'];

export const SPECIMEN_TEXT = {
    title: 'Spesimen kerangka',
    body: 'Isi halaman contoh di dalam kerangka aplikasi.',
    badge: '7',
    badgeLabel: '7 produk perlu restok',
};
