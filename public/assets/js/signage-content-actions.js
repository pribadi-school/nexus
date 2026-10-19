import {toast} from './toast.js';
import {setBusy} from './util.js';

export function initContentActions() {
  const lifetime = new AbortController();
  const {signal} = lifetime;
  const updateTargets = form => {
    const selected = form.querySelectorAll('input[name="screen_ids[]"]:checked:not(:disabled)').length;
    const button = form.querySelector('[type="submit"]');
    button.disabled = selected === 0;
    button.textContent = selected ? `Add to ${selected} ${selected === 1 ? 'TV' : 'TVs'}` : 'Add to selected TVs';
  };
  document.querySelectorAll('[data-content-targets]').forEach(updateTargets);
  document.addEventListener('signage:content-added', () => document.querySelectorAll('[data-content-targets]').forEach(updateTargets), {signal});
  document.addEventListener('change', event => {
    const form = event.target.closest('[data-content-targets]');
    if (form) updateTargets(form);
  }, {signal});
  document.addEventListener('submit', async event => {
    const form = event.target;
    // The shared confirmation dialog handles the first delete submission.
    if (event.defaultPrevented || !form.matches('[data-content-targets], [data-content-delete]')) return;
    event.preventDefault();
    if (form.dataset.saving === 'true') return;
    const deleting = form.matches('[data-content-delete]');
    const card = form.closest('[data-content-card]');
    const body = new FormData(form);
    const button = form.querySelector('[type="submit"]');
    form.dataset.saving = 'true'; form.inert = true; setBusy(button, true);
    try {
      const response = await fetch(form.action, {method: 'POST', body, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch', Accept: 'application/json'}, signal});
      if (response.redirected && new URL(response.url).pathname === '/login') throw new Error('Your session expired. Sign in again to continue.');
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'Could not save. Please try again.');
      const menu = form.closest('[popover]');
      if (menu?.matches(':popover-open')) menu.hidePopover();
      if (deleting) {
        const summary = document.querySelector('[data-content-summary]');
        if (summary) {
          const count = Math.max(0, Number(summary.dataset.count) - 1);
          const bytes = Math.max(0, Number(summary.dataset.bytes) - Number(card.dataset.sizeBytes));
          summary.dataset.count = String(count); summary.dataset.bytes = String(bytes);
          const size = bytes >= 1073741824 ? `${(bytes / 1073741824).toFixed(2)} GB` : bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${(bytes / 1024).toFixed(1)} KB`;
          summary.textContent = `${count} ${count === 1 ? 'item' : 'items'}${count ? ` · ${size} stored` : ''}`;
        }
        card.remove();
        const grid = document.querySelector('[data-content-grid]');
        if (grid && !grid.children.length) {
          const empty = document.createElement('p'); empty.className = 'signage-help'; empty.dataset.contentEmpty = '';
          empty.textContent = 'No content yet. Use Add content to upload a video or image, or add a website.';
          grid.before(empty);
        }
      } else {
        form.querySelectorAll('input[name="screen_ids[]"]').forEach(input => {
          const assigned = result.screen_ids.includes(Number(input.value));
          input.checked = assigned; input.disabled = assigned;
          const label = input.nextElementSibling;
          if (assigned && !label.querySelector('small')) {
            const note = document.createElement('small'); note.textContent = 'Already added'; label.append(note);
          }
        });
        card.querySelector('[data-content-usage]').textContent = `${result.used_count} playlist ${result.used_count === 1 ? 'use' : 'uses'}`;
      }
      document.dispatchEvent(new Event('signage:content-changed'));
      toast(result.message);
    } catch (error) {
      if (error.name !== 'AbortError') toast(error.message || 'Could not save. Please try again.', {tone: 'error'});
    } finally {
      delete form.dataset.saving; form.inert = false; setBusy(button, false);
      if (!deleting) updateTargets(form);
    }
  }, {signal});
  return () => lifetime.abort();
}
