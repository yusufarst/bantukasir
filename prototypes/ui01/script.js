const ROLES = {
  PEMILIK: {
    name: 'Budi Santoso',
    label: 'SUPER_ADMIN',
    nav: [
      { id: 'dasbor', label: 'Dasbor', icon: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>' },
      { id: 'laporan', label: 'Laporan', icon: '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>' },
      { id: 'produk', label: 'Produk', icon: '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>' },
      { id: 'stok', label: 'Stok', icon: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>' },
      { id: 'log', label: 'Log/Riwayat', icon: '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>' },
      { id: 'pengaturan', label: 'Profil Bisnis', icon: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>' }
    ]
  },
  OPERASIONAL: {
    name: 'Siti Aminah',
    label: 'OPERATIONS_ADMIN',
    nav: [
      { id: 'produk', label: 'Produk', icon: '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>' },
      { id: 'masuk', label: 'Barang Masuk', icon: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>' },
      { id: 'keluar', label: 'Barang Keluar', icon: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>' },
      { id: 'stok', label: 'Stok', icon: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>' },
      { id: 'log', label: 'Riwayat', icon: '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>' }
    ]
  },
  KASIR: {
    name: 'Andi Kurniawan',
    label: 'CASHIER',
    nav: [
      { id: 'kasir', label: 'Kasir', icon: '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>' },
      { id: 'sesi', label: 'Sesi Kas Saya', icon: '<rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/>' }
    ]
  }
};

let currentRole = null;
let activePage = null;

function switchRole(roleKey) {
  currentRole = ROLES[roleKey];
  document.getElementById('view-login').classList.add('hidden');
  document.getElementById('view-app').classList.remove('hidden');

  document.getElementById('user-name').textContent = currentRole.name;
  document.getElementById('user-role').textContent = currentRole.label;

  const identity = document.getElementById('session-identity');
  if (roleKey === 'KASIR') {
    identity.classList.remove('hidden');
  } else {
    identity.classList.add('hidden');
  }

  renderNav();
  navigate(currentRole.nav[0].id);
}

function logout() {
  document.getElementById('view-app').classList.add('hidden');
  document.getElementById('dialog-profile').classList.add('hidden');
  document.getElementById('view-login').classList.remove('hidden');
  currentRole = null;
}

function renderNav() {
  const nav = document.getElementById('sidebar-nav');
  nav.innerHTML = currentRole.nav.map(item => `
    <a class="nav-item" id="nav-${item.id}" onclick="navigate('${item.id}')">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${item.icon}</svg>
      ${item.label}
    </a>
  `).join('');
}

function navigate(pageId) {
  if (activePage) {
    const old = document.getElementById(`nav-${activePage}`);
    if (old) old.classList.remove('active');
  }
  activePage = pageId;
  const curr = document.getElementById(`nav-${activePage}`);
  if (curr) curr.classList.add('active');

  const pageItem = currentRole.nav.find(i => i.id === pageId);
  document.getElementById('page-title').textContent = pageItem ? pageItem.label : pageId;

  renderContent(pageId);
  if (window.innerWidth <= 768) {
    document.getElementById('sidebar').classList.remove('open');
  }
}

function renderContent(pageId) {
  const container = document.getElementById('page-content');

  if (pageId === 'dasbor') {
    container.innerHTML = `
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card">
          <div class="text-caption" style="margin-bottom: 8px;">Penjualan Hari Ini</div>
          <div class="text-section text-money">Rp 4.250.000</div>
        </div>
        <div class="card">
          <div class="text-caption" style="margin-bottom: 8px;">Laba Kotor (Hari Ini)</div>
          <div class="text-section text-money" style="color: var(--success);">Rp 1.150.000</div>
        </div>
        <div class="card">
          <div class="text-caption" style="margin-bottom: 8px;">Stok Menipis / Habis</div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="text-section">12</span>
            <span class="badge badge-warning">LOW</span>
            <span class="badge badge-danger">OUT</span>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 class="text-section" style="margin-bottom: 16px;">Ringkasan Stok Menipis</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Produk</th>
                <th>SKU</th>
                <th class="numeric">Stok</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Kopi Kapal Api Special 165g</td>
                <td>8996001300021</td>
                <td class="numeric font-medium">3</td>
                <td><span class="badge badge-warning">Menipis</span></td>
              </tr>
              <tr>
                <td>Indomie Hita Seleraku</td>
                <td>8998866200001</td>
                <td class="numeric font-medium" style="color: var(--danger);">0</td>
                <td><span class="badge badge-danger">Habis</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;
  } else if (pageId === 'kasir') {
    container.innerHTML = `
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; opacity: 0.8;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--subtle); display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--secondary);"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        </div>
        <h3 class="text-section">Sesi Kasir Siap</h3>
        <p class="text-body text-caption" style="margin-top: 8px;">Gunakan scan barcode atau cari barang untuk memulai</p>
        <div style="display: flex; gap: 8px; margin-top: 24px;">
          <div class="form-input" style="width: 300px; display: flex; align-items: center; color: var(--secondary);">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            Scan / Ketik Nama (F2)
          </div>
        </div>
      </div>
    `;
  } else {
    container.innerHTML = `
      <div class="card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 24px; text-align: center;">
        <h3 class="text-section">${currentRole.nav.find(i => i.id === pageId).label}</h3>
        <p class="text-body text-caption" style="margin-top: 8px;">Tampilan UI01 Placeholder</p>
      </div>
    `;
  }
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function toggleProfile() {
  const dialog = document.getElementById('dialog-profile');
  if (dialog.classList.contains('hidden')) {
    dialog.classList.remove('hidden');
  } else {
    dialog.classList.add('hidden');
  }
}

document.getElementById('mobile-menu-btn').addEventListener('click', toggleSidebar);
