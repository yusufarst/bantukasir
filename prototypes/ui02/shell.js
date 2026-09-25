/* UI01 R6 shell renderer reuse from 1f62e98; route links adapted for UI02 only. */
/* UI01 visual prototype. All data and state are local demonstration values. */
const $ = (s, root = document) => root.querySelector(s);
const money = n => 'Rp' + n.toLocaleString('id-ID');
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const paths = {
 barcode:'M3 4v16M6 4v16M10 4v16M12 4v16M16 4v16M20 4v16', chevron:'m6 9 6 6 6-6', trend:'M3 17l6-6 4 4 8-10M15 5h6v6', chevrons:'m8 8 4-4 4 4m-8 8 4 4 4-4', bell:'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M10 21h4', panel:'M3 3h18v18H3zM9 3v18',
 menu:'M4 6h16M4 12h16M4 18h16', close:'m6 6 12 12M6 18 18 6', arrow:'m9 5 7 7-7 7',
 home:'m3 10 9-7 9 7v11H3zM9 21v-8h6v8', box:'m3 7 9-4 9 4v10l-9 4-9-4zM3 7l9 5 9-5M12 12v9',
 down:'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5', up:'M12 16V4m-5 5 5-5 5 5M4 16v5h16v-5',
 history:'M3 5v5h5M4 9a9 9 0 1 1 0 7M12 7v5l3 2', alert:'m12 3 10 18H2zM12 9v5M12 17v1',
 search:'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
 user:'M20 21v-3a5 5 0 0 0-5-5H9a5 5 0 0 0-5 5v3M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 cash:'M3 5h18v15H3zM3 10h18M7 15h3', report:'M4 3v18h17M8 16v-5m5 5V7m5 9v-7',
 settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2',
 minus:'M5 12h14', plus:'M12 5v14M5 12h14', cart:'M2 3h3l3 12h11l3-9H6M9 20h1m7 0h1',
 calendar:'M4 5h16v16H4zM4 10h16M8 2v6m8-6v6', logout:'M9 4H3v16h6M9 12h12m-4-4 4 4-4 4'
};
const icon = name => '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="'+(paths[name]||paths.box)+'"/></svg>';
const brand = () => '<span class="brand"><img src="../../assets/brand/bantukasir-logo.svg" alt="BantuKasir"></span>';
const roles = {
 ops:{name:'Siti Aminah',label:'Admin Operasional',initial:'SA',email:'siti@example.test'},
 owner:{name:'Budi Santoso',label:'Pemilik',initial:'BS',email:'budi@example.test'},
 cashier:{name:'Dewi Lestari',label:'Kasir',initial:'DL',email:'dewi@example.test'}
};

const navItem=(label,ico,target)=>({label,ico,target});
function groups(){
 const stock=[navItem('Produk','box','produk'),navItem('Stok','box','stok'),navItem('Barang Masuk','down','masuk'),navItem('Barang Keluar','up','keluar'),navItem('Restock','history','restock')];
 if(role==='cashier') return [{title:'PENJUALAN',items:[navItem('Kasir','cash','pos'),navItem('Sesi Kas Saya','history','session')]}];
 const result=[{title:'UTAMA',items:[navItem('Dashboard','home','dashboard')]}];
 if(role==='owner')result.push({title:'PENJUALAN',items:[navItem('Kasir','cash','pos'),navItem('Sesi Kas','history','session')]});
 result.push({title:'OPERASIONAL',items:stock});
 result.push({title:'PENGAWASAN',items:role==='owner'?[navItem('Laporan','report','laporan'),navItem('HPP / Laba Kotor','report','hpp'),navItem('Log / Riwayat','history','riwayat')]:[navItem('Riwayat','history','riwayat')]});
 if(role==='owner')result.push({title:'PENGATURAN',items:[navItem('Pengguna','user','pengguna'),navItem('Profil Bisnis','box','bisnis'),navItem('Sistem','settings','sistem')]});
 return result;
}
function navigation(drawer=false){
 const r=roles[role];
 const headerControls = (!sidebarCollapsed||drawer)
  ? '<div class="brand-container">'+brand()+'</div>'+(drawer?'<button class="icon-button" data-close="drawer" aria-label="Tutup navigasi">'+icon('close')+'</button>':'<button class="icon-button desk-close" data-action="sidebar" aria-label="Tutup sidebar" title="Tutup sidebar">'+icon('panel')+'</button>')
  : '<button class="icon-button is-collapsed-btn" data-action="sidebar" aria-label="Buka sidebar" aria-expanded="false" title="Buka sidebar">'+icon('panel')+'</button>';
 return '<div class="sidebar-brand">'+headerControls+'</div><nav class="navigation" aria-label="Menu '+r.label+'">'+groups().map(g=>'<div class="nav-group"><p class="nav-label">'+g.title+'</p>'+g.items.map(i=>'<a class="nav-item" title="'+i.label+'" aria-label="'+i.label+'" href="'+routeURL(i.target)+'" '+((page===i.target||(i.target==='produk'&&['detail','baru','edit','label','kategori'].includes(page)))?'aria-current="page"':'')+'>'+icon(i.ico)+'<span class="nav-text">'+i.label+'</span>'+(i.target==='restock'?'<span class="nav-count">'+lowStock().length+'</span>':'')+'</a>').join('')+'</div>').join('')+'</nav><div class="sidebar-account"><button class="account-button" data-action="account" aria-haspopup="dialog" aria-expanded="false" aria-label="Menu akun '+esc(r.name)+'"><span class="avatar">'+esc(r.initial)+'</span><span class="account-copy"><strong>'+esc(r.name)+'</strong><small>'+r.label+'</small></span>'+icon('chevrons')+'</button></div>';
}
const heading=(title,sub,action='')=>'<div class="page-heading"><div><h1>'+title+'</h1><p>'+sub+'</p></div><div class="heading-actions">'+action+'</div></div>';

function topbar(title){
 const activePOS=page==='pos'&&session;
 return '<header class="topbar"><div class="topbar-left"><button class="icon-button mobile-drawer-btn" data-action="sidebar" aria-label="Buka navigasi" aria-expanded="false" aria-controls="drawer">'+icon('panel')+'</button><div class="brand-container mobile-brand">'+brand()+'</div><span class="crumb"><span class="muted desktop-only">Toko ATK Contoh / </span>'+title+'</span></div><div class="topbar-right">'+(activePOS?'':'<form class="header-search" id="header-search-form"><label class="search">'+icon('search')+'<input id="header-search" aria-label="Cari produk di BantuKasir" placeholder="Cari produk..." autocomplete="off"></label></form><button class="icon-button mobile-search" data-action="search" aria-label="Cari produk">'+icon('search')+'</button>')+'<button class="icon-button attention-trigger" data-action="attention" aria-haspopup="dialog" aria-label="'+(role==='cashier'?'Informasi sesi saya':'Perhatian stok')+'">'+icon('bell')+(role!=='cashier'?'<span class="utility-dot"></span>':'')+'</button></div></header>';
}

function closePopovers(){
 for(const id of ['date-picker','account','utility']){const p=$('#'+id);if(p?.matches(':popover-open'))p.hidePopover();}
}
function toggleSidebar(){
 closePopovers();
 if(matchMedia('(min-width:1024px)').matches){const scroll=$('#desktop-sidebar .navigation').scrollTop;sidebarCollapsed=!sidebarCollapsed;$('.shell').classList.toggle('is-collapsed',sidebarCollapsed);$('#desktop-sidebar').innerHTML=navigation();$('#desktop-sidebar .navigation').scrollTop=scroll;$('#desktop-sidebar [data-action=sidebar]').focus({preventScroll:true});return;}
 const drawer=$('#drawer');
 if(drawer.open){drawer.close();return;}
 drawer.innerHTML=navigation(true);drawer.showModal();$('.navigation',drawer).scrollTop=navScroll[role];
}
function positionPopover(pop,trigger,above=false){
 const rect=trigger.getBoundingClientRect(),gap=8,rail=above&&sidebarCollapsed&&matchMedia('(min-width:1024px)').matches;
 const bounds=above?trigger.closest('.sidebar, .drawer').getBoundingClientRect():{left:0,right:innerWidth,top:0,width:innerWidth};
 const width=above?(rail?272:bounds.width-24):Math.min(360,innerWidth-24);
 pop.style.width=width+'px';pop.style.maxHeight=Math.min(pop.querySelector('.notification-feed')?420:innerHeight-24,innerHeight-24)+'px';
 const height=pop.getBoundingClientRect().height;
 pop.style.left=Math.max(12,Math.min(above?(rail?bounds.right+8:bounds.left+12):rect.right-width,innerWidth-width-12))+'px';
 pop.style.top=Math.max(12,Math.min(above?rect.top-height-gap:rect.bottom+gap,innerHeight-height-12))+'px';
}

function account(trigger){
 if(trigger.getAttribute('aria-expanded')==='true'){
  trigger.setAttribute('aria-expanded','false');
  if($('#account').matches(':popover-open')) $('#account').hidePopover();
  return;
 }
 closePopovers();accountTrigger=trigger;
 const pop=$('#account'),drawer=$('#drawer');
 (drawer.open?drawer:document.body).append(pop);
 const r=roles[role];
 pop.innerHTML='<div class="account-summary"><span class="avatar">'+esc(r.initial)+'</span><div><h2 id="account-heading">'+esc(r.name)+'</h2><p class="muted small">'+r.label+'</p><p class="muted small">'+r.email+'</p></div></div><button class="menu-action" data-go="profile">'+icon('user')+'Profil akun</button>'+(role==='owner'?'<button class="menu-action" data-go="sistem">'+icon('settings')+'Pengaturan</button>':'')+'<button class="menu-action" data-action="logout">'+icon('logout')+'Keluar</button>';
 pop.showPopover();trigger.setAttribute('aria-expanded','true');positionPopover(pop,trigger,true);pop.querySelector('button').focus();
}
function showUtility(trigger,title,body){
 if(trigger.getAttribute('aria-expanded')==='true'){
  trigger.setAttribute('aria-expanded','false');
  if($('#utility').matches(':popover-open')) $('#utility').hidePopover();
  return;
 }
 closePopovers();utilityTrigger=trigger;
 const pop=$('#utility');document.body.append(pop);
 pop.innerHTML='<div class="dialog-head"><h2 id="utility-heading">'+title+'</h2><button class="icon-button" data-action="close-utility" aria-label="Tutup panel">'+icon('close')+'</button></div>'+body;
 pop.showPopover();trigger.setAttribute('aria-expanded','true');positionPopover(pop,trigger);(pop.querySelector('input:not([type="hidden"])')||pop.querySelector('button,a'))?.focus();
}
