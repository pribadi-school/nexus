import {toast} from './toast.js';
import {setBusy} from './util.js';

/** One shared form supports both the regular page and the immediately available dialog. */
export function initContentForms() {
  const lifetime = new AbortController();
  const {signal} = lifetime;
  const dialog = document.querySelector('[data-content-dialog]');
  const form = document.querySelector('[data-signage-content-form]');
  if (!form) return () => lifetime.abort();
  const type = form.querySelector('[data-content-type]');
  const file = form.querySelector('[name="file"]');
  const feedback = form.querySelector('[data-upload-feedback]');
  const updateType = () => {
    form.querySelectorAll('[data-content-fields]').forEach(group => {
      const active = group.dataset.contentFields.split(' ').includes(type.value);
      group.hidden = !active;
      group.querySelectorAll('input').forEach(input => { input.disabled = !active; });
    });
    file.accept = type.value === 'image' ? 'image/png,image/jpeg,.png,.jpg,.jpeg' : 'video/mp4,video/quicktime,.mp4,.mov';
    file.required = !form.querySelector('[name="type"][type="hidden"]') && type.value !== 'website';
    form.querySelector('[name="source_url"]').required = type.value === 'website';
    form.querySelector('[data-file-label]').textContent = `${form.querySelector('[name="type"][type="hidden"]') ? 'Replace' : 'Upload'} ${type.value === 'image' ? 'image' : 'video'}`;
  };
  type.addEventListener('change', updateType);
  updateType();
  if (!(dialog instanceof HTMLDialogElement)) {
    form.addEventListener('submit', () => {
      if (type.value !== 'website' && file.files.length) feedback.textContent = 'Uploading file. Keep this page open until the upload finishes.';
    });
    return () => lifetime.abort();
  }

  let uploading = false;
  const errorNotice = form.querySelector('[data-content-error]');
  const button = form.querySelector('[type="submit"]');
  const clearErrors = () => {
    errorNotice.hidden = true;
    form.querySelectorAll('[data-upload-field-error]').forEach(node => node.remove());
    form.querySelectorAll('[aria-invalid]').forEach(input => { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); });
    form.querySelectorAll('.has-error').forEach(group => group.classList.remove('has-error'));
  };
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href="/signage/content/create"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    dialog.showModal();
  }, {signal});
  dialog.querySelectorAll('[data-content-cancel]').forEach(close => close.addEventListener('click', () => { if (!uploading) dialog.close(); }));
  dialog.addEventListener('cancel', event => { if (uploading) event.preventDefault(); });
  dialog.addEventListener('click', event => {
    if (event.target !== dialog || uploading) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
  dialog.addEventListener('close', () => { form.reset(); clearErrors(); feedback.textContent = ''; updateType(); });
  window.addEventListener('beforeunload', event => {
    if (uploading) { event.preventDefault(); event.returnValue = ''; }
  }, {signal});

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (uploading) return;
    clearErrors();
    if (!form.reportValidity()) return;
    const body = new FormData(form);
    const editorSave = document.querySelector('[data-playlist-save]');
    const screenId = editorSave?.getAttribute('action')?.match(/^\/signage\/screens\/(\d+)\/playlist$/)?.[1];
    if (screenId) body.set('screen_id', screenId);
    uploading = true;
    form.dataset.uploading = 'true';
    form.inert = true;
    setBusy(button, true);
    dialog.querySelectorAll('[data-content-cancel]').forEach(close => { close.disabled = true; });
    feedback.textContent = type.value === 'website' ? 'Adding website…' : 'Uploading file…';
    try {
      const response = await fetch(form.action, {method: 'POST', body, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch', Accept: 'application/json'}, signal});
      if (response.redirected && new URL(response.url).pathname === '/login') throw new Error('Your session expired. Sign in in another tab, then try again.');
      let result;
      try { result = await response.json(); } catch { throw new Error('The upload could not finish. Check your connection and the server upload limit.'); }
      if (!response.ok) {
        for (const [field, message] of Object.entries(result.errors ?? {})) {
          const input = form.elements.namedItem(field);
          if (!(input instanceof HTMLElement)) continue;
          const error = document.createElement('p');
          error.className = 'field__error'; error.dataset.uploadFieldError = ''; error.id = `${input.id}-error`; error.textContent = message;
          input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', error.id);
          input.closest('.field').classList.add('has-error'); input.after(error);
        }
        throw new Error(result.error || 'Could not add this content.');
      }
      document.dispatchEvent(new CustomEvent('signage:content-added', {detail: result}));
      dialog.close();
      toast('Content added to the library.');
    } catch (error) {
      if (error.name === 'AbortError') return;
      errorNotice.textContent = error.message || 'Could not upload. Please try again.';
      errorNotice.hidden = false;
      feedback.textContent = '';
    } finally {
      uploading = false;
      delete form.dataset.uploading;
      form.inert = false;
      setBusy(button, false);
      dialog.querySelectorAll('[data-content-cancel]').forEach(close => { close.disabled = false; });
      if (dialog.open) form.querySelector('[aria-invalid="true"]')?.focus();
    }
  });

  document.addEventListener('signage:content-added', event => {
    const grid = document.querySelector('[data-content-grid]');
    if (!grid) return;
    const template = document.createElement('template');
    template.innerHTML = event.detail.card_html;
    const card = template.content.firstElementChild;
    grid.prepend(card);
    document.querySelector('[data-content-empty]')?.remove();
    const summary = document.querySelector('[data-content-summary]');
    const count = Number(summary.dataset.count) + 1;
    const bytes = Number(summary.dataset.bytes) + event.detail.size_bytes;
    summary.dataset.count = String(count); summary.dataset.bytes = String(bytes);
    const size = bytes >= 1073741824 ? `${(bytes / 1073741824).toFixed(2)} GB` : bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${(bytes / 1024).toFixed(1)} KB`;
    summary.textContent = `${count} ${count === 1 ? 'item' : 'items'} · ${size} stored`;
    document.dispatchEvent(new CustomEvent('signage:preview-added', {detail: card}));
  }, {signal});
  return () => { lifetime.abort(); if (dialog.open) dialog.close(); };
}
