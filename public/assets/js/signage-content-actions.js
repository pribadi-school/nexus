import {toast} from './toast.js';
import {setBusy, csrfToken} from './util.js';

export function initContentActions() {
  const lifetime = new AbortController();
  const {signal} = lifetime;
  const updateTargets = form => {
    const changes = [...form.querySelectorAll('input[name="screen_ids[]"]')].filter(input => input.checked !== input.defaultChecked).length;
    const button = form.querySelector('[type="submit"]');
    button.disabled = changes === 0;
    button.textContent = changes ? `Save ${changes} ${changes === 1 ? 'change' : 'changes'}` : 'Save TV playlists';
    form.dataset.unsaved = String(changes > 0);
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
    const token = csrfToken();
    if (token) body.set('_csrf', token);
    const button = form.querySelector('[type="submit"]');
    const controller = new AbortController();
    const cancel = () => controller.abort();
    let timedOut = false, confirmed = false;
    signal.addEventListener('abort', cancel, {once: true});
    // Include the response body in the deadline, not just the initial headers.
    const deadline = setTimeout(() => { timedOut = true; controller.abort(); }, 12000);
    form.dataset.saving = 'true'; form.inert = true; setBusy(button, true);
    document.dispatchEvent(new Event('signage:action-start'));
    try {
      const response = await fetch(form.action, {method: 'POST', body, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch', 'X-CSRF-Token': token, Accept: 'application/json'}, signal: controller.signal});
      if (response.status === 401 || (response.redirected && new URL(response.url).pathname === '/login')) throw new Error('Your session expired. Sign in again to continue.');
      let result;
      try { result = await response.json(); } catch (error) {
        if (controller.signal.aborted) throw error;
        throw new Error('Nexus returned an incomplete response. Refresh the page to check whether the change was saved.');
      }
      if (!result || typeof result !== 'object') throw new Error('Nexus did not confirm the change. Refresh the page to check before trying again.');
      if (!response.ok) throw new Error(result.error || 'Could not save. Please try again.');
      if (typeof result.message !== 'string' || (!deleting && (!Array.isArray(result.screen_ids)
          || !result.screen_ids.every(id => Number.isInteger(id) && id > 0) || !Number.isInteger(result.used_count)))) {
        throw new Error('Nexus did not confirm the change. Refresh the page to check before trying again.');
      }
      confirmed = true;
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
          input.checked = assigned; input.defaultChecked = assigned; input.disabled = false;
        });
        form.querySelectorAll('input[name="original_screen_ids[]"]').forEach(input => input.remove());
        result.screen_ids.forEach(id => {
          const input = document.createElement('input'); input.type = 'hidden'; input.name = 'original_screen_ids[]'; input.value = String(id); form.append(input);
        });
        card.querySelector('[data-content-usage]').textContent = `${result.used_count} playlist ${result.used_count === 1 ? 'use' : 'uses'}`;
      }
      document.dispatchEvent(new Event('signage:content-changed'));
      toast(result.message);
    } catch (error) {
      if (!signal.aborted) {
        // A lost acknowledgement can follow a committed save/delete. Never repeat it automatically.
        const message = confirmed ? 'The change was saved. Refresh the page to update the library.'
          : timedOut ? 'Nexus took too long to respond. Refresh the page to check whether the change was saved before trying again.'
          : error instanceof TypeError ? 'Connection interrupted. Nexus could not confirm the change. Refresh the page to check before trying again.'
          : error.message || 'Could not save. Please try again.';
        toast(message, {tone: 'error'});
      }
    } finally {
      clearTimeout(deadline); signal.removeEventListener('abort', cancel);
      delete form.dataset.saving; form.inert = false; setBusy(button, false);
      if (!deleting) updateTargets(form);
      document.dispatchEvent(new Event('signage:action-finished'));
    }
  }, {signal});
  window.addEventListener('beforeunload', event => {
    if (document.querySelector('[data-content-targets][data-unsaved="true"]')) { event.preventDefault(); event.returnValue = ''; }
  }, {signal});
  return () => lifetime.abort();
}
