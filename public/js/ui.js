/* 48Source interface behaviour: custom select and date controls, popovers, tooltips, toasts, table helpers.
   Plain script, no build step. Loaded once and kept across wire:navigate page changes. */
(() => {
  'use strict';
  if (window.__ui48) return; window.__ui48 = true;
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c]);
  const ic = (n, s = 16) => { const t = document.getElementById('ic-' + n); return t ? t.innerHTML.replace(/width="\d+" height="\d+"/, `width="${s}" height="${s}"`) : ''; };
  const MN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const tx = s => (window.I18N || {})[s] || s;
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'].map(tx);
  const pad = n => String(n).padStart(2, '0');
  const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const addD = (v, n) => { const d = new Date(v + 'T12:00'); d.setDate(d.getDate() + n); return iso(d); };
  const fd = v => { if (!v) return ''; const [d, t] = v.split('T'), [y, m, dd] = d.split('-').map(Number); return `${dd} ${tx(MN[m - 1])} ${y}` + (t ? `, ${t.slice(0, 5)}` : ''); };
  const fire = el => { el.dispatchEvent(new Event('input', {bubbles: true})); el.dispatchEvent(new Event('change', {bubbles: true})); };
  const isDate = el => el.tagName === 'INPUT' && (el.type === 'date' || el.type === 'datetime-local');

  /* ---------- popover ---------- */
  let POP = null, CS = null, DP = null;
  const alpinePops = new Set();
  function closeAlpinePops(except = null) { alpinePops.forEach(pop => { if (pop !== except) pop.close(); }); }
  function closePop() { if (POP) { POP.el.remove(); POP = null; } }
  function placePop(anchor, p, o = {}) {
    const edge = 8, gap = 6, r = anchor.getBoundingClientRect(), w = Math.min(o.w || 220, innerWidth - edge * 2);
    p.style.width = w + 'px';
    const below = innerHeight - r.bottom - edge, above = r.top - edge, down = below >= above, free = down ? below : above;
    p.style.maxHeight = Math.max(1, Math.min(320, free - gap)) + 'px'; p.style.overflowY = 'auto';
    const h = p.offsetHeight;
    let left = o.left ? r.left : r.right - w; left = Math.max(edge, Math.min(left, innerWidth - w - edge));
    const top = down ? r.bottom + gap : r.top - h - gap;
    p.style.left = left + 'px'; p.style.top = top + 'px';
    /* The list is on the page body, so these are screen coordinates. Do not measure it again here: its opening animation scales it and would give a wrong offset. */
    return {width: w + 'px', left: p.style.left, top: p.style.top, maxHeight: p.style.maxHeight};
  }
  function openPop(anchor, html, o = {}) {
    closePop();
    closeAlpinePops();
    const p = document.createElement('div'); p.className = 'pop'; p.setAttribute('role', 'menu'); p.innerHTML = html;
    /* Always on the page body: inside a dialog, the dialog's own transform shifts a fixed list away from its field. */
    document.body.appendChild(p); if (anchor.closest('.modal,.drawer')) p.classList.add('pop-top');
    placePop(anchor, p, o); POP = {el: p, anchor};
    return p;
  }

  /* ---------- custom select and date picker ---------- */
  function csSync(s) {
    const b = s.nextElementSibling; if (!b || !b.classList.contains('cs')) return; const v = b.querySelector('.cs-v');
    if (s.tagName === 'SELECT') v.textContent = s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : '';
    else { v.textContent = s.value ? fd(s.value) : tx(s.type === 'date' ? 'Select a date' : 'Select date and time'); v.classList.toggle('none', !s.value); }
    b.disabled = s.disabled; b.classList.toggle('err', s.classList.contains('err'));
  }
  function enhance(root = document) {
    $$('.cs', root).forEach(b => { const s = b.previousElementSibling; if (!s || !s.classList.contains('native-hide')) b.remove(); });
    $$('select,input[type=date],input[type=datetime-local]', root).forEach(s => {
      if (s.closest('[data-native]')) return;
      const nx = s.nextElementSibling;
      if (s.classList.contains('native-hide') && nx && nx.classList.contains('cs')) return csSync(s);
      const sel = s.tagName === 'SELECT', b = document.createElement('button'); b.type = 'button';
      b.className = 'cs ' + s.className.replace('native-hide', '') + (sel ? '' : ' input dp'); b.dataset.cs = sel ? 'sel' : 'dp'; b.setAttribute('aria-haspopup', sel ? 'listbox' : 'dialog');
      if (s.style.width) b.style.width = s.style.width; if (s.style.height) b.style.height = s.style.height;
      const lb = s.id && document.querySelector(`label[for="${s.id}"]`), l = s.getAttribute('aria-label') || (lb ? lb.textContent : '');
      if (l) b.setAttribute('aria-label', l.trim());
      b.innerHTML = sel ? `<span class="cs-v"></span>${ic('down', 15)}` : `${ic('cal', 15)}<span class="cs-v"></span>`;
      s.classList.add('native-hide'); s.tabIndex = -1; s.setAttribute('aria-hidden', 'true'); s.after(b); csSync(s);
    });
    $$('textarea', root).forEach(autoGrow);
    $$('[data-indet]', root).forEach(c => { c.indeterminate = true; });
    $$('.tbl-wrap,.cmp-wrap', root).forEach(w => { if (!w.hasAttribute('tabindex')) { w.tabIndex = 0; w.setAttribute('role', 'region'); if (!w.getAttribute('aria-label')) w.setAttribute('aria-label', 'Table, scrollable'); } edges(w); });
  }
  function edges(w) { w.classList.toggle('can-l', w.scrollLeft > 2); w.classList.toggle('can-r', w.scrollLeft + w.clientWidth < w.scrollWidth - 2); }
  function autoGrow(t) { if (!t.offsetParent) return; t.style.height = 'auto'; t.style.height = Math.max(84, t.scrollHeight + 2) + 'px'; }
  function csOpen(b) {
    if (POP && POP.anchor === b) return closePop(); const s = b.previousElementSibling; CS = s;
    const find = s.options.length > 7 ? `<div class="cs-find"><input type="search" placeholder="${tx('Search')}" aria-label="${tx('Search options')}" autocomplete="off"></div>` : '';
    const p = openPop(b, find + [...s.options].map((o, i) => `<button type="button" class="pop-i ${i === s.selectedIndex ? 'on' : ''}" role="option" aria-selected="${i === s.selectedIndex}" ${o.disabled ? 'disabled' : ''} data-pick="${i}"><span style="flex:1;min-width:0">${esc(o.text)}</span>${i === s.selectedIndex ? `<span class="k">${ic('check', 15)}</span>` : ''}</button>`).join('') + `<div class="cs-none" hidden>${tx('Nothing matches')}</div>`, {w: Math.max(b.offsetWidth, 200), left: true});
    p.setAttribute('role', 'listbox'); p.classList.add('cs-pop');
    const on = p.querySelector('.on') || p.querySelector('button'); if (on) on.scrollIntoView({block: 'nearest'});
    const f = p.querySelector('.cs-find input'); (f || on) && (f || on).focus();
  }
  function csFilter(input) {
    const q = input.value.trim().toLowerCase(); let n = 0;
    $$('[data-pick]', POP.el).forEach(o => { const hit = !q || o.textContent.toLowerCase().includes(q); o.hidden = !hit; if (hit) n++; });
    const none = POP.el.querySelector('.cs-none'); if (none) none.hidden = n > 0;
  }
  function dpHTML() {
    const {el, y, m} = DP, first = new Date(y, m, 1), lead = (first.getDay() + 6) % 7, n = new Date(y, m + 1, 0).getDate(), min = (el.min || '').slice(0, 10), t = iso(new Date()), cur = (el.value || '').slice(0, 10);
    let cells = ''; for (let i = 0; i < lead; i++) cells += '<span></span>';
    for (let d = 1; d <= n; d++) { const v = iso(new Date(y, m, d)); cells += `<button type="button" class="dp-d ${v === cur ? 'on' : ''} ${v === t ? 'today' : ''}" ${min && v < min ? 'disabled' : ''} data-day="${v}" aria-label="${fd(v)}" aria-pressed="${v === cur}">${d}</button>`; }
    const [hh, mm] = DP.time ? DP.time.split(':') : ['', ''];
    const time = DP.time ? `<div class="dp-time"><span class="lbl">${tx('Time')}</span><span class="tm"><button type="button" data-tm="h,-1" aria-label="${tx('Hour down')}">${ic('left', 14)}</button><input inputmode="numeric" maxlength="2" value="${hh}" data-tmi="h" aria-label="${tx('Hour')}"><button type="button" data-tm="h,1" aria-label="${tx('Hour up')}">${ic('right', 14)}</button></span><b>:</b><span class="tm"><button type="button" data-tm="m,-5" aria-label="${tx('Minutes down')}">${ic('left', 14)}</button><input inputmode="numeric" maxlength="2" value="${mm}" data-tmi="m" aria-label="${tx('Minutes')}"><button type="button" data-tm="m,5" aria-label="${tx('Minutes up')}">${ic('right', 14)}</button></span></div><div class="dp-chips">${['09:00', '12:00', '17:00', '18:00', '23:59'].map(t => `<button type="button" class="${t === DP.time ? 'on' : ''}" data-tset="${t}">${t}</button>`).join('')}</div>` : '';
    return `<div class="dp-h"><button type="button" class="icon-btn" data-nav="-1" aria-label="${tx('Previous month')}">${ic('left')}</button><b>${MONTHS[m]} ${y}</b><button type="button" class="icon-btn" data-nav="1" aria-label="${tx('Next month')}">${ic('right')}</button></div><div class="dp-g dp-w">${['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map(d => `<span>${tx(d)}</span>`).join('')}</div><div class="dp-g">${cells}</div>${time}<div class="dp-f"><button type="button" class="btn btn-sm btn-g" data-day="">${tx('Clear')}</button><span class="sp"></span>${[['In a week', 7], ['In 2 weeks', 14], ['In a month', 30]].map(q => `<button type="button" class="btn btn-sm" data-day="${addD(t, q[1])}">${tx(q[0])}</button>`).join('')}${DP.time ? `<button type="button" class="btn btn-sm btn-p" data-done>${tx('Done')}</button>` : ''}</div>`;
  }
  function dpOpen(b) {
    if (POP && POP.anchor === b) return closePop(); const el = b.previousElementSibling, t = iso(new Date()), min = (el.min || '').slice(0, 10);
    const d = new Date(((el.value || '').slice(0, 10) || (min > t ? min : t)) + 'T12:00');
    DP = {el, y: d.getFullYear(), m: d.getMonth(), time: el.type === 'datetime-local' ? ((el.value || '').split('T')[1] || '17:00').slice(0, 5) : null};
    const p = openPop(b, dpHTML(), {w: DP.time ? 372 : 330, left: true}); p.classList.add('dp-pop'); p.setAttribute('role', 'dialog');
    const f = p.querySelector('.dp-d.on,.dp-d.today:not([disabled]),.dp-d:not([disabled])'); if (f) f.focus();
  }
  function tmSet(h, m) {
    h = ((h % 24) + 24) % 24; m = ((m % 60) + 60) % 60; DP.time = pad(h) + ':' + pad(m);
    const a = POP.el.querySelector('[data-tmi=h]'), b = POP.el.querySelector('[data-tmi=m]'); if (a) a.value = pad(h); if (b) b.value = pad(m);
    $$('[data-tset]', POP.el).forEach(c => c.classList.toggle('on', c.dataset.tset === DP.time));
    const d = (DP.el.value || '').slice(0, 10); if (d) dpSet(d, true);
  }
  function dpSet(day, keep) { const el = DP.el; el.value = day ? (DP.time ? `${day}T${DP.time}` : day) : ''; csSync(el); fire(el); if (!keep) { closePop(); if (document.contains(el) && el.nextElementSibling) el.nextElementSibling.focus(); } }

  let searchTimer = null, searchSeq = 0;
  function searchOpen(input) {
    const form = input.closest('[data-search-suggest]'); if (!form) return; const q = input.value.trim(), seq = ++searchSeq;
    clearTimeout(searchTimer); searchTimer = setTimeout(async () => {
      try { const data = await fetch(form.dataset.searchSuggest + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}}).then(r => r.json()); if (seq !== searchSeq || document.activeElement !== input) return;
        const row = (r, i) => `<button type="button" class="pop-i" data-search-row="${i}" data-href="${esc(r.url)}">${ic(r.icon)}<span style="min-width:0"><b style="display:block">${esc(r.title)}</b><span class="muted sm">${esc(r.sub)}</span></span></button>`;
        const pages = data.pages.length ? `<div class="pop-h">${tx('Go to')}</div>${data.pages.map((r, i) => row(r, i)).join('')}` : '';
        const records = data.records.length ? `<div class="pop-h">${tx('Results')}</div>${data.records.map((r, i) => row(r, i + data.pages.length)).join('')}` : '';
        openPop(form, pages + records || `<div class="empty" style="padding:20px"><p>${q ? tx('No results for') + ' "' + esc(q) + '".' : tx('Go to')}</p></div>`, {w: Math.max(300, form.offsetWidth), left: true});
      } catch (_) { /* Search suggestions must never interrupt normal Enter navigation. */ }
    }, q.length < 2 ? 0 : 200);
  }

  document.addEventListener('click', e => {
    const t = e.target; if (!t.closest) return;
    if (POP && POP.el.contains(t)) {
      const pk = t.closest('[data-pick]'), day = t.closest('[data-day]'), nav = t.closest('[data-nav]'), go = t.closest('[data-href]'), tm = t.closest('[data-tm]'), ts = t.closest('[data-tset]');
      if (tm) { const [k, d] = tm.dataset.tm.split(','), [h, m] = DP.time.split(':').map(Number); k === 'h' ? tmSet(h + +d, m) : tmSet(h, Math.round(m / 5) * 5 + +d); }
      else if (ts) { const [h, m] = ts.dataset.tset.split(':').map(Number); tmSet(h, m); }
      else if (pk) { const s = CS, b = s.nextElementSibling; s.selectedIndex = +pk.dataset.pick; csSync(s); closePop(); fire(s); if (b && document.contains(b)) b.focus(); }
      else if (t.closest('[data-done]')) { const an = POP.anchor; closePop(); if (document.contains(an)) an.focus(); }
      else if (day) { const keep = !!DP.time && day.dataset.day !== '' && !!day.closest('.dp-g'); dpSet(day.dataset.day, keep); if (keep && POP) POP.el.innerHTML = dpHTML(); }
      else if (nav) { const x = new Date(DP.y, DP.m + +nav.dataset.nav, 1); DP.y = x.getFullYear(); DP.m = x.getMonth(); POP.el.innerHTML = dpHTML(); const f = POP.el.querySelector('.dp-d.on,.dp-d:not([disabled])'); if (f) f.focus(); }
      else if (go) { closePop(); window.Livewire.navigate(go.dataset.href); }
      return;
    }
    const cs = t.closest('.cs[data-cs]'); if (cs) { e.preventDefault(); return cs.dataset.cs === 'sel' ? csOpen(cs) : dpOpen(cs); }
    const rb = t.closest('[data-recent-btn]'); if (rb) return recentOpen(rb);
    if (POP) closePop();
    const l = t.closest('label[for]'); if (l) { const x = document.getElementById(l.htmlFor); if (x && x.classList.contains('native-hide') && x.nextElementSibling) { e.preventDefault(); x.nextElementSibling.focus(); } }
    const row = t.closest('[data-href]'); if (row && !t.closest('a,button,input,label,select,textarea,.cs')) window.Livewire.navigate(row.dataset.href);
  });
  document.addEventListener('change', e => { const t = e.target; if (t.classList && t.classList.contains('native-hide')) csSync(t); if (t.dataset && t.dataset.tmi && DP && POP) { const h = parseInt(POP.el.querySelector('[data-tmi=h]').value, 10) || 0, m = parseInt(POP.el.querySelector('[data-tmi=m]').value, 10) || 0; tmSet(Math.min(23, Math.max(0, h)), Math.min(59, Math.max(0, m))); } });
  document.addEventListener('input', e => { if (e.target.id === 'gsearch') searchOpen(e.target); if (e.target.tagName === 'TEXTAREA') autoGrow(e.target); if (POP && e.target.closest && e.target.closest('.cs-find')) csFilter(e.target); if (e.target.dataset && e.target.dataset.tmi) e.target.value = e.target.value.replace(/\D/g, ''); });
  document.addEventListener('focusin', e => { if (e.target.id === 'gsearch') searchOpen(e.target); });
  document.addEventListener('scroll', e => { const w = e.target; if (w.classList && (w.classList.contains('tbl-wrap') || w.classList.contains('cmp-wrap'))) edges(w); else if (POP && !(w.nodeType === 1 && POP.el.contains(w))) closePop(); tipHide(); }, true);
  window.addEventListener('resize', () => { closePop(); $$('.tbl-wrap,.cmp-wrap').forEach(edges); });
  document.addEventListener('keydown', e => {
    const a = document.activeElement, typing = a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName);
    if (POP && e.key === 'Escape') { e.stopPropagation(); const an = POP.anchor; closePop(); if (document.contains(an)) an.focus(); return; }
    if (POP && POP.anchor?.id === 'gsearch' && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { const rows = $$('[data-search-row]', POP.el); if (!rows.length) return; e.preventDefault(); const i = rows.indexOf(document.activeElement); rows[Math.max(0, Math.min(rows.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)))].focus(); return; }
    if (POP && e.key === 'Enter' && a.closest && a.closest('.cs-find')) { e.preventDefault(); const first = POP.el.querySelector('[data-pick]:not([hidden]):not([disabled])'); if (first) first.click(); return; }
    if (POP && a.dataset && a.dataset.tmi) { if (e.key === 'ArrowUp' || e.key === 'ArrowDown') { e.preventDefault(); const [h, m] = DP.time.split(':').map(Number), d = e.key === 'ArrowUp' ? 1 : -1; a.dataset.tmi === 'h' ? tmSet(h + d, m) : tmSet(h, m + d); } return; }
    if (POP && /^Arrow/.test(e.key)) {
      const cal = POP.el.classList.contains('dp-pop') && a.classList.contains('dp-d');
      const L = $$(cal ? '.dp-d:not([disabled])' : 'button:not([disabled]):not([hidden])', POP.el), i = L.indexOf(a); if (!L.length) return;
      const step = cal ? ({ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7})[e.key] : ({ArrowUp: -1, ArrowDown: 1})[e.key]; if (!step) return;
      e.preventDefault(); L[Math.max(0, Math.min(L.length - 1, i < 0 ? 0 : i + step))].focus(); return;
    }
    if (a && a.classList.contains('cs') && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); a.click(); return; }
    if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey) { const s = $('.dt-search input'); if (s) { e.preventDefault(); s.focus(); s.select(); } }
    if (e.key === 'Enter' && a && a.dataset && a.dataset.href && a === e.target) window.Livewire.navigate(a.dataset.href);
  }, true);

  /* ---------- tooltips replace the browser's own ---------- */
  let TIP = null;
  function tipHide() { if (TIP) { TIP.remove(); TIP = null; } }
  function tipShow(el) {
    tipHide(); const txt = el.dataset.tip; if (!txt || el.classList.contains('brand') || (el.classList.contains('sb-item') && !$('.app.collapsed'))) return;
    TIP = document.createElement('div'); TIP.className = 'tip'; TIP.setAttribute('role', 'tooltip'); TIP.textContent = txt; document.body.appendChild(TIP);
    const r = el.getBoundingClientRect(), w = TIP.offsetWidth, h = TIP.offsetHeight, side = !!el.closest('.sb');
    let left = side ? r.right + 10 : r.left + r.width / 2 - w / 2, top = side ? r.top + r.height / 2 - h / 2 : r.bottom + 8;
    if (top + h > innerHeight - 6) top = r.top - h - 8;
    TIP.style.left = Math.max(6, Math.min(left, innerWidth - w - 6)) + 'px'; TIP.style.top = top + 'px'; TIP._for = el;
  }
  const tipOf = t => { if (t.hasAttribute('title')) { if (t.title) t.dataset.tip = t.title; t.removeAttribute('title'); } return t; };
  document.addEventListener('mouseover', e => { const t = e.target.closest && e.target.closest('[title],[data-tip]'); if (!t) return tipHide(); tipOf(t); if (!TIP || TIP._for !== t) tipShow(t); });
  document.addEventListener('focusin', e => { const t = e.target.closest && e.target.closest('[title],[data-tip]'); if (t && e.target.matches(':focus-visible')) tipShow(tipOf(t)); });
  ['mousedown', 'focusout'].forEach(ev => document.addEventListener(ev, tipHide, true));

  /* ---------- toasts ---------- */
  function toast(text, kind = 'check') {
    const box = $('#toasts'); if (!box || !text) return;
    const t = document.createElement('div'); t.className = 'toast' + (kind === 'alert' ? ' bad' : ''); t.setAttribute('role', 'status');
    t.innerHTML = `${ic(kind === 'alert' ? 'alert' : 'check')}<div>${esc(text)}</div>`;
    box.appendChild(t); setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 220); }, 4400);
  }
  window.addEventListener('toast', e => { const d = Array.isArray(e.detail) ? e.detail[0] : e.detail; if (d) toast(d.text, d.kind); });

  /* ---------- recently opened records, kept in this browser ---------- */
  const RK = '48source-recent';
  const recent = () => { try { return JSON.parse(localStorage.getItem(RK) || '[]'); } catch (_) { return []; } };
  function recentTrack() {
    const h = $('[data-recent]'); let L = recent();
    if (h) { const it = {t: h.dataset.recent, s: h.dataset.recentSub || '', u: location.pathname}; L = [it, ...L.filter(x => x.u !== it.u)].slice(0, 8); try { localStorage.setItem(RK, JSON.stringify(L)); } catch (_) {} }
    $$('[data-recent-btn]').forEach(b => { b.hidden = !L.length; });
  }
  function recentOpen(b) {
    if (POP && POP.anchor === b) return closePop();
    openPop(b, `<div class="pop-h">${tx('Recently opened')}</div>` + recent().map(x => `<button type="button" class="pop-i" data-href="${esc(x.u)}"><span style="flex:1;min-width:0"><span class="trunc" style="display:block">${esc(x.t)}</span><span class="faint xs">${esc(x.s)}</span></span></button>`).join(''), {w: 300});
  }

  /* ---------- numbers in the summary strip count up once per page ---------- */
  function countUp() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    $$('.strip-v').forEach(el => { if (el.dataset.done || !/^\d{1,6}$/.test(el.textContent.trim())) return; el.dataset.done = 1; const to = +el.textContent, t0 = performance.now();
      const step = now => { const k = Math.min(1, (now - t0) / 520); el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); }; if (to > 1) requestAnimationFrame(step); });
  }

  /* Rows and list entries with one main link behave like the prototype: the whole row is clickable and gets the hover. */
  function rows() {
    $$('.tbl tbody tr:not([data-href]):not(.sec):not([data-plain])').forEach(tr => { const a = tr.querySelector('td a[href]:not([target]):not([download])'); if (!a || tr.querySelectorAll('td a[href]').length > 3) return; tr.dataset.href = a.getAttribute('href'); tr.classList.add('click'); });
    $$('.list li:not([data-href]):not([data-plain])').forEach(li => { const a = li.querySelectorAll('a[href]'); if (a.length !== 1 || a[0].target || li.querySelector('button,input,select,textarea')) return; li.dataset.href = a[0].getAttribute('href'); li.classList.add('click'); });
  }
  /* Small transitions from the prototype: the table body fades on a filter or page change, tab content rises. */
  let TABFX = false;
  function fx(root) { (root || document).querySelectorAll('.tbl').forEach(t => { t.classList.remove('fx'); void t.offsetWidth; t.classList.add('fx'); }); }
  document.addEventListener('click', e => { if (e.target.closest && e.target.closest('.tabs a,.tabs button,.seg a,.seg button')) TABFX = true; }, true);
  function tabfx() { const pg = $('.page'); if (pg && TABFX) { pg.classList.remove('enter', 'tabfx'); void pg.offsetWidth; pg.classList.add('tabfx'); } TABFX = false; }
  function boot() { rows(); tabfx(); closePop(); closeAlpinePops(); tipHide(); enhance(); recentTrack(); countUp(); const f = $('#toasts'); if (f && f.dataset.flash) { toast(f.dataset.flash); delete f.dataset.flash; } }
  document.addEventListener('livewire:navigated', boot);
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('livewire:init', () => {
    let keep = null;
    window.Livewire.hook('morph', () => { closePop(); closeAlpinePops(); const a = document.activeElement; keep = a && a.classList.contains('cs') && a.previousElementSibling ? a.previousElementSibling.id : null; });
    window.Livewire.hook('morphed', () => { rows(); tabfx(); enhance(); if (keep) { const s = document.getElementById(keep); if (s && s.nextElementSibling) s.nextElementSibling.focus(); keep = null; } });
    window.Livewire.hook('commit', ({component, commit, succeed}) => { const list = commit && ((commit.updates && Object.keys(commit.updates).some(k => /^(search|q|status|page|sort|dir|per|f$|f\.)/.test(k))) || (commit.calls || []).some(c => /^(setStatus|toggleFacet|sortBy|gotoPage|nextPage|previousPage|clearFilters|setPage)/.test(c.method))); succeed(() => queueMicrotask(() => { enhance(); rows(); if (list && component && component.el) fx(component.el); })); });
  });
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('pop', (o = {}) => ({
      open: false, pos: {}, trigger: null, _listening: false,
      init() { alpinePops.add(this); },
      destroy() { this.close(); alpinePops.delete(this); },
      toggle(btn) {
        if (this.open) return this.close(true);
        closePop(); closeAlpinePops(this); this.trigger = btn; this.open = true; this.listen();
        if (!o.inline) this.$nextTick(() => this.place(btn));
      },
      close(returnFocus = false) {
        const trigger = this.trigger; this.open = false; this.unlisten();
        if (returnFocus && trigger && document.contains(trigger)) trigger.focus();
      },
      listen() {
        if (this._listening) return; this._listening = true;
        this._outside = e => { const panel = this.$refs.p; if (this.open && !panel?.contains(e.target) && !this.trigger?.contains(e.target)) this.close(); };
        this._escape = e => { if (this.open && e.key === 'Escape') { e.stopPropagation(); this.close(true); } };
        this._dismiss = e => { if (!e || !this.$refs.p?.contains(e.target)) this.close(); };
        document.addEventListener('pointerdown', this._outside, true);
        document.addEventListener('keydown', this._escape, true);
        document.addEventListener('scroll', this._dismiss, true);
        window.addEventListener('resize', this._dismiss);
      },
      unlisten() {
        if (!this._listening) return; this._listening = false;
        document.removeEventListener('pointerdown', this._outside, true);
        document.removeEventListener('keydown', this._escape, true);
        document.removeEventListener('scroll', this._dismiss, true);
        window.removeEventListener('resize', this._dismiss);
      },
      place(btn) { this.pos = placePop(btn, this.$refs.p, o); },
    }));
    window.Alpine.data('ov', () => ({ bye() { const s = this.$root; if (s.classList.contains('closing')) return; s.classList.add('closing'); setTimeout(() => this.$wire.close(), 160); } }));
  });
  /* ---------- guided tour: walks through the pages the signed-in role really has ---------- */
  let TOUR = null;
  const tourKey = e => { if (!TOUR) return; if (e.key === 'Escape') { e.stopPropagation(); tourEnd(); } else if (e.key === 'ArrowRight' && TOUR.i < TOUR.steps.length - 1) tourGo(1); else if (e.key === 'ArrowLeft' && TOUR.i) tourGo(-1); };
  function tourEnd() { if (!TOUR) return; if (TOUR.box) TOUR.box.remove(); document.removeEventListener('keydown', tourKey, true); TOUR = null; }
  function tourGo(d) { TOUR.i += d; const st = TOUR.steps[TOUR.i]; if (st.path !== location.pathname) { TOUR.box.classList.add('moving'); window.Livewire.navigate(st.href); } else tourDraw(); }
  function tourDraw() {
    if (!TOUR) return; const st = TOUR.steps[TOUR.i], t = $('#tour-t').dataset;
    if (!TOUR.box || !document.body.contains(TOUR.box)) { TOUR.box = document.createElement('div'); TOUR.box.id = 'tour'; TOUR.box.innerHTML = `<div class="tour-hole"></div><div class="tour-card" role="dialog" aria-modal="true" aria-label="${tx('Guided tour')}"></div>`; document.body.appendChild(TOUR.box);
      TOUR.box.addEventListener('click', e => { const b = e.target.closest('[data-t]'); if (!b) return; b.dataset.t === 'x' ? tourEnd() : tourGo(+b.dataset.t); }); }
    TOUR.box.classList.remove('moving');
    const app = $('.app'); if (app && innerWidth <= 900) app.classList.add('navopen');
    const el = $$('.sb-item').find(e => e.getAttribute('href') === st.href); if (!el) return tourEnd();
    el.scrollIntoView({block: 'nearest'});
    const r = el.getBoundingClientRect(), hole = TOUR.box.querySelector('.tour-hole'), card = TOUR.box.querySelector('.tour-card'), last = TOUR.i === TOUR.steps.length - 1;
    Object.assign(hole.style, {left: r.left - 4 + 'px', top: r.top - 4 + 'px', width: r.width + 8 + 'px', height: r.height + 8 + 'px'});
    card.innerHTML = `<div class="muted sm">${esc(t.step.replace(':n', TOUR.i + 1).replace(':total', TOUR.steps.length))}</div><h3>${esc(st.title)}</h3><p>${esc(st.text)}</p><p class="muted sm" style="margin-top:6px">${esc(t.behind)}</p><div class="row" style="margin-top:14px"><button type="button" class="btn btn-sm btn-g" data-t="x">${esc(t.skip)}</button><span class="sp"></span>${TOUR.i ? `<button type="button" class="btn btn-sm" data-t="-1">${esc(t.back)}</button>` : ''}<button type="button" class="btn btn-sm btn-p" data-t="${last ? 'x' : '1'}">${esc(last ? t.finish : t.next)}</button></div>`;
    const h = card.offsetHeight, wide = innerWidth > 900;
    card.style.left = Math.max(12, Math.min(wide ? r.right + 18 : r.left, innerWidth - 332)) + 'px'; card.style.top = Math.max(12, Math.min(wide ? r.top - 8 : r.bottom + 14, innerHeight - h - 12)) + 'px';
    const b = card.querySelector('.btn-p'); if (b) b.focus();
  }
  function tour() {
    tourEnd();
    const steps = $$('.sb-item[data-tour]').filter(e => e.dataset.tour).map(e => ({href: e.getAttribute('href'), path: new URL(e.href).pathname, title: (e.dataset.tip || e.getAttribute('title') || e.querySelector('span').textContent).trim(), text: e.dataset.tour}));
    if (!steps.length) return;
    TOUR = {steps, i: 0, box: null}; document.addEventListener('keydown', tourKey, true);
    if (steps[0].path !== location.pathname) window.Livewire.navigate(steps[0].href); else tourDraw();
  }
  document.addEventListener('livewire:navigated', () => { if (TOUR) setTimeout(tourDraw, 60); });
  function shortcuts() {
    const t = $('#keys-t'); if (!t || $('#keys-ov')) return;
    const ov = document.createElement('div'); ov.className = 'scrim'; ov.id = 'keys-ov'; ov.innerHTML = t.innerHTML; document.body.appendChild(ov);
    const close = () => { ov.remove(); document.removeEventListener('keydown', esc2, true); }, esc2 = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('[data-x]')) close(); }); document.addEventListener('keydown', esc2, true); ov.querySelector('button').focus();
  }
  document.addEventListener('keydown', e => { const a = document.activeElement; if (e.key === '?' && !(a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) && !e.ctrlKey && !e.metaKey) shortcuts(); });
  window.ui48 = {enhance, toast, tour, shortcuts};
})();
