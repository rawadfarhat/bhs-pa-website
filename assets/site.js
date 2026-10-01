document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.site-nav');
  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav?.classList.toggle('is-open', !open);
  });

  const form = document.querySelector('[data-contact-form]');
  const children = form?.querySelector('[data-children]');
  const template = document.querySelector('#child-template');
  const updateRemoveButtons = () => {
    const rows = children?.querySelectorAll('.child-row') ?? [];
    rows.forEach((row) => { const button = row.querySelector('.remove-child'); if (button) button.hidden = rows.length === 1; });
  };
  form?.querySelector('[data-add-child]')?.addEventListener('click', () => {
    if ((children?.querySelectorAll('.child-row').length ?? 0) >= 10) return;
    if (children && template instanceof HTMLTemplateElement) children.append(template.content.cloneNode(true));
    updateRemoveButtons();
  });
  children?.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-child');
    if (button && children.querySelectorAll('.child-row').length > 1) button.closest('.child-row')?.remove();
    updateRemoveButtons();
  });
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const status = form.querySelector('.form-status');
    const button = form.querySelector('.submit-button');
    if (!status || !button) return;
    button.disabled = true;
    status.hidden = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const data = await response.json();
      status.textContent = data.message;
      status.className = `form-status ${response.ok ? 'success' : 'error'}`;
      status.hidden = false;
      if (response.ok) { form.reset(); children?.querySelectorAll('.child-row:not(:first-child)').forEach((row) => row.remove()); updateRemoveButtons(); status.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    } catch {
      status.textContent = 'We could not submit the form. Please check your connection and try again.';
      status.className = 'form-status error';
      status.hidden = false;
    } finally { button.disabled = false; }
  });
  updateRemoveButtons();
});

