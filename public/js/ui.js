/* 48Source interface behaviour: custom select and date controls, popovers, tooltips, toasts, table helpers.
   Plain script, no build step. Loaded once and kept across wire:navigate page changes. */
(() => {
  'use strict';
  if (window.__ui48) return; window.__ui48 = true;
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c]);
  const ic = (n, s = 16) => { const t = document.getElementById('ic-' + n); return t ? t.innerHTML.replace(/width="\d+" height="\d+"/, `width="${s}" height="${s}"`) : ''; };
  const MN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const pad = n => String(n).padStart(2, '0');
  const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const addD = (v, n) => { const d = new Date(v + 'T12:00'); d.setDate(d.getDate() + n); return iso(d); };
  const fd = v => { if (!v) return ''; const [d, t] = v.split('T'), [y, m, dd] = d.split('-').map(Number); return `${dd} ${MN[m - 1]} ${y}` + (t ? `, ${t.slice(0, 5)}` : ''); };
  const fire = el => { el.dispatchEvent(new Event('input', {bubbles: true})); el.dispatchEvent(new Event('change', {bubbles: true})); };
  const isDate = el => el.tagName === 'INPUT' && (el.type === 'date' || el.type === 'datetime-local');

  /* ---------- popover ---------- */
  let POP = null, CS = null, DP = null;
  function closePop() { if (POP) { POP.el.remove(); POP = null; } }
  function openPop(anchor, html, o = {}) {
    closePop();
    const p = document.createElement('div'); p.className = 'pop'; p.setAttribute('role', 'menu');
    const w = Math.min(o.w || 220, innerWidth - 16); p.style.width = w + 'px'; p.innerHTML = html;
    (anchor.closest('.modal,.drawer') || document.body).appendChild(p);
    const r = anchor.getBoundingClientRect(), h = p.offsetHeight;
    let left = o.left ? r.left : r.right - w; left = Math.max(8, Math.min(left, innerWidth - w - 8));
    let top = r.bottom + 6; if (top + h > innerHeight - 8) top = Math.max(8, r.top - h - 6);
    p.style.left = left + 'px'; p.style.top = top + 'px'; POP = {el: p, anchor};
    /* a dialog that is animating or transformed becomes the containing block: correct for it */
    const q = p.getBoundingClientRect(); if (Math.abs(q.left - left) > 1 || Math.abs(q.top - top) > 1) { p.style.left = (left - (q.left - left)) + 'px'; p.style.top = (top - (q.top - top)) + 'px'; }
    return p;
  }

  /* ---------- custom select and date picker ---------- */
  function csSync(s) {
    const b = s.nextElementSibling; if (!b || !b.classList.contains('cs')) return; const v = b.querySelector('.cs-v');
    if (s.tagName === 'SELECT') v.textContent = s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : '';
    else { v.textContent = s.value ? fd(s.value) : (s.type === 'date' ? 'Select a date' : 'Select date and time'); v.classList.toggle('none', !s.value); }
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
    const p = openPop(b, [...s.options].map((o, i) => `<button type="button" class="pop-i ${i === s.selectedIndex ? 'on' : ''}" role="option" aria-selected="${i === s.selectedIndex}" ${o.disabled ? 'disabled' : ''} data-pick="${i}"><span style="flex:1;min-width:0">${esc(o.text)}</span>${i === s.selectedIndex ? `<span class="k">${ic('check', 15)}</span>` : ''}</button>`).join(''), {w: Math.max(b.offsetWidth, 200), left: true});
    p.setAttribute('role', 'listbox'); p.classList.add('cs-pop'); const on = p.querySelector('.on') || p.querySelector('button'); if (on) { on.focus(); on.scrollIntoView({block: 'nearest'}); }
  }
  function dpHTML() {
    const {el, y, m} = DP, first = new Date(y, m, 1), lead = (first.getDay() + 6) % 7, n = new Date(y, m + 1, 0).getDate(), min = (el.min || '').slice(0, 10), t = iso(new Date()), cur = (el.value || '').slice(0, 10);
    let cells = ''; for (let i = 0; i < lead; i++) cells += '<span></span>';
    for (let d = 1; d <= n; d++) { const v = iso(new Date(y, m, d)); cells += `<button type="button" class="dp-d ${v === cur ? 'on' : ''} ${v === t ? 'today' : ''}" ${min && v < min ? 'disabled' : ''} data-day="${v}" aria-label="${fd(v)}" aria-pressed="${v === cur}">${d}</button>`; }
    const time = DP.time ? `<div class="dp-f" style="border-top:1px solid var(--line)"><label class="lbl" for="dp-time" style="margin:0">Time</label><span class="sp"></span><input id="dp-time" type="time" class="input mono" style="width:120px;height:32px" value="${DP.time}"></div>` : '';
    return `<div class="dp-h"><button type="button" class="icon-btn" data-nav="-1" aria-label="Previous month">${ic('left')}</button><b>${MONTHS[m]} ${y}</b><button type="button" class="icon-btn" data-nav="1" aria-label="Next month">${ic('right')}</button></div><div class="dp-g dp-w">${['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map(d => `<span>${d}</span>`).join('')}</div><div class="dp-g">${cells}</div>${time}<div class="dp-f"><button type="button" class="btn btn-sm btn-g" data-day="">Clear</button><span class="sp"></span>${[['In a week', 7], ['In 2 weeks', 14], ['In a month', 30]].map(q => `<button type="button" class="btn btn-sm" data-day="${addD(t, q[1])}">${q[0]}</button>`).join('')}</div>`;
  }
  function dpOpen(b) {
    if (POP && POP.anchor === b) return closePop(); const el = b.previousElementSibling, t = iso(new Date()), min = (el.min || '').slice(0, 10);
    const d = new Date(((el.value || '').slice(0, 10) || (min > t ? min : t)) + 'T12:00');
    DP = {el, y: d.getFullYear(), m: d.getMonth(), time: el.type === 'datetime-local' ? ((el.value || '').split('T')[1] || '17:00').slice(0, 5) : null};
    const p = openPop(b, dpHTML(), {w: 330, left: true}); p.classList.add('dp-pop'); p.setAttribute('role', 'dialog');
    const f = p.querySelector('.dp-d.on,.dp-d.today:not([disabled]),.dp-d:not([disabled])'); if (f) f.focus();
  }
  function dpSet(day, keep) { const el = DP.el; el.value = day ? (DP.time ? `${day}T${DP.time}` : day) : ''; csSync(el); fire(el); if (!keep) { closePop(); if (document.contains(el) && el.nextElementSibling) el.nextElementSibling.focus(); } }

  document.addEventListener('click', e => {
    const t = e.target; if (!t.closest) return;
    if (POP && POP.el.contains(t)) {
      const pk = t.closest('[data-pick]'), day = t.closest('[data-day]'), nav = t.closest('[data-nav]'), go = t.closest('[data-href]');
      if (pk) { const s = CS, b = s.nextElementSibling; s.selectedIndex = +pk.dataset.pick; csSync(s); closePop(); fire(s); if (b && document.contains(b)) b.focus(); }
      else if (day) dpSet(day.dataset.day);
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
  document.addEventListener('change', e => { const t = e.target; if (t.classList && t.classList.contains('native-hide')) csSync(t); if (t.id === 'dp-time' && DP) { DP.time = t.value || '17:00'; const d = (DP.el.value || '').slice(0, 10); if (d) dpSet(d, true); } });
  document.addEventListener('input', e => { if (e.target.tagName === 'TEXTAREA') autoGrow(e.target); });
  document.addEventListener('scroll', e => { const w = e.target; if (w.classList && (w.classList.contains('tbl-wrap') || w.classList.contains('cmp-wrap'))) edges(w); else if (POP && !(w.nodeType === 1 && POP.el.contains(w))) closePop(); tipHide(); }, true);
  window.addEventListener('resize', () => { closePop(); $$('.tbl-wrap,.cmp-wrap').forEach(edges); });
  document.addEventListener('keydown', e => {
    const a = document.activeElement, typing = a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName);
    if (POP && e.key === 'Escape') { e.stopPropagation(); const an = POP.anchor; closePop(); if (document.contains(an)) an.focus(); return; }
    if (POP && /^Arrow/.test(e.key) && a.id !== 'dp-time') {
      const cal = POP.el.classList.contains('dp-pop') && a.classList.contains('dp-d');
      const L = $$(cal ? '.dp-d:not([disabled])' : 'button:not([disabled])', POP.el), i = L.indexOf(a); if (!L.length) return;
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
  function reloadToast(text) {
    const box = $('#toasts'); if (!box) return;
    const t = document.createElement('div'); t.className = 'toast bad'; t.setAttribute('role', 'alert');
    t.innerHTML = `${ic('alert')}<div>${esc(text)}</div><button type="button" class="link sm">Reload</button>`;
    t.querySelector('button').addEventListener('click', () => location.reload()); box.appendChild(t);
  }
  window.addEventListener('toast', e => { const d = Array.isArray(e.detail) ? e.detail[0] : e.detail; if (d) toast(d.text, d.kind); });

  /* ---------- Livewire request feedback ---------- */
  const busyButtons = new Map();
  const uploading = new Set();
  function spinner() { const s = document.createElement('span'); s.className = 'lw-spinner'; s.setAttribute('aria-hidden', 'true'); return s; }
  function startButton(button) {
    if (!button || button.disabled || button.dataset.lwBusy) return;
    button.dataset.lwBusy = '1'; button.disabled = true; button.setAttribute('aria-busy', 'true');
    const timer = setTimeout(() => { if (!button.isConnected || !button.dataset.lwBusy) return; button.classList.add('lw-busy'); if (!button.querySelector('.lw-spinner')) button.prepend(spinner()); }, 150);
    busyButtons.set(button, timer);
  }
  function finishButton() {
    busyButtons.forEach((timer, button) => {
      clearTimeout(timer);
      if (button.isConnected) {
        button.classList.remove('lw-busy'); button.querySelector('.lw-spinner')?.remove(); delete button.dataset.lwBusy;
        button.removeAttribute('aria-busy'); button.disabled = false;
      }
    });
    busyButtons.clear();
  }
  function formButton(form) { return form.querySelector('button[type="submit"]:not([disabled]),input[type="submit"]:not([disabled])'); }
  document.addEventListener('click', e => {
    const button = e.target.closest && e.target.closest('button,[role="button"]');
    if (button && button.hasAttribute('wire:click')) startButton(button);
  }, true);
  document.addEventListener('submit', e => {
    const form = e.target;
    if (form.attributes && [...form.attributes].some(a => a.name === 'wire:submit' || a.name.startsWith('wire:submit.'))) startButton(e.submitter || formButton(form));
  }, true);
  function uploadBar(input) {
    let bar = input.parentElement?.querySelector(':scope > .lw-upload-progress');
    if (!bar) { bar = document.createElement('div'); bar.className = 'lw-upload-progress'; bar.setAttribute('role', 'progressbar'); bar.setAttribute('aria-label', 'Upload progress'); bar.innerHTML = '<span></span>'; input.insertAdjacentElement('afterend', bar); }
    return bar;
  }
  function uploadState(input, percent, phase = 'progress') {
    const bar = uploadBar(input); bar.hidden = false; bar.setAttribute('aria-valuenow', String(percent)); bar.querySelector('span').style.width = `${percent}%`;
    const form = input.closest('form');
    if (phase === 'start') uploading.add(input);
    if (phase === 'done') { uploading.delete(input); setTimeout(() => { if (bar) bar.hidden = true; }, 500); }
    if (form) form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(b => { b.disabled = [...uploading].some(i => i.closest('form') === form); });
  }
  document.addEventListener('livewire-upload-start', e => uploadState(e.target, 0, 'start'));
  document.addEventListener('livewire-upload-progress', e => uploadState(e.target, e.detail.progress || 0));
  document.addEventListener('livewire-upload-finish', e => uploadState(e.target, 100, 'done'));
  document.addEventListener('livewire-upload-error', e => { uploadState(e.target, 0, 'done'); toast('The upload failed. Please try again.', 'alert'); });
  document.addEventListener('livewire:navigate', () => { const p = $('#lw-progress'); if (p) { p.className = 'on'; p.setAttribute('aria-hidden', 'false'); } });
  document.addEventListener('livewire:navigated', () => { const p = $('#lw-progress'); if (!p) return; p.className = 'done'; setTimeout(() => { p.className = ''; p.setAttribute('aria-hidden', 'true'); }, 180); });
  function networkState() { const b = $('#offline-banner'); if (b) b.hidden = navigator.onLine; }
  window.addEventListener('offline', networkState); window.addEventListener('online', networkState); document.addEventListener('DOMContentLoaded', networkState);

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
    openPop(b, `<div class="pop-h">Recently opened</div>` + recent().map(x => `<button type="button" class="pop-i" data-href="${esc(x.u)}"><span style="flex:1;min-width:0"><span class="trunc" style="display:block">${esc(x.t)}</span><span class="faint xs">${esc(x.s)}</span></span></button>`).join(''), {w: 300});
  }

  /* ---------- numbers in the summary strip count up once per page ---------- */
  function countUp() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    $$('.strip-v').forEach(el => { if (el.dataset.done || !/^\d{1,6}$/.test(el.textContent.trim())) return; el.dataset.done = 1; const to = +el.textContent, t0 = performance.now();
      const step = now => { const k = Math.min(1, (now - t0) / 520); el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); }; if (to > 1) requestAnimationFrame(step); });
  }

  function boot() { closePop(); tipHide(); enhance(); recentTrack(); countUp(); const f = $('#toasts'); if (f && f.dataset.flash) { toast(f.dataset.flash); delete f.dataset.flash; } }
  document.addEventListener('livewire:navigated', boot);
  document.addEventListener('DOMContentLoaded', boot);
  document.addEventListener('livewire:init', () => {
    let keep = null;
    window.Livewire.hook('morph', () => { const a = document.activeElement; keep = a && a.classList.contains('cs') && a.previousElementSibling ? a.previousElementSibling.id : null; });
    window.Livewire.hook('morphed', () => { enhance(); if (keep) { const s = document.getElementById(keep); if (s && s.nextElementSibling) s.nextElementSibling.focus(); keep = null; } });
    window.Livewire.hook('commit', ({succeed, fail}) => {
      succeed(() => { finishButton(); queueMicrotask(() => enhance()); });
      fail(({status}) => { finishButton(); if (status === 419) reloadToast('Your session expired. Reload to continue.'); else toast('The request could not be completed. Please try again.', 'alert'); });
    });
  });
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('pop', (o = {}) => ({
      open: false, pos: {},
      toggle(btn) { this.open = !this.open; if (this.open) this.$nextTick(() => this.place(btn)); },
      place(btn) { const p = this.$refs.p, w = Math.min(o.w || 220, innerWidth - 16), r = btn.getBoundingClientRect(); p.style.width = w + 'px'; const h = p.offsetHeight;
        let left = o.left ? r.left : r.right - w; left = Math.max(8, Math.min(left, innerWidth - w - 8)); let top = r.bottom + 6; if (top + h > innerHeight - 8) top = Math.max(8, r.top - h - 6);
        this.pos = {width: w + 'px', left: left + 'px', top: top + 'px'}; },
    }));
    window.Alpine.data('ov', () => ({ bye() { const s = this.$root; if (s.classList.contains('closing')) return; s.classList.add('closing'); setTimeout(() => this.$wire.close(), 160); } }));
  });
  /* ---------- guided tour over the menu the signed-in role really has ---------- */
  let TOUR = null;
  function tourEnd() { if (!TOUR) return; TOUR.box.remove(); document.removeEventListener('keydown', TOUR.key, true); TOUR = null; }
  function tourDraw() {
    const st = TOUR.steps[TOUR.i], t = $('#tour-t').dataset; st.el.scrollIntoView({block: 'nearest'});
    const r = st.el.getBoundingClientRect(), hole = TOUR.box.querySelector('.tour-hole'), card = TOUR.box.querySelector('.tour-card'), last = TOUR.i === TOUR.steps.length - 1;
    Object.assign(hole.style, {left: r.left - 4 + 'px', top: r.top - 4 + 'px', width: r.width + 8 + 'px', height: r.height + 8 + 'px'});
    card.innerHTML = `<div class="muted sm">${esc(t.step.replace(':n', TOUR.i + 1).replace(':total', TOUR.steps.length))}</div><h3>${esc(st.title)}</h3><p>${esc(st.text)}</p><div class="row" style="margin-top:14px"><button type="button" class="btn btn-sm btn-g" data-t="x">${esc(t.skip)}</button><span class="sp"></span>${TOUR.i ? `<button type="button" class="btn btn-sm" data-t="-1">${esc(t.back)}</button>` : ''}<button type="button" class="btn btn-sm btn-p" data-t="${last ? 'x' : '1'}">${esc(last ? t.finish : t.next)}</button></div>`;
    const h = card.offsetHeight, wide = innerWidth > 900;
    card.style.left = Math.max(12, Math.min(wide ? r.right + 18 : r.left, innerWidth - 332)) + 'px'; card.style.top = Math.max(12, Math.min(wide ? r.top - 8 : r.bottom + 14, innerHeight - h - 12)) + 'px';
    const b = card.querySelector('.btn-p'); if (b) b.focus();
  }
  function tour() {
    tourEnd(); const app = $('.app'); if (app && innerWidth <= 900) app.classList.add('navopen');
    const steps = $$('.sb-item[data-tour]').filter(e => e.dataset.tour && e.offsetParent).map(e => ({el: e, title: (e.dataset.tip || e.getAttribute('title') || e.textContent).trim(), text: e.dataset.tour}));
    if (!steps.length) return;
    const box = document.createElement('div'); box.id = 'tour'; box.innerHTML = '<div class="tour-hole"></div><div class="tour-card" role="dialog" aria-modal="true" aria-label="Guided tour"></div>'; document.body.appendChild(box);
    const key = e => { if (e.key === 'Escape') { e.stopPropagation(); tourEnd(); } else if (e.key === 'ArrowRight' && TOUR.i < TOUR.steps.length - 1) { TOUR.i++; tourDraw(); } else if (e.key === 'ArrowLeft' && TOUR.i) { TOUR.i--; tourDraw(); } };
    TOUR = {steps, i: 0, box, key}; document.addEventListener('keydown', key, true);
    box.addEventListener('click', e => { const b = e.target.closest('[data-t]'); if (!b) return; if (b.dataset.t === 'x') return tourEnd(); TOUR.i += +b.dataset.t; tourDraw(); });
    tourDraw();
  }
  document.addEventListener('livewire:navigate', tourEnd);
  window.ui48 = {enhance, toast, tour};
})();
