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
let mobileCart=false, productQuery='', chartBins=[], chartObserver;
const navScroll={ops:0,owner:0,cashier:0};
let role='ops', page='dashboard', session=null, cart={}, tableQuery='', tableType='', cartObserver, sidebarCollapsed=false, accountTrigger=null, utilityTrigger=null, lastRoute='';
const DEMO_TODAY='2026-09-23';
const monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const formatDate=iso=>{const [year,month,day]=iso.split('-');return day+' '+monthNames[Number(month)-1]+' '+year;};
const shiftDate=(iso,days)=>{const d=new Date(iso+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+days);return d.toISOString().slice(0,10);};
let period={key:'day',start:DEMO_TODAY,end:DEMO_TODAY};
const periodNames={day:'Hari Ini',week:'7 Hari',month:'Bulan Ini',lastMonth:'Bulan Lalu',custom:'Custom Range'};
const rangeText=(range=period)=>{
 if(range.key==='day') return formatDate(range.start);
 if(range.key==='month'||range.key==='lastMonth') return monthNames[parseInt(range.start.slice(5,7),10)-1]+' '+range.start.slice(0,4);
 return formatDate(range.start)+(range.start===range.end?'':' — '+formatDate(range.end));
};
const inPeriod=(date,range=period)=>date>=range.start&&date<=range.end;
const priorPeriod=()=>{const days=Math.round((Date.parse(period.end)-Date.parse(period.start))/86400000)+1;return {start:shiftDate(period.start,-days),end:shiftDate(period.start,-1)};};

const products=[
 {id:'89910001',name:'Kertas HVS A4 80 gsm',sku:'ATK-001',price:58000,stock:24,min:10,created:'2026-08-10'},
 {id:'89910002',name:'Pulpen Gel Hitam',sku:'ATK-002',price:6500,stock:6,min:12,created:'2026-08-15'},
 {id:'89910003',name:'Buku Nota 2 Rangkap',sku:'ATK-003',price:12000,stock:0,min:8,created:'2026-09-01'},
 {id:'89910004',name:'Map Folder L',sku:'ATK-004',price:4500,stock:4,min:10,created:'2026-09-10'},
 {id:'89910005',name:'Spidol Whiteboard',sku:'ATK-005',price:9500,stock:18,min:6,created:'2026-09-22'},
 {id:'89910006',name:'Binder Clip 32 mm',sku:'ATK-006',price:18000,stock:15,min:5,created:'2026-09-23'},
 {id:'89910007',name:'Tinta Printer Hitam',sku:'ATK-007',price:85000,stock:5,min:5,created:'2026-09-23'},
 {id:'89910008',name:'Staples Max',sku:'ATK-008',price:14500,stock:20,min:10,created:'2026-09-23'}
];
const productById=id=>products.find(p=>p.id===id);
const lowStock=()=>products.filter(p=>p.stock>0&&p.stock<=p.min);
const outStock=()=>products.filter(p=>p.stock===0);
const movements=[
 {date:'2026-09-23',time:'10:42:08',id:'89910001',ref:'BM-0024',type:'Masuk',qty:12,note:'Siti Aminah'},
 {date:'2026-09-23',time:'10:18:25',id:'89910006',ref:'BM-0023',type:'Masuk',qty:24,note:'Siti Aminah'},
 {date:'2026-09-23',time:'09:56:12',id:'89910002',ref:'BK-0017',type:'Keluar',qty:-2,note:'Sampel produk'},
 {date:'2026-09-23',time:'09:24:40',id:'89910004',ref:'BK-0016',type:'Keluar',qty:-3,note:'Barang rusak'},
 {date:'2026-09-22',time:'14:15:10',id:'89910005',ref:'BM-0022',type:'Masuk',qty:30,note:'Siti Aminah'},
 {date:'2026-09-22',time:'11:08:02',id:'89910003',ref:'BK-0015',type:'Keluar',qty:-2,note:'Barang rusak'},
 {date:'2026-09-20',time:'09:10:30',id:'89910001',ref:'BM-0021',type:'Masuk',qty:20,note:'Siti Aminah'},
 {date:'2026-09-18',time:'13:25:00',id:'89910002',ref:'BM-0020',type:'Masuk',qty:48,note:'Siti Aminah'},
 {date:'2026-09-10',time:'08:40:15',id:'89910004',ref:'BM-0019',type:'Masuk',qty:36,note:'Siti Aminah'},
 {date:'2026-09-01',time:'10:30:00',id:'89910003',ref:'BM-0018',type:'Masuk',qty:24,note:'Siti Aminah'},
 {date:'2026-08-25',time:'11:05:20',id:'89910001',ref:'BM-0017',type:'Masuk',qty:40,note:'Siti Aminah'}
];
// Synthetic aggregate examples; never business records or a historical stock balance.
const sales=[
 {date:'2026-09-23',revenue:2450000,cost:1760000,count:28},
 {date:'2026-09-22',revenue:2100000,cost:1550000,count:25},
 {date:'2026-09-20',revenue:1680000,cost:1200000,count:19},
 {date:'2026-09-18',revenue:1900000,cost:1380000,count:22},
 {date:'2026-09-10',revenue:1560000,cost:1120000,count:18},
 {date:'2026-09-01',revenue:1300000,cost:950000,count:16},
 {date:'2026-08-25',revenue:1800000,cost:1260000,count:21}
];
const salesTotal=range=>sales.filter(x=>inPeriod(x.date,range)).reduce((a,x)=>({revenue:a.revenue+x.revenue,cost:a.cost===null||x.cost===null?null:a.cost+x.cost,count:a.count+x.count}),{revenue:0,cost:0,count:0});
const incoming=range=>movements.filter(x=>inPeriod(x.date,range)&&x.type==='Masuk').reduce((n,x)=>n+x.qty,0);
function delta(value,previous){return previous?((value-previous>=0?'+':'')+((value-previous)/previous*100).toLocaleString('id-ID',{maximumFractionDigits:1})+'%'):'Belum ada pembanding';}

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
 return '<div class="sidebar-brand">'+headerControls+'</div><nav class="navigation" aria-label="Menu '+r.label+'">'+groups().map(g=>'<div class="nav-group"><p class="nav-label">'+g.title+'</p>'+g.items.map(i=>'<a class="nav-item" title="'+i.label+'" aria-label="'+i.label+'" href="#'+role+'/'+i.target+'" '+(page===i.target?'aria-current="page"':'')+'>'+icon(i.ico)+'<span class="nav-text">'+i.label+'</span>'+(i.target==='restock'?'<span class="nav-count">'+lowStock().length+'</span>':'')+'</a>').join('')+'</div>').join('')+'</nav><div class="sidebar-account"><button class="account-button" data-action="account" aria-haspopup="dialog" aria-expanded="false" aria-label="Menu akun '+esc(r.name)+'"><span class="avatar">'+esc(r.initial)+'</span><span class="account-copy"><strong>'+esc(r.name)+'</strong><small>'+r.label+'</small></span>'+icon('chevrons')+'</button></div>';
}
const heading=(title,sub,action='')=>'<div class="page-heading"><div><h1>'+title+'</h1><p>'+sub+'</p></div><div class="heading-actions">'+action+'</div></div>';
const previewButton=(label,target,primary=false)=>'<button class="btn '+(primary?'primary':'quiet')+'" data-preview="'+target+'">'+label+icon('arrow')+'</button>';
function reviewSwitch(){
 const selectedRoute=role==='cashier' && page==='pos' ? (session?'cashier/open':'cashier/pos') : page==='profile'?role+'/profile':role+'/'+page;
 return '<footer class="footnote"><span>UI01 · Prototype visual · Semua data contoh</span><label class="review-switch">Lihat layar<select aria-label="Pilih layar review" id="review-screen">'+[['ops/dashboard','Admin Operasional'],['owner/dashboard','Pemilik'],['cashier/pos','Kasir · tanpa sesi'],['cashier/open','Kasir · sesi aktif'],[role+'/profile','Profil akun'],['login','Login']].map(([v,l])=>'<option value="'+v+'" '+(selectedRoute===v?'selected':'')+'>'+l+'</option>').join('')+'</select></label></footer>';
}
function ownerChart(){
 const days=Math.round((Date.parse(period.end)-Date.parse(period.start))/86400000)+1,step=days<=31?1:Math.ceil(days/24);
 chartBins=[];
 for(let i=0;i<days;i+=step){const start=shiftDate(period.start,i),end=shiftDate(period.start,Math.min(days-1,i+step-1));chartBins.push({start,end,...salesTotal({start,end})});}
 return '<section class="panel owner-chart" aria-labelledby="trend-title"><div class="panel-head"><div><h2 id="trend-title">Tren usaha</h2><p class="small muted">'+rangeText()+' · '+(step===1?'Harian':step+' hari / titik')+'</p></div><div class="chart-legend"><span class="legend-revenue">Penjualan</span><span class="legend-profit">Laba Kotor</span></div></div><div class="line-chart"><svg role="group" aria-label="Tren usaha. Fokus titik lalu gunakan panah kiri atau kanan untuk membaca nilai."></svg><div id="chart-tooltip" class="chart-tooltip" role="status" hidden></div></div><p class="chart-hint">Arahkan, ketuk, atau fokuskan titik untuk melihat nilai.</p><details class="chart-data"><summary>Rincian nilai · '+chartBins.length+' '+(step===1?'hari':'kelompok tanggal')+'</summary><ul>'+chartBins.map(v=>'<li><span>'+formatDate(v.start)+(v.start!==v.end?' – '+formatDate(v.end):'')+'</span><span>Penjualan '+money(v.revenue)+' · '+(v.cost===null?'Data biaya belum lengkap':'Laba Kotor '+money(v.revenue-v.cost))+'</span></li>').join('')+'</ul></details><p class="chart-note">Data contoh · '+(days===1?'Agregat harian; rincian jam belum tersedia. ':'Hari tanpa transaksi contoh bernilai 0. ')+(step>1?'Jumlah per '+step+' hari. ':'')+'Laba Kotor sebelum biaya operasional.</p></section>';
}
function drawChart(){
 const host=$('.line-chart'),svg=host?.querySelector('svg');if(!svg||!chartBins.length)return;
 const w=Math.max(260,host.clientWidth-24),h=224,left=48,right=w-18,top=18,bottom=182;
 const vals=chartBins.flatMap(v=>[v.revenue,...(v.cost===null?[]:[v.revenue-v.cost])]);
 const max=Math.max(...vals,100000),min=Math.min(0,...vals),span=max-min;
 const x=i=>chartBins.length===1?(left+right)/2:left+i*(right-left)/(chartBins.length-1),y=v=>bottom-(v-min)/span*(bottom-top);
 const value=(v,k)=>k==='profit'?(v.cost===null?null:v.revenue-v.cost):v.revenue;
 const path=k=>{let connect=false;return chartBins.map((v,i)=>{const n=value(v,k);if(n===null){connect=false;return '';}const p=(connect?'L':'M')+x(i)+' '+y(n);connect=true;return p;}).join(' ');};
 svg.setAttribute('viewBox','0 0 '+w+' '+h);
 svg.innerHTML=[0,.5,1].map(f=>{const n=min+span*f;return '<line x1="'+left+'" x2="'+right+'" y1="'+y(n)+'" y2="'+y(n)+'" class="chart-gridline"/><text x="'+(left-8)+'" y="'+(y(n)+4)+'" text-anchor="end">'+(n/1000000).toLocaleString('id-ID',{maximumFractionDigits:2})+'</text>';}).join('')+'<text x="8" y="12">Rp jt</text><path class="revenue-line" d="'+path('revenue')+'"/><path class="profit-line" d="'+path('profit')+'"/>'+chartBins.map((v,i)=>'<g class="chart-point" data-chart="'+i+'" role="button" tabindex="'+(i===0?0:-1)+'" aria-label="'+formatDate(v.start)+(v.start!==v.end?' sampai '+formatDate(v.end):'')+', Penjualan '+money(v.revenue)+', '+(v.cost===null?'Data biaya belum lengkap':'Laba Kotor '+money(v.revenue-v.cost))+'"><rect x="'+(x(i)-Math.min(22,(right-left)/chartBins.length/2))+'" y="'+top+'" width="'+Math.min(44,(right-left)/chartBins.length)+'" height="'+(bottom-top)+'" class="chart-hit"/><circle class="revenue-point" cx="'+x(i)+'" cy="'+y(v.revenue)+'" r="3"/>'+(v.cost===null?'':'<circle class="profit-point" cx="'+x(i)+'" cy="'+y(v.revenue-v.cost)+'" r="3"/>')+'</g>').join('')+chartBins.map((v,i)=>i===0||i===chartBins.length-1||(w>=600&&i===Math.floor(chartBins.length/2))?'<text x="'+x(i)+'" y="207" text-anchor="'+(chartBins.length===1?'middle':i===0?'start':i===chartBins.length-1?'end':'middle')+'">'+formatDate(v.start)+'</text>':'').join('');
 $('#chart-tooltip').hidden=true;
}
function showChartPoint(target){
 if(role!=='owner')return;
 const v=chartBins[Number(target.dataset.chart)],tip=$('#chart-tooltip');if(!v||!tip)return;
 tip.innerHTML='<strong>'+formatDate(v.start)+(v.start!==v.end?' – '+formatDate(v.end):'')+'</strong><span>Penjualan <b>'+money(v.revenue)+'</b></span><span>Laba Kotor <b>'+(v.cost===null?'Data biaya belum lengkap':money(v.revenue-v.cost))+'</b></span>';
 tip.hidden=false;
 const host=$('.line-chart').getBoundingClientRect(),point=target.querySelector('circle').getBoundingClientRect();
 tip.style.left=Math.max(8,Math.min(point.left-host.left-tip.offsetWidth/2,host.width-tip.offsetWidth-8))+'px';
 tip.style.top='8px';
 document.querySelectorAll('.chart-point').forEach(p=>{p.classList.toggle('is-active',p===target);p.tabIndex=p===target?0:-1;});
}

function dashboard(){
 const owner=role==='owner',selected=movements.filter(x=>inPeriod(x.date)),current=salesTotal(period),previous=salesTotal(priorPeriod());
 const incomingNow=incoming(period),newProducts=products.filter(p=>inPeriod(p.created)).length;
 const values=owner?[
 ['Penjualan',money(current.revenue),delta(current.revenue,previous.revenue),'vs periode sebelumnya','report',''],
 ['HPP',current.cost===null?'Belum lengkap':money(current.cost),current.cost===null?'Data biaya belum lengkap':current.count?'Data biaya lengkap':'Belum ada transaksi',current.cost===null?'Lengkapi bukti biaya':current.count+' transaksi dengan biaya tersedia','box',''],
 ['Laba Kotor',current.cost===null?'Belum lengkap':money(current.revenue-current.cost),current.cost===null?'Data biaya belum lengkap':previous.cost===null?'Pembanding belum lengkap':delta(current.revenue-current.cost,previous.revenue-previous.cost),'vs periode sebelumnya · sebelum biaya operasional','report',''],
 ['Transaksi selesai',String(current.count),delta(current.count,previous.count),'Pembayaran penuh · periode terpilih','cash','alert-success']
 ]:[
 ['Produk aktif',String(products.length),'+'+newProducts+' produk baru','Ditambahkan pada periode terpilih','box',''],
 ['Stok menipis',String(lowStock().length),lowStock().length+' dari '+products.length+' produk','Pada/di bawah minimum · saat ini','alert','alert-warning'],
 ['Stok habis',String(outStock().length),'Prioritas restock',outStock().map(p=>p.name).join(', '),'box','alert-danger'],
 ['Barang masuk',String(incomingNow)+' unit',delta(incomingNow,incoming(priorPeriod())),'vs periode sebelumnya','down','']
 ];
 return heading('Dashboard',owner?'Ringkasan penjualan dan operasional bisnis Anda.':'Pantau stok dan aktivitas operasional Anda.',
 '<button class="date date-control" data-action="period" aria-haspopup="dialog" aria-expanded="false">'+icon('calendar')+'<span>'+esc(periodNames[period.key])+'</span>'+icon('chevron')+'</button><button class="btn primary" data-action="report" aria-haspopup="dialog">Lihat Laporan '+icon('report')+'</button>')+
 '<div class="period-context"><span>'+icon('calendar')+'<strong>'+rangeText()+'</strong></span><span>Data contoh'+(!owner?' · Indikator stok menunjukkan kondisi saat ini':' · Dibandingkan periode sebelumnya dengan durasi sama')+'</span></div>'+
 '<section class="metrics" aria-label="Ringkasan periode terpilih">'+values.map(([t,v,d,n,i,c])=>'<article class="metric '+c+'"><div class="metric-title">'+t+icon(i)+'</div><p class="metric-value '+(v.startsWith('Rp')?'money':'')+'">'+v+'</p><p class="metric-change">'+icon(d.includes('%')?(d.startsWith('-')?'down':'trend'):(c?'alert':'box'))+d+'</p><p class="metric-note">'+n+'</p></article>').join('')+'</section>'+
 (owner?ownerChart():'')+
 '<div class="dashboard-grid"><section class="panel activity-panel"><div class="panel-head"><h2>Aktivitas terbaru</h2>'+previewButton('Lihat riwayat','riwayat')+'</div><div class="activity">'+(selected.length?selected.slice(0,4).map(m=>'<div class="activity-row"><span class="activity-icon '+(m.type==='Keluar'?'issue':'')+'">'+icon(m.type==='Masuk'?'down':'up')+'</span><div class="activity-copy"><p>Barang '+m.type.toLowerCase()+' dicatat</p><small>'+productById(m.id).name+' · '+m.note+'</small><time class="activity-stamp" datetime="'+m.date+'T'+m.time+'+07:00">'+formatDate(m.date)+', '+m.time+'</time></div><span class="activity-qty">'+(m.qty>0?'+':'')+m.qty+'</span></div>').join(''):'<p class="empty-result">Belum ada aktivitas pada periode ini. Pilih periode lain.</p>')+'</div></section>'+
 '<section class="panel attention-panel"><div class="panel-head"><h2>Perlu Perhatian</h2>'+icon('alert')+'</div><div class="attention-list">'+attentionItems(owner,selected)+'</div><p class="attention-summary">'+(role==='cashier'?'Status operasional kasir':(owner?'Prioritas operasional dan finansial':'Prioritas stok saat ini'))+' · '+formatDate(DEMO_TODAY)+'</p></section></div>'+
 (owner?'':'<section class="panel" aria-labelledby="movement-title"><div class="movement-toolbar"><h2 id="movement-title">Pergerakan stok</h2><div class="table-tools"><label class="search">'+icon('search')+'<input id="movement-search" aria-label="Cari pergerakan stok" placeholder="Cari produk atau referensi" value="'+esc(tableQuery)+'"></label><select id="movement-type" aria-label="Jenis pergerakan"><option value="">Semua jenis</option><option>Masuk</option><option>Keluar</option></select></div></div><table><thead><tr><th>Tanggal / waktu</th><th>Produk</th><th>Referensi</th><th>Jenis</th><th class="quantity">Jumlah</th><th>Status</th></tr></thead><tbody id="movement-rows">'+movementRows()+'</tbody></table><div class="table-footer"><span id="movement-count">'+filteredMovements().length+' aktivitas ditampilkan</span><span>'+rangeText()+'</span></div></section>');
}
function attentionItems(owner,selected){
 const items=[];
 if(role !== 'cashier'){
  if(outStock().length) items.push(['danger','Prioritas tinggi',outStock().length+' produk stok habis',outStock().map(p=>p.name).join(', ')+' · siapkan restock','stok']);
  if(lowStock().length) items.push(['warning','Perlu ditindaklanjuti',lowStock().length+' produk mencapai batas minimum',lowStock().map(p=>p.name).join(' · '),'restock']);
 }
 if(owner){
  const st = salesTotal(period);
  if(st.cost === null) items.push(['warning','Integritas laba','Data biaya belum lengkap','HPP belum mencakup seluruh transaksi pada periode ini.','hpp']);
 }
 if(role === 'cashier'){
  if(!session) items.push(['warning','Status sesi','Sesi kasir belum dibuka','Buka sesi untuk menerima pembayaran hari ini.','session']);
 }
 return items.length?items.map(([c,l,t,n,a])=>'<button class="attention-item" data-preview="'+a+'"><span class="attention-marker '+c+'" aria-hidden="true"></span><span class="copy"><span class="attention-priority '+c+'">'+l+'</span><strong>'+t+'</strong><small>'+n+'</small></span>'+icon('arrow')+'</button>').join(''):'<p class="empty-result">Tidak ada isu prioritas saat ini.</p>';
}
function filteredMovements(){return movements.filter(m=>inPeriod(m.date)&&(productById(m.id).name+' '+m.ref+' '+m.type).toLowerCase().includes(tableQuery.toLowerCase())&&(!tableType||m.type===tableType));}
function movementRows(){
 const rows=filteredMovements();
 return rows.length?rows.map(m=>'<tr><td data-column="time"><time datetime="'+m.date+'T'+m.time+'+07:00">'+formatDate(m.date)+'<small>'+m.time+'</small></time></td><td data-column="product">'+productById(m.id).name+'</td><td data-column="ref">'+m.ref+'</td><td data-column="type">'+m.type+'</td><td data-column="qty">'+(m.qty>0?'+':'')+m.qty+' unit</td><td data-column="status"><span class="badge green">Selesai</span></td></tr>').join(''):'<tr><td colspan="6" class="empty-result">Tidak ada pergerakan yang cocok. Coba periode lain, kata lain, atau semua jenis.</td></tr>';
}

function sessionForm(){
 return heading(page==='session'?(role==='owner'?'Sesi Kas':'Sesi Kas Saya'):'Kasir','Mulai penjualan dengan membuka sesi kas Anda.')+'<div class="session-layout"><section class="panel session-form"><span class="session-icon">'+icon('cash')+'</span><h2>Belum ada sesi kas aktif</h2><p>Periksa laci dan masukkan uang tunai awal sebelum melayani pelanggan.</p><form id="open-session"><div class="form-stack"><div class="field"><label for="drawer-select">Laci kas</label><select id="drawer-select"><option>Laci Kasir 01</option></select></div><div class="field"><label for="opening-cash">Uang tunai awal (Rp)</label><input id="opening-cash" type="number" inputmode="numeric" min="0" max="999999999" step="1" placeholder="Contoh: 100000" required><small>Masukkan 0 jika laci belum berisi uang.</small></div><div class="field"><label>Kasir penanggung jawab</label><span>'+esc(roles[role].name)+'</span></div></div><button class="btn primary" type="submit">'+icon('plus')+'Buka Sesi Kas</button></form></section><aside class="panel session-guide"><h2>Siap melayani, lebih teratur</h2><ol><li><strong>Hitung uang tunai awal</strong><span>Pastikan jumlahnya sesuai isi laci.</span></li><li><strong>Cari atau scan barang</strong><span>Tambahkan barang ke keranjang pelanggan.</span></li><li><strong>Bayar, lalu cetak struk</strong><span>Selesaikan pembayaran sebelum melayani transaksi berikutnya.</span></li></ol><p class="note">Satu sesi untuk satu laci dan satu kasir penanggung jawab.</p></aside></div>';
}
function pos(){
 if(!session)return sessionForm();
 return heading('Kasir','Cari barang, susun keranjang, lalu lanjutkan pembayaran.')+
 '<div class="session-strip"><span class="badge green">Sesi aktif</span><div class="session-details"><strong>'+esc(session.name)+' · Laci Kasir 01</strong><small>Dibuka '+formatDate(DEMO_TODAY)+', '+session.time+'<span class="session-opening-cash"> · Tunai awal '+money(session.cash)+'</span></small></div><a class="btn quiet session-link" aria-label="Sesi Kas Saya" href="#'+role+'/session"><span>Sesi Kas Saya</span>'+icon('arrow')+'</a></div>'+
 '<div class="pos-layout '+(mobileCart?'cart-active':'')+'"><section class="panel catalog-panel"><h2>Cari / scan produk</h2><form id="scan-form"><div class="catalog-search"><label class="search">'+icon('search')+'<input id="product-search" aria-label="Cari nama, SKU, atau scan barcode" placeholder="Nama, SKU, atau barcode" value="'+esc(productQuery)+'" autocomplete="off"></label><button class="btn" type="button" data-action="barcode">'+icon('barcode')+'Scan</button></div></form><div id="product-results" class="product-list">'+productRows(productQuery)+'</div><p class="note">Scanner USB/Bluetooth atau ketik barcode, lalu Enter.</p></section><section class="panel" id="cart-panel" aria-labelledby="cart-heading"><div class="panel-head"><h2 id="cart-heading" tabindex="-1">Keranjang</h2><span id="cart-count" class="muted">0 unit</span><button class="btn quiet back-catalog" data-action="catalog">Tambah barang</button></div><div id="cart-content"></div></section></div><button class="btn primary mobile-cart-link" data-action="cart">Lihat keranjang</button>';
}
function productRows(q){
 const matches=products.filter(p=>(p.name+' '+p.id+' '+p.sku).toLowerCase().includes(q.toLowerCase()));
 return matches.length?matches.map(p=>'<div class="product-row"><img class="product-thumb" src="assets/products/'+p.sku.toLowerCase()+'.svg" alt="" width="52" height="52"><div class="product-copy"><strong>'+p.name+'</strong><small>'+p.sku+' · '+(p.stock===0?'Habis':'Stok '+p.stock)+(p.stock>0&&p.stock<=p.min?' · Menipis':'')+'</small><span class="product-price">'+money(p.price)+'</span></div><button class="icon-button" data-add="'+p.id+'" aria-label="Tambah '+p.name+'" '+(p.stock===0?'disabled':'')+'>'+icon('plus')+'</button></div>').join(''):'<p class="empty-result">Produk tidak ditemukan. Periksa nama atau barcode.</p>';
}
function renderCart(focusKey=''){
 if(!$('#cart-content'))return;
 const entries=products.filter(p=>cart[p.id]),total=entries.reduce((n,p)=>n+p.price*cart[p.id],0),count=Object.values(cart).reduce((a,b)=>a+b,0);
 $('#cart-count').textContent=count+' unit';
 $('#cart-content').innerHTML=(entries.length?'<div class="cart-lines">'+entries.map(p=>'<div class="cart-line"><div class="cart-line-copy"><strong>'+p.name+'</strong><small>'+money(p.price)+' / unit</small></div><button class="icon-button remove-line" data-remove="'+p.id+'" aria-label="Hapus '+p.name+'">'+icon('close')+'</button><div class="quantity-stepper"><button class="icon-button" data-adjust="'+p.id+'" data-step="-1" aria-label="Kurangi '+p.name+'">'+icon('minus')+'</button><output aria-label="Jumlah '+p.name+'">'+cart[p.id]+'</output><button class="icon-button" data-adjust="'+p.id+'" data-step="1" '+(cart[p.id]>=p.stock?'disabled':'')+' aria-label="Tambah jumlah '+p.name+'">'+icon('plus')+'</button></div><strong class="line-total">'+money(p.price*cart[p.id])+'</strong></div>').join('')+'</div>':'<div class="cart-empty">'+icon('cart')+'<strong>Keranjang masih kosong</strong><p>Cari atau scan barang untuk mulai melayani.</p><button class="btn quiet" data-action="catalog">Cari barang</button></div>')+'<div class="cart-summary"><div class="cart-total"><span>Total</span><strong>'+money(total)+'</strong></div><button class="btn primary" data-action="payment" '+(!count?'disabled':'')+'>Tinjau pembayaran '+icon('arrow')+'</button><small>Contoh UI01 · belum mencatat penjualan.</small></div>';
 const jump=$('.mobile-cart-link');if(jump)jump.innerHTML='<span>'+icon('cart')+' '+count+' unit</span><strong>'+money(total)+'</strong><span>Lihat keranjang '+icon('arrow')+'</span>';
 const badge=$('[data-cart-count]');if(badge)badge.textContent=count;
 if(focusKey){const target=$('[data-adjust="'+focusKey.split(':')[0]+'"][data-step="'+focusKey.split(':')[1]+'"]');(target&&!target.disabled?target:$('#cart-heading')).focus();}
}
function setCartView(show){
 mobileCart=show;const layout=$('.pos-layout');if(!layout)return;
 layout.classList.toggle('cart-active',show);const jump=$('.mobile-cart-link');if(jump)jump.hidden=show;
 document.querySelectorAll('.bottom-nav [data-action]').forEach(b=>b.setAttribute('aria-current',b.dataset.action===(show?'cart':'catalog')?'page':'false'));
 requestAnimationFrame(()=>{const target=show?$('#cart-heading'):$('#product-search');target?.focus();if(matchMedia('(max-width:767px)').matches)window.scrollTo(0,0);});
}

function profile(){
 const r=roles[role];
 return heading('Profil akun','Identitas dan akses akun Anda.','<a class="btn" href="#'+role+'/'+(role==='cashier'?'pos':'dashboard')+'">Kembali</a>')+'<section class="panel profile"><div class="profile-summary"><span class="avatar">'+esc(r.initial)+'</span><div><h2>'+esc(r.name)+'</h2><p>'+r.label+' · <span class="badge green">Aktif</span></p></div></div><dl class="profile-fields">'+[['Nama lengkap',esc(r.name)],['Alamat email',r.email],['Bisnis','Toko ATK Contoh'],['Akses',role==='owner'?'Pengelolaan usaha, pengguna, laporan dan sistem':role==='ops'?'Produk, stok dan riwayat operasional':'Penjualan dan sesi kas sendiri']].map(([k,v])=>'<div class="profile-line"><dt>'+k+'</dt><dd>'+v+'</dd></div>').join('')+'</dl><div class="profile-actions"><button class="btn primary" data-action="edit-profile">Edit profil</button><button class="btn" data-action="account-settings">Bantuan akses akun</button>'+(role==='owner'?'<button class="btn quiet" data-go="sistem">Pengaturan sistem '+icon('arrow')+'</button>':'')+'</div><p class="profile-footer">Peran dan email masuk dikelola Pemilik. Perubahan contoh hanya berlaku sampai halaman dimuat ulang.</p></section>';
}
function editProfile(){
 detail('Edit profil','<form id="profile-form" class="form-stack"><div class="field"><label for="profile-name">Nama tampilan</label><input id="profile-name" value="'+esc(roles[role].name)+'" required maxlength="60" autocomplete="name" aria-describedby="profile-help"><small id="profile-help">Tidak mengubah email, peran, atau penanggung jawab sesi.</small></div><p class="small muted">Pratinjau lokal. Tidak ada perubahan pada akun produksi.</p><div class="profile-actions"><button type="button" class="btn" data-close="detail">Batal</button><button class="btn primary" type="submit">Simpan contoh</button></div></form>');$('#profile-name').focus();
}
function accountSettings(){
 detail('Pengaturan akun','<dl class="profile-fields"><div class="profile-line"><dt>Email masuk</dt><dd>'+roles[role].email+'</dd></div><div class="profile-line"><dt>Peran</dt><dd>'+roles[role].label+'</dd></div></dl><p class="note">'+(role==='owner'?'Kelola akses dan reset akun melalui Pengguna.':'Hubungi Pemilik untuk perubahan email, akses, atau reset kata sandi.')+'</p>'+(role==='owner'?'<div class="profile-actions"><button class="btn primary" data-go="pengguna">Kelola pengguna '+icon('arrow')+'</button></div>':'')+'<p class="small muted">UI01 menampilkan pola akun; layanan autentikasi belum terhubung.</p>');
}
function bottomNavigation(){
 const items=role==='cashier'?[['Kasir','cash','pos'],['Keranjang','cart','cart'],['Sesi saya','history','session'],['Akun','user','profile']]:role==='owner'?[['Dashboard','home','dashboard'],['Laporan','report','laporan'],['Menu','menu','menu'],['Akun','user','profile']]:[['Dashboard','home','dashboard'],['Stok','box','stok'],['Menu','menu','menu'],['Akun','user','profile']];
 return '<nav class="bottom-nav" aria-label="Navigasi utama mobile">'+items.map(([label,i,target])=>target==='menu'?'<button data-action="sidebar" aria-label="Buka semua menu">'+icon(i)+'<span>'+label+'</span></button>':target==='cart'||(role==='cashier'&&target==='pos'&&page==='pos'&&session)?'<button data-action="'+(target==='cart'?'cart':'catalog')+'" '+(target==='cart'&&!session?'disabled':'')+' aria-current="'+((page==='pos'&&(target==='cart'?mobileCart:!mobileCart))?'page':'false')+'">'+icon(i)+'<span>'+label+(target==='cart'?' <span data-cart-count>0</span>':'')+'</span></button>':'<a href="#'+role+'/'+target+'" '+(page===target?'aria-current="page"':'')+'>'+icon(i)+'<span>'+label+'</span></a>').join('')+'</nav>';
}

function sessionView(){
 if(!session)return sessionForm();
 return heading(role==='owner'?'Sesi Kas':'Sesi Kas Saya','Ringkasan sesi kas yang sedang berjalan.','<a class="btn primary" href="#'+role+'/pos">Kembali ke Kasir</a>')+'<section class="panel profile"><div class="panel-head"><h2>Laci Kasir 01</h2><span class="badge green">Sesi aktif</span></div><dl class="profile-fields">'+[['Penanggung jawab',session.name],['Waktu buka',formatDate(DEMO_TODAY)+', '+session.time],['Tunai awal',money(session.cash)]].map(([k,v])=>'<div class="profile-line"><dt>'+k+'</dt><dd>'+esc(v)+'</dd></div>').join('')+'</dl><p class="profile-footer">Tampilan sesi contoh. Rekonsiliasi dan penutupan sesi mengikuti tahap integrasi berikutnya.</p></section>';
}
function login(){
 return '<div class="login"><header class="login-header">'+brand()+'</header><aside class="login-aside"><h2>Usaha tertata.<br>Pelayanan lebih lancar.</h2><p>Penjualan dan persediaan barang, dalam satu alur kerja yang jelas.</p><p class="login-principle">BantuKasir · Teman operasional usaha Anda</p></aside><main class="login-main" id="content"><h1>Selamat datang</h1><p>Masuk untuk melanjutkan aktivitas usaha Anda.</p><div id="login-feedback" role="status" aria-live="polite" hidden></div><form class="form-stack" id="login-form"><div class="field"><label for="email">Alamat email</label><input id="email" type="email" placeholder="nama@usaha.com" autocomplete="username" required></div><div class="field"><label for="password">Kata sandi</label><input id="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></div><button class="btn primary" type="submit">Masuk</button></form><div class="login-demo"><label for="demo-role">Prototype visual · Pilih peran contoh</label><select id="demo-role"><option value="ops">Admin Operasional</option><option value="owner">Pemilik</option><option value="cashier">Kasir</option></select><label for="demo-login-state">Hasil masuk contoh</label><select id="demo-login-state"><option value="success">Berhasil</option><option value="error">Kesalahan masuk</option></select><p class="muted small">Gunakan email dan kata sandi contoh. Ini bukan autentikasi akun.</p></div></main><footer class="login-footer">BantuKasir · UI01 untuk review desain</footer></div>';
}
function render(){
 if($('#drawer').open)navScroll[role]=$('#drawer .navigation').scrollTop;
 const oldNav=$('#desktop-sidebar .navigation');if(oldNav&&matchMedia('(min-width:1024px)').matches)navScroll[role]=oldNav.scrollTop;
 chartObserver?.disconnect();
 closePopovers();
 cartObserver?.disconnect();
 for(const d of document.querySelectorAll('dialog[open]'))d.close();
 const route=(location.hash.slice(1)||'ops/dashboard').split('/');
 if(route[0]==='login'){page='login';$('#app').innerHTML=login();return;}
 const nextRole=roles[route[0]]?route[0]:'ops';
 if(nextRole!==role){session=null;cart={};mobileCart=false;productQuery='';}
 role=nextRole; page=route[1]||(role==='cashier'?'pos':'dashboard');
 if(page==='open'&&role!=='ops'&&!session){session={name:roles[role].name,time:'08:00:00',cash:100000};cart={};page='pos';}
 if(page==='open'&&role!=='ops')page='pos';
 const allowed=groups().flatMap(g=>g.items.map(i=>i.target));
 if(!allowed.includes(page)&&page!=='profile')page=role==='cashier'?'pos':'dashboard';
 const title=page==='profile'?'Profil akun':groups().flatMap(g=>g.items).find(i=>i.target===page)?.label||'Dashboard';
 const content=page==='dashboard'?dashboard():page==='pos'?pos():page==='session'?sessionView():page==='profile'?profile():heading(title,'Pratinjau struktur navigasi '+roles[role].label+'.')+'<section class="panel session-guide"><h2>'+title+'</h2><p class="muted">Layar detail ini berada di bundle berikutnya. UI01 menetapkan shell, navigasi, dan gaya komponen.</p><a class="btn quiet" href="#'+role+'/dashboard">Kembali ke Dashboard '+icon('arrow')+'</a></section>';
 $('#app').innerHTML='<div class="shell '+(sidebarCollapsed?'is-collapsed':'')+'"><aside class="sidebar" id="desktop-sidebar">'+navigation()+'</aside>'+topbar(title)+'<main class="content" id="content">'+content+reviewSwitch()+'</main>'+bottomNavigation()+'</div>';
 const nav=$('#desktop-sidebar .navigation');nav.scrollTop=navScroll[role];
 const active=nav.querySelector('[aria-current=page]');if(active){const r=active.getBoundingClientRect(),n=nav.getBoundingClientRect();if(r.top<n.top)nav.scrollTop-=n.top-r.top;else if(r.bottom>n.bottom)nav.scrollTop+=r.bottom-n.bottom;}
 if($('.line-chart')){drawChart();chartObserver=new ResizeObserver(drawChart);chartObserver.observe($('.line-chart'));}
 renderCart(); if($('#movement-type'))$('#movement-type').value=tableType;
 const cartJump=$('.mobile-cart-link');if(cartJump)cartJump.hidden=mobileCart;
 const currentRoute=role+'/'+page;
 if(lastRoute&&lastRoute!==currentRoute){const h=$('h1')||$('#content');if(h){h.tabIndex=-1;h.focus();}}
 lastRoute=currentRoute;
}
function detail(title,body){if($('#drawer').open)$('#drawer').close();$('#detail').innerHTML='<div class="dialog-head"><h2 id="detail-heading">'+title+'</h2><button class="icon-button" data-close="detail" aria-label="Tutup dialog">'+icon('close')+'</button></div>'+body;$('#detail').showModal();}
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
function searchResults(query){
 const found=products.filter(p=>(p.name+' '+p.sku+' '+p.id).toLowerCase().includes(query.toLowerCase()));
 return found.length?found.map(p=>'<div class="shell-result"><strong>'+p.name+'</strong><small>'+p.sku+' · Stok '+p.stock+' · '+money(p.price)+'</small></div>').join(''):'<p class="empty-result">Produk tidak ditemukan. Coba nama atau barcode lain.</p>';
}
function showSearch(trigger,query=''){
 showUtility(trigger,'Cari produk','<label class="search">'+icon('search')+'<input id="shell-search" aria-label="Cari nama atau barcode" placeholder="Nama, SKU, atau barcode" value="'+esc(query)+'"></label><div id="shell-results">'+searchResults(query)+'</div><p class="small muted">Data contoh · Pencarian produk operasional</p>');
}
function showAttention(trigger){
 const owner=role==='owner';
 const attItems=attentionItems(owner,movements.filter(m=>inPeriod(m.date)));
 const actItems=movements.slice(0,3).map(m=>'<a class="feed-record" href="#'+role+'/riwayat"><span class="activity-icon '+(m.type==='Keluar'?'issue':'')+'">'+icon(m.type==='Masuk'?'down':'up')+'</span><span><strong>'+productById(m.id).name+'</strong><small>'+m.ref+' · '+(m.qty>0?'+':'')+m.qty+' unit · '+m.time+'</small></span></a>').join('');
 const body=role==='cashier'?
 '<div class="notification-feed"><div class="feed-section"><h3>Status Sesi</h3>'+(session?'<div class="attention-item"><span class="attention-marker info" aria-hidden="true"></span><span class="copy"><span class="attention-priority info">Sesi aktif</span><strong>'+esc(session.name)+' · Laci Kasir 01</strong><small>Dibuka '+formatDate(DEMO_TODAY)+', '+session.time+'</small></span></div>':'<div class="attention-item"><span class="attention-marker warning" aria-hidden="true"></span><span class="copy"><span class="attention-priority warning">Status sesi</span><strong>Sesi kasir belum dibuka</strong><small>Buka sesi untuk menerima pembayaran hari ini.</small></span></div>')+'</div></div>'
 :
 '<div class="notification-feed">'+
 (attItems!== '<p class="empty-result">Tidak ada isu prioritas saat ini.</p>' ? '<div class="feed-section"><h3>Perlu Perhatian</h3>'+attItems+'</div>' : '')+
 '<div class="feed-section"><h3>Aktivitas Terbaru</h3>'+actItems+'</div></div><div class="dialog-foot"><a class="btn quiet" href="#'+role+'/riwayat">Lihat semua riwayat '+icon('arrow')+'</a></div>';
 showUtility(trigger,role==='cashier'?'Status Kasir':'Aktivitas & Perhatian',body);
}
const presetRange=key=>{
 let start=DEMO_TODAY,end=DEMO_TODAY;
 if(key==='week')start=shiftDate(end,-6);
 if(key==='month')start=end.slice(0,8)+'01';
 if(key==='lastMonth'){end=shiftDate(DEMO_TODAY.slice(0,8)+'01',-1);start=end.slice(0,8)+'01';}
 return {key,start,end};
};
const validDate=value=>/^\d{4}-\d{2}-\d{2}$/.test(value)&&Number(value.slice(0,4))>=1000&&!Number.isNaN(Date.parse(value))&&new Date(value+'T12:00:00Z').toISOString().slice(0,10)===value;
let calState={...period,viewDate:new Date(period.start+'T12:00:00Z'),focusDate:period.start,field:'start'};
function renderCalendar(year,month){
 const days=new Date(Date.UTC(year,month+1,0)).getUTCDate(),startDay=new Date(Date.UTC(year,month,1)).getUTCDay();
 let html='<div class="calendar-month"><div class="calendar-grid">'+['Mg','Sn','Sl','Rb','Km','Jm','Sb'].map(d=>'<span class="cal-dw">'+d+'</span>').join('');
 for(let i=0;i<startDay;i++)html+='<span class="cal-empty"></span>';
 for(let i=1;i<=days;i++){
  const date=year+'-'+String(month+1).padStart(2,'0')+'-'+String(i).padStart(2,'0'),selected=date===calState[calState.field],disabled=date>DEMO_TODAY||(calState.field==='end'&&date<calState.start);
  html+='<button type="button" class="cal-date '+(selected?'cal-selected':'')+'" data-caldate="'+date+'" tabindex="'+(calState.focusDate===date?0:-1)+'" aria-label="'+formatDate(date)+'" aria-pressed="'+selected+'" '+(disabled?'disabled':'')+'>'+i+'</button>';
 }
 return html+'</div></div>';
}
function updatePeriodDOM(){
 $('#utility .period-fields').hidden=calState.key!=='custom';
 for(const field of ['start','end'])$('#period-'+field+' .date-value').textContent=formatDate(calState[field]);
 $('#period-selection-status').textContent=calState.key==='custom'?'Tanggal akhir tidak boleh mendahului tanggal awal.':rangeText(calState);
 $('#cal-apply').disabled=!validDate(calState.start)||!validDate(calState.end)||calState.start>calState.end||calState.end>DEMO_TODAY;
 $('#utility input[value="'+calState.key+'"]').checked=true;
}
function positionCalendar(){
 const pop=$('#date-picker'),trigger=$('#period-'+calState.field),r=trigger.getBoundingClientRect();
 pop.style.width=Math.min(336,innerWidth-24)+'px';pop.style.maxHeight=(innerHeight-24)+'px';
 const h=pop.getBoundingClientRect().height;
 pop.style.left=Math.max(12,Math.min(r.left,innerWidth-pop.getBoundingClientRect().width-12))+'px';
 pop.style.top=Math.max(12,Math.min(r.bottom+8+h<=innerHeight-12?r.bottom+8:r.top-h-8,innerHeight-h-12))+'px';
}
function updateCalendarDOM(){
 const y=calState.viewDate.getUTCFullYear(),m=calState.viewDate.getUTCMonth();
 $('#cal-container').innerHTML=renderCalendar(y,m);
 const c=$('#cal-container');if(!c.querySelector('[tabindex="0"]:not(:disabled)'))c.querySelector('.cal-date:not(:disabled)')?.setAttribute('tabindex','0');
 $('#calendar-month').value=m;$('#calendar-year').value=y;
 $('#date-picker [data-calnav="1"]').disabled=new Date(Date.UTC(y,m+1,1)).toISOString().slice(0,10)>DEMO_TODAY;
 $('#date-picker [data-calnav="-1"]').disabled=y===1000&&m===0;
}
function closeCalendar(focus=false){
 const pop=$('#date-picker');if(pop?.matches(':popover-open'))pop.hidePopover();
 document.querySelectorAll('[data-date-field]').forEach(b=>b.setAttribute('aria-expanded','false'));
 if(focus)$('#period-'+calState.field)?.focus({preventScroll:true});
}
function showCalendar(field){
 closeCalendar();calState.field=field;calState.focusDate=calState[field];calState.viewDate=new Date(calState[field].slice(0,8)+'01T12:00:00Z');
 const pop=$('#date-picker');
 pop.innerHTML='<div class="date-picker-heading"><strong id="date-picker-title">'+(field==='start'?'Dari tanggal':'Sampai tanggal')+'</strong><button class="icon-button" data-action="close-calendar" aria-label="Tutup kalender">'+icon('close')+'</button></div><div class="cal-header"><button class="icon-button" data-calnav="-1" aria-label="Bulan sebelumnya">'+icon('arrow')+'</button><div class="cal-jump"><select id="calendar-month" aria-label="Bulan kalender">'+monthNames.map((m,i)=>'<option value="'+i+'">'+m+'</option>').join('')+'</select><input id="calendar-year" type="number" min="1000" max="2026" step="1" aria-label="Tahun kalender"></div><button class="icon-button" data-calnav="1" aria-label="Bulan berikutnya">'+icon('arrow')+'</button></div><div id="cal-container"></div><p class="small muted calendar-limit">Sampai '+formatDate(DEMO_TODAY)+' · data contoh</p>';
 updateCalendarDOM();pop.showPopover();$('#period-'+field).setAttribute('aria-expanded','true');positionCalendar();pop.querySelector('[tabindex="0"]')?.focus({preventScroll:true});
}
function showPeriod(trigger){
 if(trigger.getAttribute('aria-expanded')==='true'){closePopovers();return;}
 closePopovers();utilityTrigger=trigger;const pop=$('#utility');document.body.append(pop);
 calState={...period,viewDate:new Date(period.start.slice(0,8)+'01T12:00:00Z'),focusDate:period.start,field:'start'};
 pop.innerHTML='<div class="dialog-head"><h2 id="utility-heading">Periode tampilan</h2><button class="icon-button" data-action="close-utility" aria-label="Tutup panel">'+icon('close')+'</button></div><div class="period-picker"><fieldset class="period-options"><legend class="sr-only">Pilih periode</legend>'+Object.entries(periodNames).map(([key,label])=>'<label><input type="radio" name="period" value="'+key+'" '+(key===period.key?'checked':'')+'><span>'+label+'</span></label>').join('')+'</fieldset><div class="period-fields">'+['start','end'].map(field=>'<button class="date-field" id="period-'+field+'" data-date-field="'+field+'" aria-haspopup="dialog" aria-expanded="false" aria-controls="date-picker"><span class="date-label">'+(field==='start'?'Dari tanggal':'Sampai tanggal')+'</span><span class="date-value"></span>'+icon('calendar')+'</button>').join('')+'</div></div><p id="period-selection-status" class="period-status small muted" role="status"></p><div class="period-buttons"><button class="btn quiet" data-action="close-utility">Batal</button><button class="btn primary" id="cal-apply" data-action="apply-custom">Terapkan</button></div><p id="period-error" class="inline-error" role="status"></p><div id="date-picker" class="date-picker" popover="auto" role="dialog" aria-labelledby="date-picker-title"></div>';
 updatePeriodDOM();pop.showPopover();trigger.setAttribute('aria-expanded','true');positionPopover(pop,trigger);pop.querySelector('input:checked').focus();
 $('#date-picker').addEventListener('toggle',e=>{if(e.newState==='closed')document.querySelectorAll('[data-date-field]').forEach(b=>b.setAttribute('aria-expanded','false'));});
}
function applyPeriod(){
 if(!validDate(calState.start)||!validDate(calState.end)||calState.start>calState.end||calState.end>DEMO_TODAY)return;
 period={key:calState.key,start:calState.start,end:calState.end};render();$('[data-action="period"]')?.focus();$('#announcement').textContent='Periode diperbarui: '+rangeText()+'.';
}
function showReport(trigger){
 if(trigger.getAttribute('aria-expanded')==='true'){
  trigger.setAttribute('aria-expanded','false');
  if($('#utility').matches(':popover-open')) $('#utility').hidePopover();
  return;
 }
 closePopovers();utilityTrigger=trigger;
 const pop=$('#utility');document.body.append(pop);
 const rows=movements.filter(m=>inPeriod(m.date));
 pop.innerHTML='<div class="dialog-head"><h2 id="utility-heading">Laporan '+(role==='owner'?'usaha':'operasional')+'</h2><button class="icon-button" data-action="close-utility" aria-label="Tutup panel">'+icon('close')+'</button></div>'+'<p class="report-period">'+rangeText()+'</p><p class="muted small">Ekspor data contoh yang ditampilkan. Laporan lengkap akan direview pada bundle berikutnya.</p><div class="export-actions"><button class="menu-action" data-export="stock">'+icon('down')+'<span><strong>Pergerakan stok</strong><small>'+rows.length+' catatan · CSV contoh</small></span></button>'+(role==='owner'?'<button class="menu-action" data-export="sales">'+icon('report')+'<span><strong>Ringkasan penjualan</strong><small>Penjualan, HPP, Laba Kotor · CSV contoh</small></span></button>':'')+'</div><p class="small muted" id="export-feedback" role="status"></p>';
 pop.showPopover();trigger.setAttribute('aria-expanded','true');positionPopover(pop,trigger);(pop.querySelector('button,a'))?.focus();
}
function exportExample(kind){
 if(kind==='sales'&&role!=='owner')return;
 let rows;
 if(kind==='sales'){const t=salesTotal(period);rows=[['Data contoh UI01','Dari','Sampai','Transaksi','Penjualan','HPP','Laba Kotor'],['Ringkasan penjualan',formatDate(period.start),formatDate(period.end),t.count,t.revenue,t.cost===null?"Data biaya belum lengkap":t.cost,t.cost===null?"Data biaya belum lengkap":t.revenue-t.cost]];}
 else rows=[['Data contoh UI01','Tanggal','Waktu','Produk','Referensi','Jenis','Jumlah'],...movements.filter(m=>inPeriod(m.date)).map(m=>['Pergerakan stok',formatDate(m.date),m.time,productById(m.id).name,m.ref,m.type,m.qty])];
 const csv=rows.map(row=>row.map(value=>'"'+String(value).replace(/"/g,'""')+'"').join(',')).join('\r\n');
 const url=URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'}));
 const link=document.createElement('a');link.href=url;link.download='bantukasir-contoh-'+kind+'-'+period.start+'-'+period.end+'.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
 $('#export-feedback').textContent='CSV contoh berhasil disiapkan.';
}

function add(id){
 const p=products.find(p=>p.id===id);if(!session||!p)return;
 if((cart[id]||0)>=p.stock){detail('Stok tidak mencukupi','<p>Jumlah di keranjang sudah mencapai stok tersedia ('+p.stock+' unit).</p>');return;}
 cart[id]=(cart[id]||0)+1;renderCart();$('#announcement').textContent=p.name+' ditambahkan. Jumlah '+cart[id]+'.';
}
let toggledPopover=null;
document.addEventListener('pointerdown',e=>{const t=e.target.closest('[data-action=account]');toggledPopover=t&&$('#account').matches(':popover-open')?t:null;},true);
document.addEventListener('click',e=>{
 const point=e.target.closest('[data-chart]');if(point){showChartPoint(point);return;}
 const skip=e.target.closest('.skip-link');
 if(skip){e.preventDefault();const c=$('#content');if(c){c.tabIndex=-1;c.focus();}return;}
 const b=e.target.closest('button');if(!b)return;
 if(b.dataset.close){$('#'+b.dataset.close).close();return;}
 if(b.dataset.go){closePopovers();if($('#detail').open)$('#detail').close();if($('#drawer').open)$('#drawer').close();location.hash=role+'/'+b.dataset.go;return;}
 if(b.dataset.preview){location.hash=role+'/'+b.dataset.preview;return;}
 if(b.dataset.export){exportExample(b.dataset.export);return;}
 if(b.dataset.add){add(b.dataset.add);return;}
 if(b.dataset.adjust){const id=b.dataset.adjust,step=Number(b.dataset.step),p=productById(id);cart[id]=Math.max(0,Math.min(p.stock,(cart[id]||0)+step));if(!cart[id])delete cart[id];renderCart(id+':'+step);$('#announcement').textContent=p.name+': '+(cart[id]||0)+' unit.';return;}
 if(b.dataset.remove){delete cart[b.dataset.remove];renderCart();$('#announcement').textContent='Barang dihapus dari keranjang.';$('#cart-heading').focus();return;}
 if(b.dataset.action === 'apply-custom') { applyPeriod(); return; }
 if(b.dataset.dateField){showCalendar(b.dataset.dateField);return;}
 if(b.dataset.calnav){
  calState.viewDate.setUTCMonth(calState.viewDate.getUTCMonth()+Number(b.dataset.calnav));
  updateCalendarDOM();positionCalendar();$('#date-picker [data-calnav="'+b.dataset.calnav+'"]').focus({preventScroll:true});return;
 }
 if(b.dataset.caldate){
  if(b.disabled)return;const date=b.dataset.caldate;calState[calState.field]=date;calState.focusDate=date;
  if(calState.start>calState.end){calState.end=calState.start;$('#period-error').textContent='Tanggal akhir disesuaikan dengan tanggal awal. Pilih tanggal akhir bila diperlukan.';}else $('#period-error').textContent='';
  updatePeriodDOM();closeCalendar(true);positionPopover($('#utility'),utilityTrigger);return;
 }
 switch(b.dataset.action){
 case 'drawer':case 'sidebar':toggleSidebar();break;
 case 'account':if(toggledPopover===b){closePopovers();toggledPopover=null;b.focus();}else account(b);break;
 case 'search':showSearch(b);break;
 case 'attention':showAttention(b);break;
 case 'period':showPeriod(b);break;
 case 'report':showReport(b);break;
 case 'close-calendar':closeCalendar(true);break;
 case 'close-utility':$('#utility').hidePopover();utilityTrigger?.focus();break;
 case 'logout':session=null;cart={};mobileCart=false;location.hash='login';break;
 case 'cart':if(page!=='pos'){mobileCart=true;location.hash=role+'/pos';}else setCartView(true);break;
 case 'catalog':setCartView(false);break;
 case 'edit-profile':closePopovers();editProfile();break;
 case 'account-settings':closePopovers();accountSettings();break;
 case 'barcode':detail('Scan barcode','<form id="barcode-form" class="form-stack"><div class="field"><label for="barcode-input">Barcode barang</label><input id="barcode-input" inputmode="numeric" autocomplete="off" required aria-describedby="barcode-help"><small id="barcode-help">Gunakan scanner USB/Bluetooth atau ketik kode. Contoh: 89910001.</small></div><p id="barcode-error" class="inline-error" role="status"></p><button type="submit" class="btn primary">Tambahkan ke keranjang</button></form>');$('#barcode-input').focus();break;
 case 'payment':detail('Ringkasan keranjang contoh','<p>Total <strong>'+money(products.reduce((n,p)=>n+p.price*(cart[p.id]||0),0))+'</strong></p><p class="muted">Alur pembayaran dan struk akan direview di UI03. Tidak ada pembayaran atau penjualan yang dicatat dari prototype ini.</p>');break;
 }
});
document.addEventListener('change',e=>{
 if(e.target.name==='period'){
  closeCalendar();calState.key=e.target.value;if(calState.key!=='custom')Object.assign(calState,presetRange(calState.key));
  $('#period-error').textContent='';updatePeriodDOM();positionPopover($('#utility'),utilityTrigger);
 }
 if(e.target.id==='calendar-month'||e.target.id==='calendar-year'){
  const year=Number($('#calendar-year').value),month=Number($('#calendar-month').value);
  if(!Number.isInteger(year)||year<1000||year>2026){$('#calendar-year').value=calState.viewDate.getUTCFullYear();return;}
  calState.viewDate=new Date(Date.UTC(year,month,1));updateCalendarDOM();positionCalendar();
 }
 if(e.target.id==='review-screen'){
 const route=e.target.value;
 if(route==='cashier/pos'){session=null;cart={};mobileCart=false;}
 location.hash=route;render();
 }
 if(e.target.id==='movement-type'){tableType=e.target.value;updateMovements();}
});
function updateMovements(){$('#movement-rows').innerHTML=movementRows();$('#movement-count').textContent=filteredMovements().length+' aktivitas ditampilkan';}
document.addEventListener('input',e=>{
 if(e.target.id==='profile-name')e.target.setCustomValidity('');
 if(e.target.id==='movement-search'){tableQuery=e.target.value;updateMovements();}
 if(e.target.id==='shell-search')$('#shell-results').innerHTML=searchResults(e.target.value.trim());
 if(e.target.id==='product-search'){productQuery=e.target.value;$('#product-results').innerHTML=productRows(productQuery.trim());}
});
document.addEventListener('submit',e=>{
 e.preventDefault();
 if(e.target.id==='profile-form'){const name=$('#profile-name').value.trim();if(!name){$('#profile-name').setCustomValidity('Isi nama tampilan.');$('#profile-name').reportValidity();return;}roles[role].name=name;roles[role].initial=name.split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase();$('#detail').close();render();const savedHeading=$('h1');savedHeading.tabIndex=-1;savedHeading.focus();$('#announcement').textContent='Profil contoh diperbarui. Akun produksi tidak diubah.';return;}
 if(e.target.id==='header-search-form'){showSearch($('#header-search'),$('#header-search').value.trim());return;}
 if(e.target.id==='login-form'){
  const form=e.target, button=form.querySelector('button'), feedback=$('#login-feedback');
  const demoRole=$('#demo-role').value, fail=$('#demo-login-state').value==='error';
  feedback.hidden=false;feedback.className='muted small';feedback.textContent='Memeriksa data masuk contoh…';
  form.setAttribute('aria-busy','true');button.disabled=true;button.textContent='Memeriksa…';
  for(const input of form.querySelectorAll('input'))input.removeAttribute('aria-invalid');
  setTimeout(()=>{
   if(!form.isConnected)return;
   form.removeAttribute('aria-busy');button.disabled=false;button.textContent='Masuk';
   if(fail){feedback.className='login-error';feedback.textContent='Email atau kata sandi tidak sesuai. Periksa kembali dan coba lagi.';for(const input of form.querySelectorAll('input')){input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby','login-feedback');}$('#email').focus();return;}
   session=null;cart={};location.hash=demoRole+'/'+(demoRole==='cashier'?'pos':'dashboard');
  },900);
 }
 if(e.target.id==='open-session'){session={name:roles[role].name,cash:Number($('#opening-cash').value),time:'10:45:00'};page='pos';location.hash=role+'/pos';render();}
 if(e.target.id==='barcode-form'){const input=$('#barcode-input'),p=productById(input.value.trim());if(!p||!p.stock||(cart[p.id]||0)>=p.stock){$('#barcode-error').textContent=!p?'Barcode belum dikenali. Periksa kode atau cari nama barang.':'Stok tersedia tidak mencukupi.';input.setAttribute('aria-invalid','true');input.focus();return;}add(p.id);$('#detail').close();$('#product-search')?.focus();return;}
 if(e.target.id==='scan-form'){
 const q=$('#product-search').value.trim().toLowerCase();
 const p=products.find(p=>p.id===q||p.sku.toLowerCase()===q||p.name.toLowerCase()===q);
 if(p){add(p.id);productQuery='';$('#product-search').value='';$('#product-results').innerHTML=productRows('');}
 else{$('#announcement').textContent='Barcode belum dikenali. Periksa produk dan coba lagi.';detail('Produk belum dikenali','<p>Periksa barcode atau cari barang berdasarkan nama. Keranjang Anda tetap tersimpan.</p>');}
 }
});
document.addEventListener('pointerover',e=>{const p=e.target.closest('[data-chart]');if(p)showChartPoint(p);});
document.addEventListener('focusin',e=>{if(e.target.matches('[data-chart]'))showChartPoint(e.target);});
document.addEventListener('pointerdown',e=>{if(!e.target.closest('.line-chart')&&$('#chart-tooltip'))$('#chart-tooltip').hidden=true;});
document.addEventListener('keydown',e=>{
 const p=e.target.closest('[data-chart]');
 if(p){if(e.key==='Escape'){$('#chart-tooltip').hidden=true;return;}if(['ArrowLeft','ArrowRight','Home','End'].includes(e.key)){e.preventDefault();const i=Number(p.dataset.chart),next=e.key==='Home'?0:e.key==='End'?chartBins.length-1:Math.max(0,Math.min(chartBins.length-1,i+(e.key==='ArrowRight'?1:-1)));$('[data-chart="'+next+'"]').focus();return;}if(['Enter',' '].includes(e.key)){e.preventDefault();showChartPoint(p);}}
 const cell=e.target.closest('[data-caldate]');
 if(cell&&['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)){
  e.preventDefault();const date=shiftDate(cell.dataset.caldate,{ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7}[e.key]);if(date>DEMO_TODAY||date<'1000-01-01'||(calState.field==='end'&&date<calState.start))return;
  calState.focusDate=date;if(!$('[data-caldate="'+date+'"]'))calState.viewDate=new Date(date.slice(0,8)+'01T12:00:00Z');updateCalendarDOM();$('[data-caldate="'+date+'"]')?.focus();
 }
});
document.addEventListener('keydown',e=>{
 if(e.key!=='Escape')return;
 if($('#date-picker')?.matches(':popover-open')){e.preventDefault();e.stopPropagation();closeCalendar(true);return;}
 const accountOpen=$('#account').matches(':popover-open'),utilityOpen=$('#utility').matches(':popover-open');
 if(accountOpen||utilityOpen){e.preventDefault();e.stopPropagation();closePopovers();(accountOpen?accountTrigger:utilityTrigger)?.focus();}
},true);
$('#drawer').addEventListener('click',e=>{if(e.target===$('#drawer')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom){closePopovers();e.target.close();}}});
document.addEventListener('scroll',e=>{if(e.target.matches?.('.navigation')&&e.target.clientHeight>0)navScroll[role]=e.target.scrollTop;},true);
$('#drawer').addEventListener('close',()=>{closePopovers();document.body.append($('#account'));});
$('#utility').addEventListener('toggle',e=>{if(e.newState==='closed')utilityTrigger?.setAttribute('aria-expanded','false');});
$('#account').addEventListener('toggle',e=>{if(e.newState==='closed')accountTrigger?.setAttribute('aria-expanded','false');});
let lastViewportWidth=innerWidth;
window.addEventListener('resize',()=>{
 if(innerWidth!==lastViewportWidth){closePopovers();lastViewportWidth=innerWidth;}
 if(matchMedia('(min-width:1024px)').matches&&$('#drawer').open)$('#drawer').close();
});
window.addEventListener('hashchange',()=>{render();window.scrollTo(0,0);});
render();
