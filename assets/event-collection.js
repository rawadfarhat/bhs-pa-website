(() => {
  const dialog = document.getElementById('event-collection-dialog');
  const form = document.getElementById('event-collection-form');
  if (!dialog || !form) return;
  let opener;
  const picker = form.querySelector('.event-country-picker');
  const country = form.querySelector('[name="phone_country"]');
  const toggle = picker?.querySelector('.event-country-toggle');
  const panel = picker?.querySelector('.event-country-panel');
  const search = picker?.querySelector('.event-country-search');
  const options = [...(picker?.querySelectorAll('[data-country]') || [])];
  // The popover top layer escapes the dialog's overflow clipping while keeping
  // the picker inside the dialog's focus and form ownership.
  if (panel && typeof panel.showPopover === 'function') panel.setAttribute('popover', 'manual');
  const positionCountries = () => {
    if (!panel || panel.hidden) return;
    const anchor = toggle.getBoundingClientRect();
    const viewport = window.visualViewport;
    const leftEdge = (viewport?.offsetLeft || 0) + 12;
    const topEdge = (viewport?.offsetTop || 0) + 12;
    const rightEdge = leftEdge + (viewport?.width || window.innerWidth) - 24;
    const bottomEdge = topEdge + (viewport?.height || window.innerHeight) - 24;
    const below = Math.max(0, bottomEdge - anchor.bottom - 5);
    const above = Math.max(0, anchor.top - topEdge - 5);
    const openAbove = below < 300 && above > below;
    const available = openAbove ? above : below;
    panel.style.width = Math.max(0, Math.min(340, rightEdge - leftEdge)) + 'px';
    panel.style.left = Math.max(leftEdge, Math.min(anchor.left, rightEdge - panel.getBoundingClientRect().width)) + 'px';
    panel.querySelector('#event-country-options').style.maxHeight = Math.max(40, Math.min(240, available - search.getBoundingClientRect().height - 24)) + 'px';
    panel.style.top = (openAbove ? Math.max(topEdge, anchor.top - panel.getBoundingClientRect().height - 5) : anchor.bottom + 5) + 'px';
  };
  const closeCountries = (restoreFocus = false) => {
    if (!panel) return;
    if (panel.hasAttribute('popover') && panel.matches(':popover-open')) panel.hidePopover();
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (restoreFocus) toggle.focus();
  };
  const visibleOptions = () => options.filter(option => !option.hidden);
  toggle?.addEventListener('click', () => {
    if (!panel.hidden) return closeCountries();
    search.value = '';
    options.forEach(option => option.hidden = false);
    panel.hidden = false;
    if (panel.hasAttribute('popover')) panel.showPopover();
    positionCountries();
    toggle.setAttribute('aria-expanded', 'true');
    search.focus({ preventScroll: true });
    positionCountries();
  });
  search?.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase();
    options.forEach(option => {
      option.hidden = !(option.dataset.name + ' +' + option.dataset.code).toLocaleLowerCase().includes(term);
    });
    positionCountries();
  });
  options.forEach(option => option.addEventListener('click', () => {
    country.value = option.dataset.country;
    picker.querySelector('[data-phone-country-flag]').src = option.querySelector('img').getAttribute('src');
    picker.querySelector('[data-phone-country-code]').textContent = '+' + option.dataset.code;
    toggle.setAttribute('aria-label', `Phone country: ${option.dataset.name} +${option.dataset.code}`);
    options.forEach(item => item.setAttribute('aria-selected', String(item === option)));
    closeCountries(true);
  }));
  picker?.addEventListener('keydown', event => {
    if (panel.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault(); event.stopPropagation(); closeCountries(true); return;
    }
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault();
      const visible = visibleOptions();
      if (!visible.length) return;
      let index = visible.indexOf(document.activeElement);
      if (event.key === 'Home') index = 0;
      else if (event.key === 'End') index = visible.length - 1;
      else if (index < 0) index = event.key === 'ArrowDown' ? 0 : visible.length - 1;
      else index = (index + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
      visible[index].focus();
    }
  });
  document.addEventListener('click', event => { if (picker && !picker.contains(event.target)) closeCountries(); });
  picker?.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) closeCountries(); });
  dialog.addEventListener('scroll', positionCountries);
  window.addEventListener('resize', positionCountries);
  window.visualViewport?.addEventListener('resize', positionCountries);
  window.visualViewport?.addEventListener('scroll', positionCountries);
  dialog.addEventListener('close', () => closeCountries());
  document.querySelectorAll('[data-event-collection-open]').forEach(button => button.addEventListener('click', () => {
    opener = button;
    dialog.showModal();
  }));
  dialog.querySelector('.event-collection-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => opener?.focus());
  dialog.addEventListener('click', event => { if (event.target === dialog) {
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  }});
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    const message = document.getElementById('event-collection-message');
    message.textContent = '';
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
      const result = await response.json();
      message.textContent = result.message;
      if (response.ok) {
        form.hidden = true;
        const intro = dialog.querySelector('[data-event-collection-intro]');
        if (intro) intro.hidden = true;
        message.focus();
        const statistics = document.querySelectorAll('[data-event-collection-stat]');
        if (statistics.length) {
          try {
            const totalsResponse = await fetch(form.dataset.totalsUrl, { credentials: 'same-origin', cache: 'no-store' });
            if (totalsResponse.ok) {
              const totals = await totalsResponse.json();
              statistics.forEach(stat => {
                const value = totals.data?.[stat.dataset.eventCollectionStat];
                if (typeof value === 'string') stat.textContent = value;
              });
              document.querySelectorAll('[data-event-progress]').forEach(container => {
                const raw = totals.data?.[container.dataset.eventProgress];
                const progress = container.querySelector('progress');
                if (typeof raw !== 'string' || !Number.isFinite(Number(raw))) return;
                progress.value = Math.max(0, Math.min(progress.max, Number(raw)));
                progress.setAttribute('aria-valuetext', `${raw} of ${progress.getAttribute('max')}`);
              });
            }
          } catch { /* The submission succeeded; totals will also refresh on the next page load. */ }
        }
      }
    } catch { message.textContent = 'Unable to submit at the moment. Please try again.'; }
    finally { button.disabled = false; }
  });
})();
