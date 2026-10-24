import {toast} from './toast.js';
import {setBusy, csrfToken} from './util.js';
import {sendContentUpload} from './signage-upload.js';

/** Shared popup and regular-page uploader; each request contains one file. */
export function initContentForms() {
  const lifetime = new AbortController();
  const {signal} = lifetime;
  const dialog = document.querySelector('[data-content-dialog]');
  const form = document.querySelector('[data-signage-content-form]');
  if (!form) return () => lifetime.abort();
  const inDialog = dialog instanceof HTMLDialogElement;
  const adding = form.hasAttribute('data-content-adding');
  const enhanced = adding && typeof XMLHttpRequest !== 'undefined';
  const type = form.querySelector('[data-content-type]');
  const file = form.querySelector('[name="file"]');
  const feedback = form.querySelector('[data-upload-feedback]');
  const limit = Number(form.dataset.uploadLimitBytes);
  const sizeError = selected => selected && limit && selected.size > limit
    ? `Choose a file up to ${new Intl.NumberFormat(undefined, {maximumFractionDigits: 1}).format(limit / 1048576)} MB.` : '';
  const updateType = () => {
    form.querySelectorAll('[data-content-fields]').forEach(group => {
      const active = group.dataset.contentFields.split(' ').includes(type.value);
      group.hidden = !active;
      group.querySelectorAll('input').forEach(input => { input.disabled = !active; });
    });
    file.accept = enhanced ? 'video/mp4,video/quicktime,image/png,image/jpeg,.mp4,.mov,.png,.jpg,.jpeg'
      : type.value === 'image' ? 'image/png,image/jpeg,.png,.jpg,.jpeg' : 'video/mp4,video/quicktime,.mp4,.mov';
    file.required = !enhanced && adding && type.value !== 'website'; file.multiple = enhanced;
    form.querySelector('[name="source_url"]').required = type.value === 'website';
    form.querySelector('[data-file-label]').textContent = enhanced ? 'Upload files'
      : `${adding ? 'Upload' : 'Replace'} ${type.value === 'image' ? 'image' : 'video'}`;
  };
  type.addEventListener('change', updateType, {signal}); updateType();
  if (!enhanced) {
    form.addEventListener('submit', event => {
      const error = type.value === 'website' ? '' : sizeError(file.files[0]);
      if (error) { event.preventDefault(); feedback.textContent = error; return; }
      if (type.value !== 'website' && file.files.length) feedback.textContent = 'Uploading file…';
    }, {signal});
    return () => lifetime.abort();
  }
  const errorNotice = form.querySelector('[data-content-error]');
  const button = form.querySelector('[type="submit"]');
  const controls = form.querySelector('[data-upload-controls]');
  const drop = form.querySelector('[data-upload-drop]');
  const list = form.querySelector('[data-upload-queue]');
  const progress = form.querySelector('[data-upload-progress]');
  const meter = form.querySelector('[data-upload-meter]');
  const percentage = form.querySelector('[data-upload-percentage]');
  const progressStatus = form.querySelector('[data-upload-progress-status]');
  const cancelButtons = inDialog ? dialog.querySelectorAll('[data-content-cancel]') : [];
  const defaultButtonText = button.textContent;
  let queue = [], uploading = false;
  const clearErrors = () => {
    errorNotice.hidden = true;
    form.querySelectorAll('[data-upload-field-error]').forEach(node => node.remove());
    form.querySelectorAll('[aria-invalid]').forEach(input => { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); });
    form.querySelectorAll('.has-error').forEach(group => group.classList.remove('has-error'));
  };
  const updateEntry = entry => {
    entry.status.textContent = entry.state === 'done' ? 'Added' : entry.state === 'error' ? entry.message
      : entry.state === 'saving' ? '99% · Saving…'
      : entry.state === 'uploading' ? (entry.progress === null ? 'Uploading…' : `${entry.progress}% · Uploading`)
      : `${entry.type === 'image' ? 'Image' : 'Video'} · ${entry.file.size >= 1048576 ? `${(entry.file.size / 1048576).toFixed(1)} MB` : `${(entry.file.size / 1024).toFixed(1)} KB`}`;
    entry.status.classList.toggle('is-error', entry.state === 'error');
  };
  const renderQueue = () => {
    list.replaceChildren(); list.hidden = queue.length === 0;
    queue.forEach(entry => {
      const row = document.createElement('li');
      const info = document.createElement('div'); info.className = 'signage-upload-queue__info';
      const name = document.createElement('span'); name.className = 'signage-upload-queue__name'; name.textContent = entry.file.name; name.title = entry.file.name;
      entry.status = document.createElement('span'); entry.status.className = 'signage-upload-queue__status';
      const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn--secondary btn--sm'; remove.textContent = 'Remove';
      remove.setAttribute('aria-label', `Remove ${entry.file.name}`); remove.disabled = uploading;
      remove.addEventListener('click', () => { if (uploading) return; queue = queue.filter(item => item !== entry); renderQueue(); clearErrors(); });
      info.append(name, entry.status); row.append(info, remove); list.append(row); updateEntry(entry);
    });
    const remaining = queue.filter(entry => entry.state !== 'done');
    if (!uploading) button.textContent = type.value === 'website' ? defaultButtonText
      : remaining.some(entry => entry.state === 'error' && !entry.validationError) ? 'Retry failed files'
      : remaining.length > 1 ? `Add ${remaining.length} files` : defaultButtonText;
  };
  type.addEventListener('change', renderQueue, {signal});
  const addFiles = files => {
    if (uploading) return;
    clearErrors(); progress.hidden = true; feedback.textContent = '';
    for (const selected of files) {
      if (queue.some(entry => entry.file.name === selected.name && entry.file.size === selected.size && entry.file.lastModified === selected.lastModified)) continue;
      const detected = /\.(png|jpe?g)$/i.test(selected.name) ? 'image' : /\.(mp4|mov)$/i.test(selected.name) ? 'video' : null;
      const validationError = sizeError(selected) || (!detected ? 'Choose an MP4, MOV, PNG or JPG file.' : selected.size === 0 ? 'This file is empty.' : '');
      queue.push({file: selected, type: detected, validationError, state: validationError ? 'error' : 'pending', message: validationError, progress: 0});
    }
    renderQueue();
  };
  file.addEventListener('change', () => { addFiles([...file.files]); file.value = ''; }, {signal});
  form.querySelector('[data-upload-drop-hint]').hidden = false;
  const dropTarget = inDialog ? dialog : form;
  for (const name of ['dragenter', 'dragover']) dropTarget.addEventListener(name, event => {
    if (!event.dataTransfer?.types.includes('Files')) return;
    event.preventDefault(); event.dataTransfer.dropEffect = uploading || type.value === 'website' ? 'none' : 'copy';
    drop.classList.toggle('is-drag-over', !uploading && type.value !== 'website');
  }, {signal});
  dropTarget.addEventListener('dragleave', event => { if (!dropTarget.contains(event.relatedTarget)) drop.classList.remove('is-drag-over'); }, {signal});
  dropTarget.addEventListener('drop', event => {
    if (!event.dataTransfer?.types.includes('Files')) return;
    event.preventDefault(); drop.classList.remove('is-drag-over');
    if (type.value !== 'website') addFiles([...event.dataTransfer.files]);
  }, {signal});
  if (inDialog) {
    document.addEventListener('click', event => {
      const link = event.target.closest('a[href="/signage/content/create"]');
      if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault(); dialog.showModal();
    }, {signal});
    cancelButtons.forEach(close => close.addEventListener('click', () => { if (!uploading) dialog.close(); }, {signal}));
    dialog.addEventListener('cancel', event => { if (uploading) event.preventDefault(); }, {signal});
    dialog.addEventListener('click', event => {
      if (event.target !== dialog || uploading) return;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    }, {signal});
    dialog.addEventListener('close', () => { form.reset(); queue = []; renderQueue(); clearErrors(); progress.hidden = true; feedback.textContent = ''; updateType(); }, {signal});
  }
  window.addEventListener('beforeunload', event => { if (uploading) { event.preventDefault(); event.returnValue = ''; } }, {signal});
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (uploading) return; clearErrors();
    const isWebsite = type.value === 'website';
    const tasks = isWebsite ? [null] : queue.filter(entry => entry.state !== 'done' && !entry.validationError);
    if (!tasks.length) {
      errorNotice.textContent = queue.some(entry => entry.validationError) ? 'Remove the invalid files or choose supported files up to the limit shown.' : 'Choose files to upload.';
      errorNotice.hidden = false; return;
    }
    if (!form.reportValidity()) return;
    const body = new FormData(form), token = csrfToken();
    if (token) body.set('_csrf', token);
    const screenId = document.querySelector('[data-playlist-save]')?.getAttribute('action')?.match(/^\/signage\/screens\/(\d+)\/playlist$/)?.[1];
    if (screenId) body.set('screen_id', screenId);
    uploading = true; form.dataset.uploading = 'true'; controls.disabled = true;
    renderQueue(); setBusy(button, true); cancelButtons.forEach(close => { close.disabled = true; });
    progress.hidden = isWebsite; meter.value = 0; percentage.textContent = '0%';
    let completedBytes = 0, added = 0;
    const totalBytes = tasks.reduce((sum, entry) => sum + (entry?.file.size ?? 0), 0);
    try {
      for (const [index, entry] of tasks.entries()) {
        if (signal.aborted) return;
        if (entry) {
          body.set('file', entry.file, entry.file.name); body.set('type', entry.type);
          entry.state = 'uploading'; entry.progress = 0; updateEntry(entry);
          progressStatus.textContent = `Uploading ${index + 1} of ${tasks.length}`;
        }
        feedback.textContent = entry ? entry.file.name : 'Adding website…';
        try {
          const result = await sendContentUpload(form.action, {body, token, signal, onProgress: ratio => {
            if (!entry || signal.aborted) return;
            entry.progress = ratio === null ? null : Math.min(99, Math.floor(ratio * 100));
            entry.state = ratio === 1 ? 'saving' : 'uploading'; updateEntry(entry);
            if (ratio === null) { meter.removeAttribute('value'); percentage.textContent = 'Uploading…'; }
            else {
              meter.value = Math.min(99, Math.floor((completedBytes + entry.file.size * ratio) / totalBytes * 100));
              percentage.textContent = `${meter.value}%`;
            }
          }});
          if (entry) { entry.state = 'done'; completedBytes += entry.file.size; updateEntry(entry); }
          added++; document.dispatchEvent(new CustomEvent('signage:content-added', {detail: result}));
        } catch (error) {
          if (error.name === 'AbortError') return;
          if (entry) { entry.state = 'error'; entry.message = error.message; updateEntry(entry); }
          if (isWebsite) for (const [field, message] of Object.entries(error.fields ?? {})) {
            const input = form.elements.namedItem(field);
            if (!(input instanceof HTMLElement)) continue;
            const detail = document.createElement('p');
            detail.className = 'field__error'; detail.dataset.uploadFieldError = ''; detail.id = `${input.id}-error`; detail.textContent = message;
            input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', detail.id);
            input.closest('.field')?.classList.add('has-error'); input.after(detail);
          }
          errorNotice.textContent = error.message || 'Could not upload. Please try again.'; errorNotice.hidden = false;
          if (isWebsite || error.status === 401 || error.status === 419) break;
        }
      }
      const remaining = isWebsite ? added === 0 : queue.some(entry => entry.state !== 'done');
      if (!remaining) {
        meter.value = 100; percentage.textContent = '100%';
        if (inDialog) dialog.close(); else location.assign('/signage/content');
        toast(added === 1 ? 'Content added to the library.' : `${added} files added to the library.`);
      } else {
        progressStatus.textContent = `${added} of ${tasks.length} added`;
        feedback.textContent = 'Successful files are already in the library. Retry only the remaining files.';
      }
    } finally {
      uploading = false; delete form.dataset.uploading; controls.disabled = false;
      setBusy(button, false); cancelButtons.forEach(close => { close.disabled = false; }); updateType(); renderQueue();
      document.dispatchEvent(new Event('signage:uploads-finished'));
    }
  }, {signal});
  document.addEventListener('signage:content-added', event => {
    const grid = document.querySelector('[data-content-grid]');
    if (!grid) return;
    const template = document.createElement('template'); template.innerHTML = event.detail.card_html;
    const card = template.content.firstElementChild; grid.prepend(card); document.querySelector('[data-content-empty]')?.remove();
    const summary = document.querySelector('[data-content-summary]');
    const count = Number(summary.dataset.count) + 1, bytes = Number(summary.dataset.bytes) + event.detail.size_bytes;
    summary.dataset.count = String(count); summary.dataset.bytes = String(bytes);
    const size = bytes >= 1073741824 ? `${(bytes / 1073741824).toFixed(2)} GB` : bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${(bytes / 1024).toFixed(1)} KB`;
    summary.textContent = `${count} ${count === 1 ? 'item' : 'items'} · ${size} stored`;
    document.dispatchEvent(new CustomEvent('signage:preview-added', {detail: card}));
  }, {signal});
  return () => { lifetime.abort(); if (inDialog && dialog.open) dialog.close(); };
}
