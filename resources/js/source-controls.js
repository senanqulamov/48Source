/* Shared controls for server-rendered forms. The native element remains the
 * form value and is deliberately not replaced, which keeps Livewire updates,
 * browser validation and non-JavaScript submission intact. */
(() => {
    const selector = 'select.select:not([multiple]):not([data-source-native])';
    let active = null;

    function close() {
        if (!active) return;
        active.menu.remove();
        active.trigger.setAttribute('aria-expanded', 'false');
        active = null;
    }

    function position(menu, trigger) {
        const box = trigger.getBoundingClientRect();
        const width = Math.max(Number(menu.dataset.sourceWidth || 200), Math.ceil(box.width));
        menu.style.width = `${width}px`;
        menu.style.left = `${Math.min(Math.max(8, box.left), window.innerWidth - width - 8)}px`;
        const below = window.innerHeight - box.bottom;
        menu.style.top = `${below >= Math.min(menu.offsetHeight, 300) + 8 ? box.bottom + 6 : Math.max(8, box.top - menu.offsetHeight - 6)}px`;
    }

    function optionLabel(select) {
        return select.options[select.selectedIndex]?.text || select.getAttribute('placeholder') || 'Select an option';
    }

    function sync(control) {
        control.text.textContent = optionLabel(control.select);
        control.trigger.disabled = control.select.disabled;
    }

    function choose(control, index) {
        const option = control.select.options[index];
        if (!option || option.disabled) return;
        control.select.selectedIndex = index;
        control.select.dispatchEvent(new Event('input', { bubbles: true }));
        control.select.dispatchEvent(new Event('change', { bubbles: true }));
        sync(control);
        close();
        control.trigger.focus();
    }

    function open(control) {
        close();
        const menu = document.createElement('div');
        menu.className = 'source-select-menu';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', control.select.getAttribute('aria-label') || control.select.labels?.[0]?.textContent?.trim() || 'Options');
        [...control.select.options].forEach((option, index) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'source-select-option';
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', String(index === control.select.selectedIndex));
            row.disabled = option.disabled;
            row.tabIndex = index === control.select.selectedIndex ? 0 : -1;
            row.innerHTML = `<span></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>`;
            row.firstElementChild.textContent = option.text;
            row.addEventListener('click', () => choose(control, index));
            menu.append(row);
        });
        document.body.append(menu);
        position(menu, control.trigger);
        control.trigger.setAttribute('aria-expanded', 'true');
        active = { ...control, menu };
        menu.addEventListener('keydown', (event) => {
            const rows = [...menu.querySelectorAll('.source-select-option:not(:disabled)')];
            const current = rows.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                rows[(current + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length]?.focus();
            }
            if (event.key === 'Escape') { event.preventDefault(); close(); control.trigger.focus(); }
        });
        menu.querySelector('[tabindex="0"]:not(:disabled)')?.focus();
    }

    function move(control, direction) {
        const options = [...control.select.options];
        let index = control.select.selectedIndex;
        do { index = (index + direction + options.length) % options.length; } while (options[index]?.disabled && index !== control.select.selectedIndex);
        choose(control, index);
    }

    function enhance(select) {
        if (select.dataset.sourceNative) return;
        select.dataset.sourceNative = 'true';
        select.classList.add('source-native-select');
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'source-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.labels?.[0]?.textContent?.trim() || 'Select an option');
        trigger.innerHTML = '<span></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
        select.after(trigger);
        const control = { select, trigger, text: trigger.firstElementChild };
        sync(control);
        select.addEventListener('change', () => sync(control));
        trigger.addEventListener('click', () => active?.select === select ? close() : open(control));
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); active?.select === select ? move(control, 1) : open(control); }
            if (event.key === 'ArrowUp') { event.preventDefault(); active?.select === select ? move(control, -1) : open(control); }
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); active?.select === select ? close() : open(control); }
            if (event.key === 'Escape') close();
        });
        if (select.id) {
            document.querySelectorAll(`label[for="${CSS.escape(select.id)}"]`).forEach((label) => {
                label.addEventListener('click', (event) => { event.preventDefault(); trigger.focus(); });
            });
        }
    }

    function iso(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        return `${y}-${m}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function parseDate(value) {
        if (!value) return null;
        const [y, m, d] = value.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    function formatDate(value) {
        const date = parseDate(value);
        return date ? new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(date) : 'Select a date';
    }

    function chooseDate(control, date) {
        if (control.input.min && iso(date) < control.input.min) return;
        control.input.value = iso(date);
        control.input.dispatchEvent(new Event('input', { bubbles: true }));
        control.input.dispatchEvent(new Event('change', { bubbles: true }));
        control.text.textContent = formatDate(control.input.value);
        close();
        control.trigger.focus();
    }

    function openDate(control) {
        close();
        const selected = parseDate(control.input.value) || new Date();
        let month = new Date(selected.getFullYear(), selected.getMonth(), 1);
        const menu = document.createElement('div');
        menu.className = 'source-date-menu';
        menu.dataset.sourceWidth = '308';
        menu.setAttribute('role', 'dialog');
        menu.setAttribute('aria-label', control.input.labels?.[0]?.textContent?.trim() || 'Choose a date');
        const render = () => {
            const firstDay = (month.getDay() + 6) % 7;
            const days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
            const monthLabel = new Intl.DateTimeFormat(document.documentElement.lang || 'en', { month: 'long', year: 'numeric' }).format(month);
            menu.innerHTML = `<div class="source-date-header"><button type="button" aria-label="Previous month">‹</button><strong>${monthLabel}</strong><button type="button" aria-label="Next month">›</button></div><div class="source-date-week">${['Mo','Tu','We','Th','Fr','Sa','Su'].map(day => `<span>${day}</span>`).join('')}</div><div class="source-date-grid"></div><div class="source-date-footer"><button type="button">Clear</button><button type="button">In a week</button><button type="button">In 2 weeks</button><button type="button">In a month</button></div>`;
            const grid = menu.querySelector('.source-date-grid');
            for (let blank = 0; blank < firstDay; blank++) grid.append(document.createElement('span'));
            for (let day = 1; day <= days; day++) {
                const date = new Date(month.getFullYear(), month.getMonth(), day);
                const button = document.createElement('button');
                button.type = 'button'; button.textContent = String(day); button.dataset.date = iso(date);
                button.disabled = Boolean(control.input.min && button.dataset.date < control.input.min);
                if (button.dataset.date === iso(new Date())) button.classList.add('today');
                if (button.dataset.date === control.input.value) { button.classList.add('selected'); button.tabIndex = 0; } else button.tabIndex = -1;
                button.addEventListener('click', () => chooseDate(control, date));
                grid.append(button);
            }
            const buttons = menu.querySelectorAll('.source-date-header button');
            buttons[0].addEventListener('click', () => { month = new Date(month.getFullYear(), month.getMonth() - 1, 1); render(); position(menu, control.trigger); });
            buttons[1].addEventListener('click', () => { month = new Date(month.getFullYear(), month.getMonth() + 1, 1); render(); position(menu, control.trigger); });
            [...menu.querySelectorAll('.source-date-footer button')].forEach((button, index) => button.addEventListener('click', () => {
                if (index === 0) { control.input.value = ''; control.input.dispatchEvent(new Event('input', { bubbles: true })); control.input.dispatchEvent(new Event('change', { bubbles: true })); control.text.textContent = 'Select a date'; close(); return; }
                const date = new Date(); date.setDate(date.getDate() + [7, 14, 30][index - 1]); chooseDate(control, date);
            }));
        };
        render(); document.body.append(menu); position(menu, control.trigger);
        control.trigger.setAttribute('aria-expanded', 'true'); active = { ...control, menu };
        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); close(); control.trigger.focus(); }
            const current = menu.querySelector('.source-date-grid button:focus');
            if (!current || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Enter'].includes(event.key)) return;
            if (event.key === 'Enter') { event.preventDefault(); current.click(); return; }
            event.preventDefault(); const date = parseDate(current.dataset.date); date.setDate(date.getDate() + ({ ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7 }[event.key]));
            if (date.getMonth() !== month.getMonth()) { month = new Date(date.getFullYear(), date.getMonth(), 1); render(); position(menu, control.trigger); }
            menu.querySelector(`[data-date="${iso(date)}"]:not(:disabled)`)?.focus();
        });
        menu.querySelector('.selected:not(:disabled), .source-date-grid button:not(:disabled)')?.focus();
    }

    function enhanceDate(input) {
        if (input.dataset.sourceDate) return;
        input.dataset.sourceDate = 'true'; input.classList.add('source-native-date');
        const trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 'source-date-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog'); trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg><span></span>';
        input.after(trigger);
        const control = { input, trigger, text: trigger.lastElementChild };
        control.text.textContent = formatDate(input.value); input.addEventListener('change', () => { control.text.textContent = formatDate(input.value); });
        trigger.addEventListener('click', () => active?.input === input ? close() : openDate(control));
        trigger.addEventListener('keydown', (event) => { if (['Enter',' ','ArrowDown'].includes(event.key)) { event.preventDefault(); openDate(control); } if (event.key === 'Escape') close(); });
        if (input.id) document.querySelectorAll(`label[for="${CSS.escape(input.id)}"]`).forEach((label) => label.addEventListener('click', (event) => { event.preventDefault(); trigger.focus(); }));
    }

    function grow(textarea) { textarea.style.height = 'auto'; textarea.style.height = `${Math.max(84, textarea.scrollHeight)}px`; }
    function init() { document.querySelectorAll(selector).forEach(enhance); document.querySelectorAll('input[type="date"].input:not([data-source-date])').forEach(enhanceDate); document.querySelectorAll('textarea').forEach((textarea) => { if (!textarea.dataset.sourceGrow) { textarea.dataset.sourceGrow = 'true'; textarea.addEventListener('input', () => grow(textarea)); } grow(textarea); }); }
    function focusables(container) {
        return [...container.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex]:not([tabindex="-1"])')].filter((element) => element.offsetParent !== null);
    }
    function trapDialog(event) {
        if (event.key !== 'Tab') return;
        const dialog = document.activeElement.closest('.source-dialog [role="dialog"], .source-help .drawer');
        if (!dialog) return;
        const items = focusables(dialog);
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    function focusNewDialogs(records) {
        for (const record of records) for (const node of record.addedNodes) {
            if (!(node instanceof HTMLElement)) continue;
            const dialog = node.matches?.('.source-dialog [role="dialog"], .source-help .drawer') ? node : node.querySelector?.('.source-dialog [role="dialog"], .source-help .drawer');
            if (dialog && !dialog.contains(document.activeElement)) window.setTimeout(() => focusables(dialog)[0]?.focus(), 0);
        }
    }
    let tooltip = null;
    function tooltipText(element) {
        if (element.dataset.tooltip) return element.dataset.tooltip;
        if (element.title) { element.dataset.tooltip = element.title; element.removeAttribute('title'); return element.dataset.tooltip; }
        if (element.matches('.source-ui.collapsed .sb-item')) return element.textContent.trim();
        return '';
    }
    function hideTooltip() { if (tooltip?._for) tooltip._for.removeAttribute('aria-describedby'); tooltip?.remove(); tooltip = null; }
    function showTooltip(element) {
        hideTooltip(); const label = tooltipText(element); if (!label || element.matches('.brand')) return;
        tooltip = document.createElement('div'); tooltip.className = 'source-tooltip'; tooltip.id = `source-tooltip-${Date.now()}`; tooltip.role = 'tooltip'; tooltip.textContent = label;
        document.body.append(tooltip); const box = element.getBoundingClientRect(); const tip = tooltip.getBoundingClientRect();
        const right = element.matches('.source-ui.collapsed .sb-item');
        let left = right ? box.right + 8 : box.left + (box.width - tip.width) / 2;
        left = Math.min(Math.max(8, left), window.innerWidth - tip.width - 8);
        let top = right ? box.top + (box.height - tip.height) / 2 : box.bottom + 7;
        if (!right && top + tip.height > window.innerHeight - 8) top = box.top - tip.height - 7;
        tooltip.style.left = `${left}px`; tooltip.style.top = `${Math.max(8, top)}px`;
        element.setAttribute('aria-describedby', tooltip.id);
        tooltip._for = element;
    }
    document.addEventListener('click', (event) => { if (active && !active.menu.contains(event.target) && event.target !== active.trigger) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); trapDialog(event); });
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || target.matches('input,textarea,select,[contenteditable=true]')) return;
        const search = document.querySelector('.source-ui .dt-search input');
        if (search) { event.preventDefault(); search.focus(); }
    });
    document.addEventListener('mouseover', (event) => { const target = event.target.closest?.('.source-ui [title], .source-ui [data-tooltip], .source-ui.collapsed .sb-item'); if (target && tooltip?._for !== target) showTooltip(target); });
    document.addEventListener('mouseout', (event) => { if (tooltip?._for && !tooltip._for.contains(event.relatedTarget)) hideTooltip(); });
    document.addEventListener('focusin', (event) => { const target = event.target.closest?.('.source-ui [title], .source-ui [data-tooltip], .source-ui.collapsed .sb-item'); if (target && event.target.matches(':focus-visible')) showTooltip(target); });
    document.addEventListener('focusout', hideTooltip);
    window.addEventListener('resize', () => active && position(active.menu, active.trigger));
    window.addEventListener('scroll', () => { if (active) position(active.menu, active.trigger); hideTooltip(); }, true);
    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
    new MutationObserver(focusNewDialogs).observe(document.documentElement, { childList: true, subtree: true });
})();
