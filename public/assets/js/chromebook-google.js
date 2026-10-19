import { request, setBusy } from './util.js';

/** Page-scoped: background work stops when fast navigation leaves the Chromebook. */
export function initChromebookGoogle() {
  let panel = document.querySelector('[data-google-device]');
  if (!panel) return () => {};
  const lifecycle = new AbortController();
  const { signal } = lifecycle;
  let pending = false;
  let moving = false;
  let timer;

  window.addEventListener('beforeunload', event => {
    if (moving || panel.dataset.unsaved === 'true') {
      event.preventDefault();
      event.returnValue = '';
    }
  }, { signal });

  const schedule = () => {
    clearTimeout(timer);
    if (panel?.hasAttribute('data-google-auto-refresh') && !signal.aborted) {
      timer = setTimeout(() => refresh(false), 300000);
    }
  };
  const message = text => {
    const output = panel.querySelector('[data-google-message]');
    if (output) output.textContent = text;
  };
  const replace = html => {
    const template = document.createElement('template');
    template.innerHTML = html;
    const next = template.content.querySelector('[data-google-device]');
    if (!next || signal.aborted) return;
    panel.replaceWith(next);
    panel = next;
    bind();
  };
  async function refresh(force) {
    if (pending || moving || signal.aborted) return;
    if (!force && (document.hidden || panel.querySelector('[data-google-ou][open]'))) { schedule(); return; }
    pending = true;
    const button = panel.querySelector('[data-google-refresh-form] button');
    setBusy(button, true);
    try {
      const data = await request(panel.dataset.googleRefresh, {
        method: 'POST', signal, body: new URLSearchParams({ force: force ? '1' : '0' }),
      });
      // Opening the OU form during a request must not replace entered values.
      if (!panel.querySelector('[data-google-ou][open]')) replace(data.html);
    } catch (error) {
      if (error.name !== 'AbortError') message(error.message);
    } finally {
      pending = false;
      setBusy(button, false);
      schedule();
    }
  }

  function bind() {
    panel.querySelector('[data-google-refresh-form]')?.addEventListener('submit', event => {
      event.preventDefault();
      if (panel.dataset.unsaved === 'true') { message('Finish or close the OU form before refreshing.'); return; }
      refresh(true);
    }, { signal });
    const details = panel.querySelector('[data-google-ou]');
    const form = details?.querySelector('[data-google-move-form]');
    if (!details || !form) return;
    let unitsLoaded = false;
    let unitsLoading = false;
    form.addEventListener('input', () => { panel.dataset.unsaved = 'true'; }, { signal });
    details.addEventListener('toggle', async () => {
      if (!details.open) {
        // Closing the form explicitly discards the management password and draft.
        form.reset();
        delete panel.dataset.unsaved;
        return;
      }
      if (unitsLoaded || unitsLoading) return;
      unitsLoading = true;
      const status = details.querySelector('[data-google-ou-status]');
      status.textContent = 'Loading organizational units…';
      try {
        const data = await request(details.dataset.unitsUrl, { signal });
        const select = form.elements.org_unit;
        const current = form.elements.expected_ou.value;
        for (const path of data.units) {
          const option = new Option(path === current ? `${path} (current)` : path, path);
          option.disabled = path === current;
          select.add(option);
        }
        select.disabled = false;
        form.querySelector('[type="submit"]').disabled = false;
        unitsLoaded = true;
        status.textContent = data.units.length ? '' : 'Google returned no organizational units.';
      } catch (error) {
        if (error.name !== 'AbortError') status.textContent = `${error.message} Close and reopen this form to retry.`;
      } finally { unitsLoading = false; }
    }, { signal });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (moving || pending) { message('Wait for the current update, then try again.'); return; }
      moving = true;
      const button = event.submitter ?? form.querySelector('[type="submit"]');
      setBusy(button, true);
      message('Requesting the OU change…');
      try {
        const body = new FormData(form);
        // Never retain the management password in the page after submitting.
        form.elements.management_password.value = '';
        const data = await request(form.action, { method: 'POST', body, signal });
        replace(data.html);
        message(data.message);
      } catch (error) {
        if (error.name !== 'AbortError') message(error.message);
      } finally { moving = false; setBusy(button, false); schedule(); }
    }, { signal });
  }
  bind();
  if (panel.hasAttribute('data-google-auto-refresh')) refresh(false);
  return () => { clearTimeout(timer); lifecycle.abort(); };
}
