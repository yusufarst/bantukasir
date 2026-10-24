// Synthetic data of the component specimen (TASK-005), which exists only in the
// local and testing environments (SpecimenController). The shop "Toko Contoh"
// and its stationery goods are invented (KEY_SCREENS section 5); nothing here
// is product copy. The specimen marks its own section headings and the
// fixture-only elements of its archetypes with data-fixture; the components
// receive the other values as props, as a page would. Texts that COPY_DECK assigns
// to pages stand here as sample props, because the page keys belong to the
// page Tasks (TASK-005 section 4.3): a component takes its texts as props.

export const FIXTURE = {
    heading: 'Spesimen komponen',
    sections: {
        buttons: 'Tombol',
        fields: 'Isian',
        choices: 'Pilihan',
        status: 'Status',
        notices: 'Pemberitahuan dan pemulihan',
        dialogs: 'Dialog, menu dan laci',
        data: 'Kartu, tabel dan angka',
        chart: 'Grafik tren',
        till: 'Kasir',
        steps: 'Langkah',
        paper: 'Kertas struk',
        states: 'Keadaan',
        archetypes: 'Pola halaman',
    },
    shop: 'Toko Contoh',
    shopLines: ['Jl. Contoh No. 1', 'Telp. 0000-0000'],
    productLabel: 'Nama produk',
    productHint: 'Contoh: Pulpen gel hitam 0,5 mm',
    quantityLabel: 'Jumlah',
    quantityUnit: 'pcs',
    amountLabel: 'Modal awal',
    searchLabel: 'Cari produk',
    method: 'Metode pembayaran',
    methodCash: 'Tunai',
    methodTransfer: 'Transfer bank',
    paper: 'Ukuran struk',
    filter: 'Filter stok',
    chips: ['Habis', 'Menipis', 'Normal'],
    tones: { neutral: 'Normal', success: 'Lengkap', warning: 'Menipis', pending: 'Menunggu', danger: 'Habis', info: 'Informasi' },
    finalAction: 'Selesaikan',
    pay: 'Bayar',
    total: 'Total',
    cart: 'Keranjang',
    clearCart: 'Kosongkan keranjang',
    results: 'Hasil pencarian',
    decrement: (name: string): string => `Kurangi jumlah ${name}`,
    increment: (name: string): string => `Tambah jumlah ${name}`,
    quantityOf: (name: string): string => `Jumlah ${name}`,
    reviewTitle: 'Tinjau barang masuk',
    confirm: 'Konfirmasi barang masuk',
    mustNot: 'Jangan minta pelanggan membayar atau mentransfer lagi. Penjualan ini mungkin sudah tersimpan.',
    committed: 'Barang masuk tersimpan.',
    emptyBody: 'Produk yang Anda tambahkan akan muncul di sini.',
    menu: 'Tindakan lain Pulpen gel hitam',
    menuItems: ['Ubah produk', 'Cetak label barcode', 'Nonaktifkan'],
    drawerTitle: 'Toko Contoh',
    drawerLinks: ['Beranda', 'Kasir', 'Stok'],
    table: 'Stok produk',
    wideTable: 'Riwayat stok',
    columns: { product: 'Produk', sku: 'SKU', stock: 'Stok', cost: 'Biaya', price: 'Harga', status: 'Status', time: 'Waktu', movement: 'Mutasi', reference: 'Referensi', actor: 'Oleh' },
    figures: { sales: 'Penjualan neto', count: 'Jumlah penjualan', cash: 'Pembayaran tunai', transfer: 'Pembayaran transfer bank', cost: 'HPP' },
    figureHint: 'Data per 17 Oktober 2026, 13.45.00 WIB',
    rows: 'Perlu perhatian',
    rowItems: ['Perlu restok', 'Biaya perlu dilengkapi'],
    rowMeta: ['7 produk', '2 baris'],
    chartTitle: 'Penjualan neto per hari',
    showTable: 'Tampilkan sebagai tabel',
    showChart: 'Tampilkan sebagai grafik',
    dateHeader: 'Tanggal',
    valueHeader: 'Penjualan neto',
    steps: 'Langkah tutup sesi kas',
    stepItems: ['Pratinjau', 'Hitung uang'],
    receipt: 'Struk',
    copyMarker: 'Salinan',
    notaTitle: 'Nota Penjualan',
    paperSizes: { '58': '58 mm', '80': '80 mm', a4: 'A4' },
    variance: 'Selisih',
    expected: 'Uang seharusnya di laci',
    physical: 'Uang fisik',
    unknownCode: '8991234567890',
    staleAt: '17 Oktober 2026, 13.40.00',
    resultCount: '3',
    tenderMissing: 'Rp2.000',
    archetypes: { home: 'Beranda', list: 'Stok', report: 'Laporan keuangan', command: 'Buka sesi kas', result: 'Struk' },
    signIn: 'Masuk',
    email: 'Email',
} as const;

/** Stationery goods of Toko Contoh, with canonical money strings (ADR-006). */
export const PRODUCTS = [
    { id: 'p1', name: 'Pulpen gel hitam 0,5 mm', sku: 'ATK-PLP-GEL-HTM', stock: '24 pcs', price: '3500', cost: '2100', status: 'neutral' },
    { id: 'p2', name: 'Buku tulis bergaris 58 lembar sampul plastik bening tebal isi ulang', sku: 'ATK-BKT-58-BENING-TEBAL', stock: '3 pcs', price: '6000', cost: null, status: 'warning' },
    { id: 'p3', name: 'Map plastik kancing', sku: 'ATK-MAP-KCG', stock: '0 pcs', price: '4500', cost: '3000', status: 'danger' },
] as const;

/** Net sales per business day of October 2026. */
export const TREND = [
    ['01 Oktober 2026', '1180000'],
    ['02 Oktober 2026', '1342500'],
    ['03 Oktober 2026', '965000'],
    ['04 Oktober 2026', '1510000'],
    ['05 Oktober 2026', '1275500'],
    ['06 Oktober 2026', '1890000'],
    ['07 Oktober 2026', '2104000'],
] as const;

export const HISTORY = [
    { id: 'h1', time: '17 Oktober 2026, 09.15.00', movement: 'Barang masuk', reference: 'BM-20261017-0001', actor: 'Agus', stock: '+24 pcs' },
    { id: 'h2', time: '17 Oktober 2026, 10.02.11', movement: 'Penjualan', reference: '20261017-000123', actor: 'Rina', stock: '−2 pcs' },
] as const;

/** What the specimen records for the browser tests, in a hidden output. */
export const EVENTS = {
    review: 'review',
    confirm: 'confirm',
    final: 'final',
    pay: 'pay',
    back: 'back',
    lookup: 'lookup',
    menu: 'menu',
    resend: 'resend',
    check: 'check',
    retry: 'retry',
    reload: 'reload',
    clear: 'clear',
    more: 'more',
    submit: 'submit',
    leave: 'leave',
} as const;

/** Amounts of the specimen, canonical money strings (ADR-006). */
export const AMOUNTS = {
    netSales: '1275500',
    transfer: '310000',
    physical: '1204500',
    expected: '1206500',
    variance: '-2000',
    receiptTotal: '14000',
    openingCash: '200000',
    zero: '0',
    one: '1',
} as const;

export const TOP = '#specimen-top';

export const SPECIMEN_IDS = {
    product: 'specimen-product',
    quantity: 'specimen-quantity',
    reason: 'specimen-reason',
} as const;

export const METHOD_OPTIONS = [
    { value: 'cash', label: FIXTURE.methodCash, icon: 'cash', shortcut: 'method_cash' },
    { value: 'transfer', label: FIXTURE.methodTransfer, icon: 'bank', shortcut: 'method_transfer' },
] as const;

export const PAPER_OPTIONS = [
    { value: '58', label: FIXTURE.paperSizes['58'] },
    { value: '80', label: FIXTURE.paperSizes['80'] },
    { value: 'a4', label: FIXTURE.paperSizes.a4 },
] as const;

/** "1 × Rp3.500" on a receipt line. */
export function lineDetail(quantity: string, price: string): string {
    return `${quantity} × ${price}`;
}
