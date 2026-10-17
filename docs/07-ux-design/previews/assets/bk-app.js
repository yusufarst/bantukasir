/* =============================================================================
   BantuKasir preview behaviour — RF-4C (nonproduction)

   Renders every text from its copy key (bk-copy.js), builds the shell from one
   navigation table per role (INFORMATION_ARCHITECTURE section 4), and gives
   the key screens just enough behaviour to review them: dialogs with focus
   kept inside, the till keyboard path and shortcuts (UXA-09), the payment
   rules of D-040, the close flow, the trend chart (UXA-19).

   It is a review aid, not an implementation: no request is sent, nothing is
   stored, and every number is fixture data. Query parameters (review only):
   ?state=<name>  a designed state of the page
   ?peran=owner|ops|cashier|combined  the signed-in role, where the page allows it
   ?aksen=green|teal|blue|purple|graphite  one accent of the constrained set
   ============================================================================= */
(function () {
  'use strict';

  var COPY = window.BK_COPY || {};
  var PROPOSED = window.BK_COPY_PROPOSED || {};
  var Q = new URLSearchParams(location.search);
  var body = document.body;

  /* ---------------------------------------------------------------------------
     Copy, placeholders and formats (COPY_DECK sections 1.2 and 1.3)
     ------------------------------------------------------------------------- */
  function t(key, params) {
    var s = Object.prototype.hasOwnProperty.call(COPY, key) ? COPY[key]
      : (Object.prototype.hasOwnProperty.call(PROPOSED, key) ? PROPOSED[key] : null);
    if (s === null) { console.error('Missing copy key: ' + key); return '[' + key + ']'; }
    if (params) {
      s = s.replace(/:([a-z_]+)/g, function (m, n) {
        return Object.prototype.hasOwnProperty.call(params, n) ? String(params[n]) : m;
      });
    }
    return s;
  }
  function readParams(el, attr) {
    var raw = el.getAttribute(attr || 'data-p');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (e) { console.error('Bad params on', el, raw); return null; }
  }
  function group(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  function rp(n) {
    var neg = n < 0;
    var v = Math.round(Math.abs(n));
    return (neg ? '−' : '') + 'Rp' + group(v);
  }
  function qty(n, dec) {
    var s = Number(n).toFixed(dec || 0);
    var parts = s.split('.');
    var frac = parts[1] ? parts[1].replace(/0+$/, '') : '';
    return group(parts[0]) + (frac ? ',' + frac : '');
  }
  function parseRp(text) {
    var digits = String(text || '').replace(/[^0-9]/g, '');
    return digits === '' ? null : parseInt(digits, 10);
  }
  window.BK = { t: t, rp: rp, qty: qty };

  function renderCopy(root) {
    root = root || document;
    root.querySelectorAll('[data-k]').forEach(function (el) {
      el.textContent = t(el.getAttribute('data-k'), readParams(el));
    });
    root.querySelectorAll('[data-k-aria]').forEach(function (el) {
      var s = t(el.getAttribute('data-k-aria'), readParams(el, 'data-p-aria') || readParams(el));
      var sc = el.getAttribute('data-shortcut');
      el.setAttribute('aria-label', sc ? s + ' (' + sc + ')' : s);
    });
    root.querySelectorAll('[data-k-ph]').forEach(function (el) {
      el.setAttribute('placeholder', t(el.getAttribute('data-k-ph')));
    });
    root.querySelectorAll('[data-k-title]').forEach(function (el) {
      var s = t(el.getAttribute('data-k-title'), readParams(el, 'data-p-title'));
      var sc = el.getAttribute('data-shortcut');
      el.setAttribute('title', sc ? s + ' (' + sc + ')' : s);
    });
    root.querySelectorAll('[data-k-alt]').forEach(function (el) {
      el.setAttribute('aria-label', t(el.getAttribute('data-k-alt'), readParams(el, 'data-p-alt')));
    });
    root.querySelectorAll('[data-k-label]').forEach(function (el) {
      el.setAttribute('data-label', t(el.getAttribute('data-k-label')));
    });
    root.querySelectorAll('[data-icon]').forEach(function (el) {
      if (!el.firstElementChild) el.insertAdjacentHTML('afterbegin', window.BK_ICON(el.getAttribute('data-icon'), el.getAttribute('data-icon-class') || ''));
    });
  }

  /* ---------------------------------------------------------------------------
     Fixture identity (synthetic; CW-12, CW-14)
     ------------------------------------------------------------------------- */
  var SHOP = 'Toko Contoh';
  var USERS = {
    owner: { name: 'Hendra', roles: ['access.role.super_admin'] },
    ops: { name: 'Agus', roles: ['access.role.operations_admin'] },
    cashier: { name: 'Rina', roles: ['access.role.cashier'] },
    combined: { name: 'Dewi', roles: ['access.role.cashier', 'access.role.operations_admin'] }
  };

  /* ---------------------------------------------------------------------------
     One navigation source (INFORMATION_ARCHITECTURE section 4; COPY_DECK 4.3)
     ------------------------------------------------------------------------- */
  var OUT = 'index.html#outside-set';
  var NAV = {
    home: { key: 'reporting.home.title', icon: 'home', href: 'ks-02-beranda.html' },
    till: { key: 'sales.till.title', icon: 'till', href: 'ks-03-kasir.html' },
    sales: { key: 'sales.list.title', icon: 'receipt', href: OUT },
    sessions: { key: 'cash.sessions.title', icon: 'drawer', href: OUT },
    stock: { key: 'inventory.stock.title', icon: 'boxes', href: 'ks-08-stok.html', badge: 7 },
    products: { key: 'catalog.products.title', icon: 'tag', href: OUT },
    reports: { key: 'ux.nav.reports', icon: 'chart', href: OUT },
    settings: { key: 'config.settings.title', icon: 'sliders', href: OUT }
  };
  var ROLE_NAV = {
    owner: ['home', 'till', 'sales', 'stock', 'products', 'reports', 'settings'],
    ops: ['stock', 'products'],
    cashier: ['till', 'sales', 'sessions'],
    combined: ['till', 'sales', 'sessions', 'stock', 'products']
  };
  var SUB = {
    stock: [
      { id: 'stock', key: 'inventory.stock.title', href: 'ks-08-stok.html' },
      { id: 'history', key: 'inventory.history.title', href: OUT },
      { id: 'receipts', key: 'inventory.receipts.title', href: OUT },
      { id: 'issues', key: 'inventory.issues.title', href: OUT },
      { id: 'onboarding', key: 'inventory.onboarding.title', href: OUT, owner: true },
      { id: 'incidents', key: 'inventory.incidents.title', href: OUT, owner: true, badge: 1 }
    ],
    products: [
      { id: 'products', key: 'catalog.products.title', href: OUT },
      { id: 'categories', key: 'catalog.categories.title', href: OUT },
      { id: 'csv', key: 'catalog.csv_import.title', href: OUT }
    ],
    reports: [
      { id: 'daily', key: 'reporting.daily.title', href: OUT },
      { id: 'sessions', key: 'cash.sessions.title', href: OUT },
      { id: 'finance', key: 'reporting.finance.title', href: 'ks-10-laporan-keuangan.html' },
      { id: 'worklist', key: 'costing.worklist.title', href: OUT }
    ]
  };

  function currentRole() {
    var allowed = (body.getAttribute('data-roles') || body.getAttribute('data-role') || '').split(/\s+/).filter(Boolean);
    var asked = Q.get('peran');
    if (asked && allowed.indexOf(asked) !== -1) return asked;
    return body.getAttribute('data-role') || 'owner';
  }
  var ROLE = currentRole();
  body.setAttribute('data-role', ROLE);

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function initials(name) { return name.split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase(); }
  function shopMark() { return '<span class="bk-shop__mark" aria-hidden="true">' + esc(initials(SHOP)) + '</span>'; }

  function navItemsHTML(kind) {
    var cur = body.getAttribute('data-nav');
    var curState = body.getAttribute('data-nav-current') || 'page';
    return ROLE_NAV[ROLE].map(function (id) {
      var n = NAV[id];
      var current = id === cur ? ' aria-current="' + curState + '"' : '';
      var label = esc(t(n.key));
      var badge = '';
      if (n.badge) {
        var sr = esc(t('ux.nav.stock_badge.label', { jumlah: n.badge }));
        badge = kind === 'rail'
          ? '<span class="bk-rail__dot" aria-hidden="true">' + n.badge + '</span><span class="bk-sr-only">' + sr + '</span>'
          : '<span class="bk-badge" aria-hidden="true">' + n.badge + '</span><span class="bk-sr-only">' + sr + '</span>';
      }
      if (kind === 'rail') {
        return '<li><a class="bk-rail__link" href="' + n.href + '"' + current + '>' + window.BK_ICON(n.icon, 'bk-icon--lg') +
          '<span class="bk-tip">' + label + '</span>' + badge + '</a></li>';
      }
      return '<li><a class="bk-nav__link" href="' + n.href + '"' + current + '>' + window.BK_ICON(n.icon, 'bk-icon--lg') +
        '<span class="bk-nav__label">' + label + '</span>' + badge + '</a></li>';
    }).join('');
  }

  function accountHTML(where) {
    var u = USERS[ROLE];
    var unresolved = Q.get('banner') === 'unresolved' || body.getAttribute('data-unresolved') === '1' || /unresolved|uncertain|held/.test(Q.get('state') || '');
    var name = esc(u.name);
    var label = esc(t('ux.shell.account_menu', { nama: u.name }));
    var menuId = 'bk-account-menu-' + where;
    var items = '<a class="bk-menu__item" role="menuitem" href="' + OUT + '">' + window.BK_ICON('user') + '<span>' + esc(t('access.account.title')) + '</span></a>';
    if (unresolved) {
      items += '<a class="bk-menu__item" role="menuitem" href="' + OUT + '">' + window.BK_ICON('clock') + '<span>' + esc(t('commands.unresolved.menu_item', { jumlah: 1 })) + '</span></a>';
    }
    items += '<div class="bk-menu__sep" role="separator"></div><a class="bk-menu__item bk-menu__item--danger" role="menuitem" href="ks-01-masuk.html?state=signed-out">' + window.BK_ICON('logout') + '<span>' + esc(t('access.sign_out')) + '</span></a>';
    var visible = where === 'side' ? '<span class="bk-account__name">' + name + '</span>' : '';
    return '<div class="bk-account"><button type="button" class="bk-account__btn" aria-haspopup="menu" aria-expanded="false" aria-controls="' + menuId + '" aria-label="' + label + '">' +
      '<span class="bk-avatar" aria-hidden="true">' + esc(initials(u.name)) + '</span>' + visible + '</button>' +
      '<div class="bk-menu" role="menu" id="' + menuId + '" aria-label="' + label + '" hidden>' + items + '</div></div>';
  }

  function buildShell() {
    var layout = body.getAttribute('data-layout') || 'side';
    var app = document.getElementById('bk-app');
    if (!app || layout === 'none') return;
    app.setAttribute('data-layout', layout);
    var col = app.querySelector('.bk-col');
    var navLabel = esc(t('ux.nav.main'));
    var shell;
    if (layout === 'rail') {
      shell = '<header class="bk-rail" role="banner"><span class="bk-shop__mark" role="img" aria-label="' + esc(SHOP) + '">' + esc(initials(SHOP)) + '</span>' +
        '<nav class="bk-nav" aria-label="' + navLabel + '"><ul class="bk-rail__list" role="list">' + navItemsHTML('rail') + '</ul></nav>' + accountHTML('rail') + '</header>';
    } else {
      shell = '<header class="bk-sidenav" role="banner"><div class="bk-shop">' + shopMark() + '<span class="bk-shop__name">' + esc(SHOP) + '</span></div>' +
        '<nav class="bk-nav" aria-label="' + navLabel + '"><ul class="bk-nav__list" role="list">' + navItemsHTML('side') + '</ul></nav>' + accountHTML('side') + '</header>';
    }
    app.insertAdjacentHTML('afterbegin', shell);

    var titleKey = body.getAttribute('data-title-key');
    var top = '<header class="bk-topbar" role="banner">' +
      '<button type="button" class="bk-btn bk-btn--quiet bk-btn--icon" data-drawer-open aria-expanded="false" aria-controls="bk-drawer" aria-label="' + esc(t('ux.nav.menu_open')) + '">' + window.BK_ICON('menu', 'bk-icon--lg') + '</button>' +
      '<p class="bk-topbar__title">' + esc(t(titleKey)) + '</p>' + accountHTML('top') + '</header>';
    col.insertAdjacentHTML('afterbegin', top);

    var drawer = '<div class="bk-drawer-layer" id="bk-drawer" hidden><div class="bk-drawer-layer__scrim" data-drawer-close></div>' +
      '<div class="bk-drawer" role="dialog" aria-modal="true" aria-label="' + navLabel + '">' +
      '<div class="bk-drawer__head"><div class="bk-shop">' + shopMark() + '<span class="bk-shop__name">' + esc(SHOP) + '</span></div>' +
      '<button type="button" class="bk-btn bk-btn--quiet bk-btn--icon" data-drawer-close aria-label="' + esc(t('ux.nav.menu_close')) + '">' + window.BK_ICON('close', 'bk-icon--lg') + '</button></div>' +
      '<nav class="bk-nav" aria-label="' + navLabel + '"><ul class="bk-nav__list" role="list">' + navItemsHTML('side') + '</ul></nav></div></div>';
    body.insertAdjacentHTML('beforeend', drawer);

    // Global banners (UXI-029, UXI-030)
    var banners = '';
    var bq = Q.get('banner');
    var showUnresolved = bq === 'unresolved' || body.getAttribute('data-unresolved') === '1' || /unresolved|uncertain|held/.test(Q.get('state') || '');
    if (showUnresolved) {
      banners += '<div class="bk-banner" role="region" aria-label="' + esc(t('commands.unresolved.banner')) + '"><p class="bk-banner__text">' + window.BK_ICON('clock') + '<span>' + esc(t('commands.unresolved.banner')) + '</span></p>' +
        '<a class="bk-btn bk-btn--secondary" href="' + OUT + '">' + esc(t('ux.action.open_unresolved')) + '</a></div>';
    }
    if (bq === 'onboarding') {
      banners += '<div class="bk-banner bk-banner--warning" role="region" aria-label="' + esc(t('ux.state.onboarding.title')) + '"><p class="bk-banner__text">' + window.BK_ICON('warning') + '<span><strong>' + esc(t('ux.state.onboarding.title')) + '</strong> ' + esc(t('ux.state.onboarding.body')) + '</span></p>' +
        (ROLE === 'owner' ? '<a class="bk-btn bk-btn--secondary" href="' + OUT + '">' + esc(t('inventory.onboarding.open')) + '</a>' : '') + '</div>';
    }
    if (banners) col.querySelector('.bk-topbar').insertAdjacentHTML('afterend', banners);

    // Sub-navigation: tabs, and a menu button at compact width (UXA-03)
    var subId = body.getAttribute('data-sub-area');
    if (subId && SUB[subId]) {
      var cur = body.getAttribute('data-sub');
      var curState = body.getAttribute('data-sub-current') || 'page';
      var items = SUB[subId].filter(function (s) { return !s.owner || ROLE === 'owner'; });
      var areaTitle = t(NAV[subId].key);
      var aria = esc(t('ux.nav.sub', { halaman: areaTitle }));
      var tabs = '<nav class="bk-subnav" aria-label="' + aria + '"><ul class="bk-subnav__list" role="list">' + items.map(function (s) {
        var c = s.id === cur ? ' aria-current="' + curState + '"' : '';
        var badge = s.badge ? '<span class="bk-badge" aria-hidden="true">' + s.badge + '</span><span class="bk-sr-only">' + esc(t('ux.nav.incidents_badge.label', { jumlah: s.badge })) + '</span>' : '';
        return '<li><a class="bk-subnav__link" href="' + s.href + '"' + c + '>' + esc(t(s.key)) + badge + '</a></li>';
      }).join('') + '</ul></nav>';
      var curItem = items.filter(function (s) { return s.id === cur; })[0] || items[0];
      var menu = '<nav class="bk-submenu" aria-label="' + aria + '"><button type="button" class="bk-submenu__btn" aria-haspopup="true" aria-expanded="false" aria-controls="bk-submenu-list">' +
        '<span>' + esc(t(curItem.key)) + '</span>' + window.BK_ICON('chevron-down') + '</button>' +
        '<div class="bk-menu" id="bk-submenu-list" hidden><ul role="list">' + items.map(function (s) {
          var c = s.id === cur ? ' aria-current="' + curState + '"' : '';
          return '<li><a class="bk-menu__item" href="' + s.href + '"' + c + '>' + esc(t(s.key)) + '</a></li>';
        }).join('') + '</ul></div></nav>';
      var host = document.querySelector('[data-subnav-host]');
      if (host) host.innerHTML = tabs + menu;
    }
  }

  /* ---------------------------------------------------------------------------
     States, roles and accent (review parameters)
     ------------------------------------------------------------------------- */
  var STATE = Q.get('state') || 'default';
  function applyStates() {
    document.querySelectorAll('[data-when]').forEach(function (el) {
      var list = el.getAttribute('data-when').split(/\s+/);
      el.hidden = list.indexOf(STATE) === -1;
    });
    document.querySelectorAll('[data-unless]').forEach(function (el) {
      var list = el.getAttribute('data-unless').split(/\s+/);
      if (list.indexOf(STATE) !== -1) el.hidden = true;
    });
    document.querySelectorAll('[data-role-only]').forEach(function (el) {
      var list = el.getAttribute('data-role-only').split(/\s+/);
      if (list.indexOf(ROLE) === -1) el.remove();
    });
  }
  var ACCENTS = ['green', 'teal', 'blue', 'purple', 'graphite'];
  if (ACCENTS.indexOf(Q.get('aksen')) !== -1) document.documentElement.setAttribute('data-accent', Q.get('aksen'));

  /* ---------------------------------------------------------------------------
     Live regions (UXA-17)
     ------------------------------------------------------------------------- */
  function announce(text, assertive) {
    var el = document.getElementById(assertive ? 'bk-live-assertive' : 'bk-live-polite');
    if (!el) return;
    el.textContent = '';
    setTimeout(function () { el.textContent = text; }, 60);
  }

  /* ---------------------------------------------------------------------------
     Overlays: drawer, dialogs, menus (UXA-05, UXA-08)
     ------------------------------------------------------------------------- */
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  var openLayer = null, lastOpener = null;
  function focusables(root) {
    return Array.prototype.filter.call(root.querySelectorAll(FOCUSABLE), function (el) { return el.offsetParent !== null || el === document.activeElement; });
  }
  function setInert(on) {
    var app = document.getElementById('bk-app');
    if (app) { if (on) app.setAttribute('inert', ''); else app.removeAttribute('inert'); }
  }
  function openOverlay(layer, opener, initial) {
    closeMenus();
    openLayer = layer;
    lastOpener = opener || document.activeElement;
    layer.hidden = false;
    setInert(true);
    var target = initial || layer.querySelector('[data-initial-focus]') || focusables(layer)[0];
    if (target) target.focus();
    capturePaused = true;
  }
  function closeOverlay() {
    if (!openLayer) return;
    var layer = openLayer;
    layer.hidden = true;
    openLayer = null;
    setInert(false);
    capturePaused = false;
    var drawerBtn = document.querySelector('[data-drawer-open]');
    if (drawerBtn) drawerBtn.setAttribute('aria-expanded', 'false');
    if (lastOpener && document.body.contains(lastOpener)) lastOpener.focus();
  }
  window.BK.openDialog = function (id, opener) { var el = document.getElementById(id); if (el) openOverlay(el, opener); };
  window.BK.closeOverlay = closeOverlay;

  document.addEventListener('keydown', function (e) {
    if (!openLayer || e.key !== 'Tab') return;
    var f = focusables(openLayer);
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  function closeMenus(except) {
    document.querySelectorAll('[aria-haspopup][aria-expanded="true"]').forEach(function (btn) {
      if (btn === except) return;
      btn.setAttribute('aria-expanded', 'false');
      var m = document.getElementById(btn.getAttribute('aria-controls'));
      if (m) m.hidden = true;
    });
  }
  function menuItems(menu) { return Array.prototype.slice.call(menu.querySelectorAll('.bk-menu__item')); }

  document.addEventListener('click', function (e) {
    var t1 = e.target.closest('[data-drawer-open]');
    if (t1) { t1.setAttribute('aria-expanded', 'true'); openOverlay(document.getElementById('bk-drawer'), t1); return; }
    if (e.target.closest('[data-drawer-close]') || e.target.closest('[data-dialog-close]')) { e.preventDefault(); closeOverlay(); return; }
    var opener = e.target.closest('[data-dialog-open]');
    if (opener && opener.getAttribute('aria-disabled') !== 'true') { e.preventDefault(); openOverlay(document.getElementById(opener.getAttribute('data-dialog-open')), opener); return; }
    var mb = e.target.closest('[aria-haspopup][aria-controls]');
    if (mb && !mb.hasAttribute('data-drawer-open')) {
      var menu = document.getElementById(mb.getAttribute('aria-controls'));
      var open = mb.getAttribute('aria-expanded') === 'true';
      closeMenus(mb);
      mb.setAttribute('aria-expanded', String(!open));
      menu.hidden = open;
      if (!open) { var first = menuItems(menu)[0]; if (first) first.focus(); }
      return;
    }
    if (!e.target.closest('.bk-menu')) closeMenus();
    var disc = e.target.closest('[data-disclosure]');
    if (disc) {
      var panel = document.getElementById(disc.getAttribute('aria-controls'));
      var isOpen = disc.getAttribute('aria-expanded') === 'true';
      disc.setAttribute('aria-expanded', String(!isOpen));
      panel.hidden = isOpen;
      return;
    }
    if (openLayer && e.target === openLayer && openLayer.classList.contains('bk-dialog-layer')) { closeOverlay(); return; }
    var dis = e.target.closest('[aria-disabled="true"]');
    if (dis) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  document.addEventListener('keydown', function (e) {
    var menu = e.target.closest && e.target.closest('.bk-menu');
    if (menu && (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End')) {
      e.preventDefault();
      var items = menuItems(menu);
      var i = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') i = (i + 1) % items.length;
      if (e.key === 'ArrowUp') i = (i - 1 + items.length) % items.length;
      if (e.key === 'Home') i = 0;
      if (e.key === 'End') i = items.length - 1;
      items[i].focus();
      return;
    }
    if (e.key === 'Escape') {
      var openBtn = document.querySelector('[aria-haspopup][aria-expanded="true"]:not([data-drawer-open])');
      if (openBtn) { closeMenus(); openBtn.focus(); e.preventDefault(); return; }
      if (openLayer) { e.preventDefault(); closeOverlay(); return; }
    }
  });

  /* ---------------------------------------------------------------------------
     Shortcuts (UXA-09, provisional per D-075). Ctrl+Enter that is held or
     repeats from the step before is ignored (one key press never opens a
     review and confirms it). Enter alone never activates a command (UXA-10).
     ------------------------------------------------------------------------- */
  var capturePaused = false;
  var ctrlEnterArmed = true;
  document.addEventListener('keyup', function (e) { if (e.key === 'Enter' || e.key === 'Control') ctrlEnterArmed = true; });
  function activate(el) {
    if (!el || el.hidden || el.getAttribute('aria-disabled') === 'true' || el.disabled) return false;
    el.click();
    return true;
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.ctrlKey) {
      if (e.repeat || !ctrlEnterArmed) { e.preventDefault(); return; }
      var scope = openLayer || document;
      var target = scope.querySelector('[data-ctrl-enter]:not([hidden])');
      if (target && target.offsetParent !== null) {
        e.preventDefault();
        ctrlEnterArmed = false;
        activate(target);
      }
      return;
    }
    if (openLayer) return;
    if (e.key === 'F2') {
      var s = document.querySelector('[data-shortcut-f2]');
      if (s) { e.preventDefault(); s.focus(); s.select && s.select(); }
    } else if (e.key === 'F8') {
      var pay = document.querySelector('[data-shortcut-f8]');
      if (pay) { e.preventDefault(); activate(pay); }
    } else if (e.altKey && (e.key === '1' || e.key === '2')) {
      var r = document.querySelector('[data-shortcut-alt' + e.key + ']');
      if (r) { e.preventDefault(); r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); r.focus(); }
    } else if (e.altKey && (e.key === 'u' || e.key === 'U')) {
      var ex = document.querySelector('[data-shortcut-altu]');
      if (ex && ex.offsetParent !== null) { e.preventDefault(); activate(ex); }
    } else if (e.key === 'Escape') {
      var back = document.querySelector('[data-shortcut-esc]');
      if (back && !e.target.closest('input, textarea, select')) { e.preventDefault(); activate(back); }
    }
  });

  /* ---------------------------------------------------------------------------
     Page modules
     ------------------------------------------------------------------------- */
  var PAGES = {};

  /* Sign-in (UXP-001) */
  PAGES['UXP-001'] = function () {
    var toggle = document.getElementById('pw-toggle');
    var pw = document.getElementById('pw');
    if (toggle) toggle.addEventListener('click', function () {
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', String(show));
      toggle.setAttribute('aria-label', t(show ? 'ux.action.hide_password' : 'ux.action.show_password'));
      toggle.innerHTML = window.BK_ICON(show ? 'eye-off' : 'eye');
    });
    var form = document.getElementById('signin');
    if (form) form.addEventListener('submit', function (e) {
      e.preventDefault();
      var b = form.querySelector('[type="submit"]');
      b.setAttribute('aria-busy', 'true');
      b.innerHTML = '<span class="bk-spinner" aria-hidden="true"></span><span>' + esc(t('ux.state.submitting.title')) + '</span>';
      setTimeout(function () { location.href = 'ks-02-beranda.html'; }, 900);
    });
  };

  /* Till (UXP-101) */
  var PRODUCTS = [
    { id: 1, name: 'Pulpen gel hitam 0,5 mm', sku: 'ATK-PG-05H', code: '8991234500017', price: 4500, stock: 48, unit: 'pcs', dec: 0 },
    { id: 2, name: 'Pulpen gel biru 0,5 mm', sku: 'ATK-PG-05B', code: '8991234500024', price: 4500, stock: 31, unit: 'pcs', dec: 0 },
    { id: 3, name: 'Buku tulis bergaris 58 lembar', sku: 'ATK-BT-58', code: '8991234500031', price: 6000, stock: 120, unit: 'pcs', dec: 0 },
    { id: 4, name: 'Kertas HVS A4 70 gsm isi 500 lembar', sku: 'ATK-HVS-A470', code: '8991234500048', price: 52000, stock: 6, unit: 'rim', dec: 0 },
    { id: 5, name: 'Binder kantor dua cincin ukuran folio dengan sampul plastik tebal tahan air dan kantong depan bening untuk label nama', sku: 'ATK-BND-F2C-BENING-TEBAL', code: '8991234500055', price: 38500, stock: 14, unit: 'pcs', dec: 0 },
    { id: 6, name: 'Kertas kado motif garis per meter', sku: 'ATK-KK-GRS', code: '8991234500062', price: 7000, stock: 42.5, unit: 'm', dec: 1 },
    { id: 7, name: 'Pulpen tinta cair merah 0,7 mm', sku: 'ATK-PT-07M', code: '8991234500079', price: 5500, stock: 0, unit: 'pcs', dec: 0, inactive: true }
  ];
  function byId(id) { return PRODUCTS.filter(function (p) { return p.id === id; })[0]; }

  PAGES['UXP-101'] = function () {
    var cart = [];
    if (STATE !== 'empty' && STATE !== 'opened') cart = [{ id: 1, q: 2 }, { id: 4, q: 1 }, { id: 6, q: 2.5 }, { id: 5, q: 1 }];
    var changed = STATE === 'price-changed' ? { 4: { old: 49500 } } : {};
    var search = document.getElementById('till-search');
    var results = document.getElementById('till-results');
    var linesEl = document.getElementById('cart-lines');
    var totalEl = document.getElementById('cart-total');
    var pay = document.getElementById('pay');
    var payReason = document.getElementById('pay-reason');

    function resultHTML(p) {
      var stock = qty(p.stock, p.dec) + ' ' + p.unit;
      var thumb = '<span class="bk-thumb" role="img" aria-label="' + esc(t('catalog.photo.placeholder_alt')) + '">' + window.BK_ICON('image') + '</span>';
      var meta = '<span class="bk-result__meta"><span class="bk-mono">' + esc(p.sku) + '</span><span>' + esc(t('sales.till.result_stock', { jumlah: stock })) + '</span></span>';
      if (p.inactive) {
        return '<div class="bk-result is-inactive">' + thumb + '<span><span class="bk-result__name bk-clamp-2">' + esc(p.name) + '</span>' + meta + '</span>' +
          '<span class="bk-status bk-status--inactive"><span class="bk-status__dot" aria-hidden="true"></span>' + esc(t('ux.state.inactive.label')) + '</span></div>';
      }
      return '<button type="button" class="bk-result" data-add="' + p.id + '" aria-label="' + esc(t('sales.till.add_to_cart_named', { produk: p.name })) + '">' + thumb +
        '<span><span class="bk-result__name bk-clamp-2">' + esc(p.name) + '</span>' + meta + '</span>' +
        '<span class="bk-result__price">' + rp(p.price) + '<span class="bk-result__add" aria-hidden="true">' + esc(t('sales.till.add_to_cart')) + '</span></span></button>';
    }
    function renderResults(term) {
      term = (term || '').trim().toLowerCase();
      var list = PRODUCTS.filter(function (p) { return !term || p.name.toLowerCase().indexOf(term) !== -1 || p.sku.toLowerCase().indexOf(term) !== -1; });
      results.innerHTML = list.map(resultHTML).join('');
      document.getElementById('till-noresults').hidden = list.length > 0;
    }
    function lineHTML(l, i) {
      var p = byId(l.id);
      var sub = rp(p.price * l.q);
      var minQ = p.dec ? Math.pow(10, -p.dec) : 1;
      var decDisabled = l.q <= minQ;
      var reasonId = 'dec-reason-' + p.id;
      var ch = changed[p.id];
      var priceLine = ch ? '<p class="bk-line__meta"><span class="bk-status bk-status--warning">' + window.BK_ICON('warning') + esc(t('ux.state.price_changed.line', { lama: rp(ch.old), baru: rp(p.price) })) + '</span></p>' : '';
      return '<li class="bk-line' + (ch ? ' is-changed' : '') + '" data-line="' + p.id + '">' +
        '<div class="bk-line__top"><div><p class="bk-line__name">' + esc(p.name) + '</p><p class="bk-line__meta"><span class="bk-mono">' + esc(p.sku) + '</span> · ' + rp(p.price) + '</p>' + priceLine + '</div>' +
        '<div class="bk-line__total"><span class="bk-line__sub">' + esc(t('sales.cart.line_total')) + '</span><br>' + sub + '</div></div>' +
        '<div class="bk-line__controls"><div class="bk-stepper" role="group" aria-label="' + esc(t('sales.cart.quantity')) + ' ' + esc(p.name) + '">' +
        '<button type="button" data-dec="' + p.id + '" aria-label="' + esc(t('sales.cart.decrease', { satuan: p.unit, produk: p.name })) + '"' + (decDisabled ? ' aria-disabled="true" aria-describedby="' + reasonId + '"' : '') + '>−</button>' +
        '<input type="text" inputmode="' + (p.dec ? 'decimal' : 'numeric') + '" value="' + qty(l.q, p.dec) + '" data-qty="' + p.id + '" aria-label="' + esc(t('sales.cart.quantity')) + ' ' + esc(p.name) + '">' +
        '<button type="button" data-inc="' + p.id + '" aria-label="' + esc(t('sales.cart.increase', { satuan: p.unit, produk: p.name })) + '">+</button></div>' +
        '<span class="bk-line__unit">' + esc(p.unit) + '</span>' +
        '<button type="button" class="bk-btn bk-btn--quiet" data-remove="' + p.id + '" aria-label="' + esc(t('sales.cart.remove_named', { produk: p.name })) + '">' + window.BK_ICON('trash') + '<span>' + esc(t('sales.cart.remove')) + '</span></button></div>' +
        (decDisabled ? '<p class="bk-reason" id="' + reasonId + '">' + window.BK_ICON('info') + '<span>' + esc(t('sales.cart.decrease_disabled')) + '</span></p>' : '') +
        '</li>';
    }
    function total() { return cart.reduce(function (s, l) { return s + Math.round(byId(l.id).price * l.q); }, 0); }
    function render(flashId) {
      linesEl.innerHTML = cart.map(lineHTML).join('');
      document.getElementById('cart-empty').hidden = cart.length > 0;
      document.getElementById('cart-clear').hidden = cart.length === 0;
      totalEl.textContent = rp(total());
      var reasonKey = null;
      if (!cart.length) reasonKey = 'sales.cart.disabled_empty';
      else if (STATE === 'price-changed' && Object.keys(changed).length) reasonKey = 'sales.cart.disabled_price_review';
      else if (STATE === 'unresolved') reasonKey = 'sales.cart.disabled_unresolved';
      if (reasonKey) {
        pay.setAttribute('aria-disabled', 'true');
        pay.setAttribute('aria-describedby', 'pay-reason');
        payReason.hidden = false;
        payReason.querySelector('[data-reason-text]').textContent = t(reasonKey);
        payReason.querySelector('[data-reason-link]').hidden = reasonKey !== 'sales.cart.disabled_unresolved';
      } else {
        pay.removeAttribute('aria-disabled');
        pay.removeAttribute('aria-describedby');
        payReason.hidden = true;
      }
      pay.setAttribute('data-total', total());
      if (flashId) {
        var li = linesEl.querySelector('[data-line="' + flashId + '"]');
        if (li) { li.classList.add('is-flash'); li.scrollIntoView({ block: 'nearest' }); }
      }
    }
    function add(id) {
      var p = byId(id);
      if (!p || p.inactive) return;
      var l = cart.filter(function (x) { return x.id === id; })[0];
      if (l) l.q = Math.round((l.q + 1) * 1000) / 1000; else { l = { id: id, q: 1 }; cart.push(l); }
      render(id);
      announce(t('ux.announce.added', { produk: p.name, jumlah: qty(l.q, p.dec) + ' ' + p.unit }));
      search.focus();
    }
    results.addEventListener('click', function (e) {
      var b = e.target.closest('[data-add]');
      if (b) add(parseInt(b.getAttribute('data-add'), 10));
    });
    results.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      var rows = Array.prototype.slice.call(results.querySelectorAll('button.bk-result'));
      var i = rows.indexOf(document.activeElement);
      if (i === -1) return;
      e.preventDefault();
      if (e.key === 'ArrowDown' && i < rows.length - 1) rows[i + 1].focus();
      if (e.key === 'ArrowUp') { if (i > 0) rows[i - 1].focus(); else search.focus(); }
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { var r = results.querySelector('button.bk-result'); if (r) { e.preventDefault(); r.focus(); } }
      if (e.key !== 'Enter' || e.ctrlKey) return;
      e.preventDefault(); // Enter ends a code and runs the lookup, never a command (UXA-10)
      var v = search.value.trim();
      var exact = PRODUCTS.filter(function (p) { return p.code === v || p.sku.toLowerCase() === v.toLowerCase(); })[0];
      var unknown = document.getElementById('till-unknown');
      if (exact) { unknown.hidden = true; search.value = ''; renderResults(''); add(exact.id); return; }
      if (/^\d{8,}$/.test(v)) {
        unknown.hidden = false;
        unknown.querySelector('[data-unknown-body]').textContent = t('ux.state.unknown_code.body', { kode: v });
        announce(t('ux.announce.unknown_code', { kode: v }));
        return;
      }
      unknown.hidden = true;
      renderResults(v);
    });
    search.addEventListener('input', function () { if (!/^\d+$/.test(search.value)) renderResults(search.value); });
    linesEl.addEventListener('click', function (e) {
      var inc = e.target.closest('[data-inc]'), dec = e.target.closest('[data-dec]'), rem = e.target.closest('[data-remove]');
      if (inc) { var id = +inc.getAttribute('data-inc'); var l = cart.filter(function (x) { return x.id === id; })[0]; l.q = Math.round((l.q + 1) * 1000) / 1000; render(id); search.focus(); }
      if (dec && dec.getAttribute('aria-disabled') !== 'true') { var id2 = +dec.getAttribute('data-dec'); var l2 = cart.filter(function (x) { return x.id === id2; })[0]; l2.q = Math.max(1, Math.round((l2.q - 1) * 1000) / 1000); render(id2); search.focus(); }
      if (rem) { var id3 = +rem.getAttribute('data-remove'); cart = cart.filter(function (x) { return x.id !== id3; }); render(); search.focus(); }
    });
    linesEl.addEventListener('keydown', function (e) {
      var inp = e.target.closest('[data-qty]');
      if (!inp || e.key !== 'Enter') return;
      e.preventDefault();
      var id = +inp.getAttribute('data-qty');
      var p = byId(id);
      var v = parseFloat(inp.value.replace(/\./g, '').replace(',', '.'));
      if (!isNaN(v) && v > 0) { cart.filter(function (x) { return x.id === id; })[0].q = Math.round(v * Math.pow(10, p.dec)) / Math.pow(10, p.dec); }
      render(id);
      search.focus();
    });
    var clearConfirm = document.getElementById('clear-confirm');
    if (clearConfirm) clearConfirm.addEventListener('click', function () { cart = []; closeOverlay(); render(); search.focus(); });
    var accept = document.getElementById('accept-prices');
    if (accept) accept.addEventListener('click', function () { changed = {}; document.getElementById('price-notice').hidden = true; STATE = 'default'; render(); search.focus(); });
    pay.addEventListener('click', function (e) {
      if (pay.getAttribute('aria-disabled') === 'true') return;
      location.href = 'ks-04-pembayaran.html?total=' + total();
    });
    renderResults(STATE === 'empty' || STATE === 'opened' ? '' : 'pulpen');
    if (STATE !== 'empty' && STATE !== 'opened') search.value = 'pulpen';
    render();
    setTimeout(function () { search.focus(); }, 0);
  };

  /* Payment (UXP-102): one screen, one final action (D-040) */
  PAGES['UXP-102'] = function () {
    var total = parseInt(Q.get('total'), 10) || 119000;
    document.getElementById('pay-total').textContent = rp(total);
    var cash = document.getElementById('m-cash'), transfer = document.getElementById('m-transfer');
    var tendered = document.getElementById('tendered');
    var changeBox = document.getElementById('change-box');
    var changeLabel = document.getElementById('change-label');
    var changeVal = document.getElementById('change-value');
    var finalBtn = document.getElementById('final');
    var finalLabel = document.getElementById('final-label');
    var reason = document.getElementById('final-reason');
    function method() { return transfer.checked ? 'transfer' : 'cash'; }
    function update() {
      var m = method();
      document.querySelectorAll('[data-method]').forEach(function (el) { el.hidden = el.getAttribute('data-method') !== m; });
      var key = m === 'cash' ? 'sales.payment.final_cash' : 'sales.payment.final_transfer';
      finalLabel.textContent = t(key);
      finalBtn.setAttribute('aria-label', t(key) + ' (Ctrl+Enter)');
      if (m === 'transfer') { finalBtn.removeAttribute('aria-disabled'); finalBtn.removeAttribute('aria-describedby'); reason.hidden = true; return; }
      var v = parseRp(tendered.value);
      if (v !== null) tendered.value = group(v);
      if (v === null) {
        changeBox.classList.remove('is-short'); changeLabel.textContent = t('sales.payment.change'); changeVal.textContent = '—';
        finalBtn.setAttribute('aria-disabled', 'true'); finalBtn.setAttribute('aria-describedby', 'final-reason');
        reason.hidden = false; reason.querySelector('[data-reason-text]').textContent = t('sales.payment.disabled_tender', { uang: rp(total) });
      } else if (v < total) {
        changeBox.classList.add('is-short'); changeLabel.textContent = t('ux.state.tender_low.title'); changeVal.textContent = t('ux.state.tender_low.body', { uang: rp(total - v) });
        finalBtn.setAttribute('aria-disabled', 'true'); finalBtn.setAttribute('aria-describedby', 'final-reason');
        reason.hidden = false; reason.querySelector('[data-reason-text]').textContent = t('sales.payment.disabled_tender', { uang: rp(total - v) });
      } else {
        changeBox.classList.remove('is-short'); changeLabel.textContent = t('sales.payment.change'); changeVal.textContent = rp(v - total);
        finalBtn.removeAttribute('aria-disabled'); finalBtn.removeAttribute('aria-describedby'); reason.hidden = true;
      }
    }
    [cash, transfer].forEach(function (r) { r.addEventListener('change', function () { update(); if (method() === 'cash') tendered.focus(); }); });
    tendered.addEventListener('input', update);
    tendered.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.ctrlKey) e.preventDefault(); });
    document.getElementById('exact').addEventListener('click', function () { tendered.value = group(total); update(); tendered.focus(); });
    finalBtn.addEventListener('click', function () {
      if (finalBtn.getAttribute('aria-disabled') === 'true') return;
      finalBtn.setAttribute('aria-busy', 'true');
      finalBtn.setAttribute('aria-disabled', 'true');
      finalLabel.textContent = t('ux.state.submitting.title');
      finalBtn.insertAdjacentHTML('afterbegin', '<span class="bk-spinner" aria-hidden="true"></span>');
      setTimeout(function () { location.href = 'ks-05-struk.html'; }, 1100);
    });
    if (STATE === 'transfer') transfer.checked = true;
    if (STATE === 'short') tendered.value = group(total - 4000);
    if (STATE === 'default') tendered.value = '';
    update();
    if (STATE === 'submitting') { finalBtn.setAttribute('aria-busy', 'true'); finalBtn.setAttribute('aria-disabled', 'true'); finalLabel.textContent = t('ux.state.submitting.title'); finalBtn.insertAdjacentHTML('afterbegin', '<span class="bk-spinner" aria-hidden="true"></span>'); }
    if (STATE === 'uncertain' || STATE === 'held') {
      tendered.value = group(total + 1000); update();
      // The request is frozen on its page (NC-09): nothing in the form can change it.
      document.querySelectorAll('[data-pay-form] input, [data-pay-form] button').forEach(function (el) {
        if (el.type === 'radio') el.disabled = true;
        else if (el.tagName === 'INPUT') el.readOnly = true;
        else el.setAttribute('aria-disabled', 'true');
      });
      var panel = document.getElementById('uncertain');
      announce(t('ux.announce.uncertain', { pesan: t('commands.family.sale.must_not') }), true);
      setTimeout(function () { panel.querySelector('[data-initial-focus]:not([hidden])').focus(); }, 0);
    } else {
      setTimeout(function () { (method() === 'cash' ? tendered : transfer).focus(); }, 0);
    }
  };

  /* Receipt (UXP-103) */
  PAGES['UXP-103'] = function () {
    var paper = document.getElementById('paper');
    document.querySelectorAll('input[name="size"]').forEach(function (r) {
      r.addEventListener('change', function () { paper.className = 'bk-paper bk-paper--' + r.value; document.querySelectorAll('[data-size-only]').forEach(function (el) { el.hidden = el.getAttribute('data-size-only') !== r.value; }); });
    });
    var size = Q.get('ukuran');
    if (size) { var r = document.querySelector('input[name="size"][value="' + size + '"]'); if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); } }
    document.getElementById('print').addEventListener('click', function () { window.print(); });
    var s = document.getElementById('next-search');
    s.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); location.href = 'ks-03-kasir.html?state=empty'; } });
    setTimeout(function () { s.focus(); }, 0);
  };

  /* Open cash session (UXP-104) */
  PAGES['UXP-104'] = function () {
    var reg = document.getElementById('register'), cash = document.getElementById('opening');
    var review = document.getElementById('review-btn'), reason = document.getElementById('review-reason');
    function update() {
      var v = parseRp(cash.value);
      if (v !== null) cash.value = group(v);
      var ok = reg.value !== '' && v !== null;
      if (ok) { review.removeAttribute('aria-disabled'); review.removeAttribute('aria-describedby'); reason.hidden = true; }
      else { review.setAttribute('aria-disabled', 'true'); review.setAttribute('aria-describedby', 'review-reason'); reason.hidden = false; }
      var body2 = document.getElementById('open-review-body');
      if (body2) body2.textContent = t('cash.open.review_body', { laci: reg.options[reg.selectedIndex].text, uang: rp(v || 0) });
    }
    reg.addEventListener('change', update);
    cash.addEventListener('input', update);
    cash.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    document.getElementById('open-confirm').addEventListener('click', function () { location.href = 'ks-03-kasir.html?state=opened'; });
    if (STATE === 'review') { reg.value = 'Laci 1'; cash.value = '200.000'; }
    update();
    if (STATE === 'review') setTimeout(function () { window.BK.openDialog('open-review', review); }, 0);
    else setTimeout(function () { (reg.value ? cash : reg).focus(); }, 0);
  };

  /* Close own cash session (UXP-105) */
  PAGES['UXP-105'] = function () {
    var expected = 1206500;
    var step1 = document.getElementById('step-1'), step2 = document.getElementById('step-2');
    var s1 = document.getElementById('st-1'), s2 = document.getElementById('st-2');
    var phys = document.getElementById('physical'), review = document.getElementById('close-review-btn'), reason = document.getElementById('close-reason');
    function goStep(n) {
      step1.hidden = n !== 1; step2.hidden = n !== 2;
      if (n === 2) { s1.removeAttribute('aria-current'); s1.classList.add('is-done'); s2.setAttribute('aria-current', 'step'); phys.focus(); }
      else { s2.removeAttribute('aria-current'); s1.classList.remove('is-done'); s1.setAttribute('aria-current', 'step'); }
    }
    function update() {
      var v = parseRp(phys.value);
      if (v !== null) phys.value = group(v);
      if (v === null) { review.setAttribute('aria-disabled', 'true'); review.setAttribute('aria-describedby', 'close-reason'); reason.hidden = false; }
      else { review.removeAttribute('aria-disabled'); review.removeAttribute('aria-describedby'); reason.hidden = true; }
      var d = (v || 0) - expected;
      var word = d < 0 ? t('ux.format.variance_short') : (d > 0 ? t('ux.format.variance_over') : t('ux.format.variance_even'));
      document.getElementById('rv-physical').textContent = rp(v || 0);
      document.getElementById('rv-variance').textContent = t('ux.format.variance', { uang: rp(d), keterangan: word });
      var vs = document.getElementById('rv-variance-status');
      vs.className = 'bk-status ' + (d === 0 ? 'bk-status--success' : 'bk-status--warning');
      vs.querySelector('[data-icon-slot]').innerHTML = window.BK_ICON(d === 0 ? 'check' : 'warning');
    }
    document.getElementById('continue').addEventListener('click', function () { goStep(2); });
    document.getElementById('back-preview').addEventListener('click', function () { goStep(1); document.getElementById('continue').focus(); });
    phys.addEventListener('input', update);
    phys.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    document.getElementById('close-confirm').addEventListener('click', function () {
      closeOverlay();
      document.getElementById('close-success').hidden = false;
      step2.hidden = true; document.getElementById('steps').hidden = true;
      document.getElementById('close-success').querySelector('[tabindex]').focus();
    });
    if (STATE === 'count' || STATE === 'review') { goStep(2); phys.value = '1.204.500'; }
    update();
    if (STATE === 'review') setTimeout(function () { window.BK.openDialog('close-review', review); }, 0);
    else if (STATE !== 'count') setTimeout(function () { document.getElementById('continue').focus(); }, 0);
  };

  /* Stock (UXP-401) */
  PAGES['UXP-401'] = function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-stock-row]'));
    var chips = Array.prototype.slice.call(document.querySelectorAll('[data-chip]'));
    var search = document.getElementById('stock-search');
    function apply() {
      var on = chips.filter(function (c) { return c.getAttribute('aria-pressed') === 'true'; }).map(function (c) { return c.getAttribute('data-chip'); });
      var term = (search.value || '').trim().toLowerCase();
      var n = 0;
      rows.forEach(function (r) {
        var ok = (!on.length || on.indexOf(r.getAttribute('data-state')) !== -1) && (!term || r.textContent.toLowerCase().indexOf(term) !== -1);
        r.hidden = !ok; if (ok) n++;
      });
      document.getElementById('stock-noresults').hidden = n > 0;
      document.getElementById('stock-table').hidden = n === 0;
      document.getElementById('stock-count').textContent = t('ux.list.count', { jumlah: n });
    }
    chips.forEach(function (c) { c.addEventListener('click', function () { c.setAttribute('aria-pressed', String(c.getAttribute('aria-pressed') !== 'true')); apply(); }); });
    search.addEventListener('input', apply);
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    document.getElementById('clear-filters').addEventListener('click', function () { chips.forEach(function (c) { c.setAttribute('aria-pressed', 'false'); }); search.value = ''; apply(); search.focus(); });
    document.getElementById('reload').addEventListener('click', function () { announce(t('ux.announce.loaded')); });
    if (STATE === 'no-results') { search.value = 'stapler'; }
    if (STATE === 'attention') { chips.forEach(function (c) { if (c.getAttribute('data-chip') !== 'normal') c.setAttribute('aria-pressed', 'true'); }); }
    if (STATE !== 'empty') apply();
  };

  /* New receiving (UXP-405) */
  PAGES['UXP-405'] = function () {
    var linesHost = document.getElementById('rcv-lines');
    function sync() {
      var lines = linesHost.querySelectorAll('[data-rcv-line]');
      var unknown = linesHost.querySelectorAll('[data-unknown]:checked').length;
      lines.forEach(function (li) {
        var unk = li.querySelector('[data-unknown]').checked;
        var cost = li.querySelector('[data-cost]');
        cost.disabled = unk;
        li.querySelector('[data-cost-wrap]').classList.toggle('is-disabled', unk);
        li.querySelector('[data-unknown-hint]').hidden = !unk;
        var zero = !unk && parseRp(cost.value) === 0;
        li.querySelector('[data-zero-reason]').hidden = !zero;
      });
      document.getElementById('rv-lines').textContent = t('inventory.receipt_new.review_lines', { jumlah: lines.length });
      document.getElementById('rv-unknown').textContent = t('inventory.receipt_new.review_unknown', { jumlah: unknown });
      document.getElementById('rv-unknown').hidden = unknown === 0;
      var tb = document.getElementById('rcv-review-btn'), rs = document.getElementById('rcv-reason');
      if (lines.length) { tb.removeAttribute('aria-disabled'); tb.removeAttribute('aria-describedby'); rs.hidden = true; }
      else { tb.setAttribute('aria-disabled', 'true'); tb.setAttribute('aria-describedby', 'rcv-reason'); rs.hidden = false; }
    }
    linesHost.addEventListener('change', sync);
    linesHost.addEventListener('input', function (e) {
      var c = e.target.closest('[data-cost]');
      if (c) { var v = parseRp(c.value); if (v !== null) c.value = group(v); }
      sync();
    });
    linesHost.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove-line]');
      if (rm) { rm.closest('[data-rcv-line]').remove(); sync(); document.getElementById('rcv-search').focus(); }
    });
    var s = document.getElementById('rcv-search');
    s.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.ctrlKey) return;
      e.preventDefault();
      var v = s.value.trim();
      var unk = document.getElementById('rcv-unknown');
      if (/^\d{8,}$/.test(v) && !PRODUCTS.some(function (p) { return p.code === v; })) {
        unk.hidden = false; unk.querySelector('[data-unknown-body]').textContent = t('ux.state.unknown_code.body', { kode: v });
        announce(t('ux.announce.unknown_code', { kode: v }));
      } else { unk.hidden = true; }
    });
    document.getElementById('rcv-confirm').addEventListener('click', function () {
      closeOverlay();
      var ok = document.getElementById('rcv-success');
      ok.hidden = false;
      ok.querySelector('[tabindex]').focus();
      announce(t('ux.announce.saved', { pesan: t('inventory.receipt_new.success') }));
    });
    if (STATE === 'unknown-code') { s.value = '8990001112223'; var unk2 = document.getElementById('rcv-unknown'); unk2.hidden = false; unk2.querySelector('[data-unknown-body]').textContent = t('ux.state.unknown_code.body', { kode: s.value }); }
    sync();
    if (STATE === 'review') setTimeout(function () { window.BK.openDialog('rcv-review', document.getElementById('rcv-review-btn')); }, 0);
    else setTimeout(function () { s.focus(); }, 0);
  };

  /* Finance report (UXP-502) with the trend chart (UXA-19) */
  /* Fixture dates in the D-018 format; month names are COPY_DECK 1.3 values */
  var MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  function dateText(d) { return (d < 10 ? '0' : '') + d + ' ' + MONTHS[9] + ' 2026'; }
  PAGES['UXP-502'] = function () {
    var data = [1180000, 1342500, 965000, 1510000, 1275500, 1890000, 2104000, 1233000, 1418500, 1067000, 1622000, 1784500, 2010000, 2245500, 1356000, 1498000, 1312500];
    var chart = document.getElementById('chart');
    var tip = document.getElementById('chart-tip');
    var live = document.getElementById('chart-live');
    var W = 720, H = 240, PADL = 64, PADR = 12, PADT = 16, PADB = 28;
    var max = 2500000;
    function x(i) { return PADL + (W - PADL - PADR) * (data.length === 1 ? 0.5 : i / (data.length - 1)); }
    function y(v) { return PADT + (H - PADT - PADB) * (1 - v / max); }
    var grid = '', i;
    for (i = 0; i <= 5; i++) {
      var gv = max * i / 5;
      grid += '<line class="bk-chart__grid" x1="' + PADL + '" x2="' + (W - PADR) + '" y1="' + y(gv) + '" y2="' + y(gv) + '"/>' +
        '<text class="bk-chart__axis" x="' + (PADL - 8) + '" y="' + (y(gv) + 4) + '" text-anchor="end">' + rp(gv) + '</text>';
    }
    var pts = data.map(function (v, k) { return x(k) + ',' + y(v); }).join(' ');
    var area = 'M' + x(0) + ',' + y(0) + ' L' + pts.split(' ').join(' L') + ' L' + x(data.length - 1) + ',' + y(0) + ' Z';
    var ticks = data.map(function (v, k) { return (k % 2 === 0) ? '<text class="bk-chart__axis" x="' + x(k) + '" y="' + (H - 8) + '" text-anchor="middle">' + (k + 1) + '</text>' : ''; }).join('');
    var dots = data.map(function (v, k) { return '<circle class="bk-chart__pt" data-i="' + k + '" cx="' + x(k) + '" cy="' + y(v) + '" r="4"/><circle class="bk-chart__hit" data-i="' + k + '" cx="' + x(k) + '" cy="' + y(v) + '" r="14"/>'; }).join('');
    chart.querySelector('svg').innerHTML = grid + ticks + '<path class="bk-chart__area" d="' + area + '"/><polyline class="bk-chart__line" points="' + pts + '"/>' + dots + '<circle class="bk-chart__focus" id="chart-ring" r="9" cx="-20" cy="-20"/>';
    var active = -1;
    function show(k, speak) {
      active = k;
      var svg = chart.querySelector('svg');
      var box = svg.getBoundingClientRect();
      var sx = box.width / W, sy = box.height / H;
      var text = t('reporting.finance.trend_point', { tanggal: dateText(k + 1), nilai: rp(data[k]) });
      tip.hidden = false;
      tip.textContent = text;
      tip.style.left = (x(k) * sx) + 'px';
      tip.style.top = (y(data[k]) * sy) + 'px';
      svg.querySelectorAll('.bk-chart__pt').forEach(function (c) { c.classList.toggle('is-active', +c.getAttribute('data-i') === k); });
      var ring = document.getElementById('chart-ring');
      ring.setAttribute('cx', x(k)); ring.setAttribute('cy', y(data[k]));
      ring.style.display = document.activeElement === chart ? '' : 'none';
      if (speak) live.textContent = text;
    }
    chart.addEventListener('focus', function () { show(active < 0 ? data.length - 1 : active, true); });
    chart.addEventListener('blur', function () { tip.hidden = true; document.getElementById('chart-ring').style.display = 'none'; });
    chart.addEventListener('keydown', function (e) {
      var k = active < 0 ? data.length - 1 : active;
      if (e.key === 'ArrowRight') k = Math.min(data.length - 1, k + 1);
      else if (e.key === 'ArrowLeft') k = Math.max(0, k - 1);
      else if (e.key === 'Home') k = 0;
      else if (e.key === 'End') k = data.length - 1;
      else return;
      e.preventDefault();
      show(k, true);
    });
    chart.addEventListener('pointermove', function (e) { var h = e.target.closest('[data-i]'); if (h) show(+h.getAttribute('data-i'), false); });
    chart.addEventListener('pointerdown', function (e) { var h = e.target.closest('[data-i]'); if (h) show(+h.getAttribute('data-i'), true); });
    chart.addEventListener('pointerleave', function () { if (document.activeElement !== chart) tip.hidden = true; });
    var tbody = document.getElementById('trend-rows');
    tbody.innerHTML = data.map(function (v, k) { return '<tr><td>' + dateText(k + 1) + '</td><td class="is-num">' + rp(v) + '</td></tr>'; }).join('');
    var toggle = document.getElementById('toggle-table');
    toggle.addEventListener('click', function () {
      var showTable = document.getElementById('trend-table').hidden;
      document.getElementById('trend-table').hidden = !showTable;
      chart.hidden = showTable;
      document.getElementById('chart-range').hidden = showTable;
      toggle.setAttribute('aria-pressed', String(showTable));
      toggle.querySelector('[data-toggle-label]').textContent = t(showTable ? 'reporting.finance.show_chart' : 'reporting.finance.show_table');
    });
    document.querySelectorAll('input[name="period"]').forEach(function (r) {
      r.addEventListener('change', function () { document.getElementById('custom-range').hidden = r.value !== 'custom'; });
    });
    document.getElementById('custom-cancel').addEventListener('click', function () { document.getElementById('p-month').checked = true; document.getElementById('custom-range').hidden = true; document.getElementById('p-month').focus(); });
    if (STATE === 'table') toggle.click();
  };

  /* Owner home (UXP-007) */
  PAGES['UXP-007'] = function () {
    var retry = document.getElementById('retry-today');
    if (retry) retry.addEventListener('click', function () { location.href = 'ks-02-beranda.html'; });
  };

  /* ---------------------------------------------------------------------------
     Boot
     ------------------------------------------------------------------------- */
  function boot() {
    var titleKey = body.getAttribute('data-title-key');
    if (titleKey) {
      document.title = body.getAttribute('data-layout') === 'none'
        ? t('ux.shell.document_title_public', { halaman: t(titleKey) })
        : t('ux.shell.document_title', { halaman: t(titleKey), toko: SHOP });
    }
    buildShell();
    applyStates();
    renderCopy(document);
    var mod = PAGES[body.getAttribute('data-page')];
    if (mod) mod();
    var first = document.querySelector('[data-first-focus]');
    if (first && !document.activeElement.matches('input, button, select, textarea, [tabindex="0"]')) first.focus();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
