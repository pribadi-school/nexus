// Live filtering for device lists. A GET form marked data-live-filter
// reloads only the [data-results] block as you type or pick: the list dims
// while it loads, and the address bar keeps the filters (so Back and sharing
// work). Without JavaScript the form's Apply button submits normally.

import { request } from './util.js';
import { toast } from './toast.js';

const TYPING_DELAY = 250;

function urlFor(form) {
  const params = new URLSearchParams();
  for (const [key, value] of new FormData(form)) {
    if (typeof value === 'string' && value.trim() !== '') params.set(key, value.trim());
  }
  const query = params.toString();
  return form.getAttribute('action') + (query ? `?${query}` : '');
}

function initForm(form) {
  const results = document.querySelector('[data-results]');
  if (!results) return;
  form.classList.add('is-live');

  let timer = null;
  let controller = null;
  let latest = 0;

  const run = async () => {
    clearTimeout(timer);
    const url = urlFor(form);
    controller?.abort();
    controller = new AbortController();
    const ticket = ++latest;
    results.setAttribute('aria-busy', 'true');
    try {
      const data = await request(url, { signal: controller.signal });
      if (ticket !== latest) return;
      results.innerHTML = data.html;
      history.replaceState(history.state, '', url);
    } catch (error) {
      if (error.name !== 'AbortError') toast(error.message, { tone: 'error' });
    } finally {
      if (ticket === latest) results.removeAttribute('aria-busy');
    }
  };

  form.addEventListener('input', (event) => {
    if (event.target.matches('input[type="search"], input[type="text"]')) {
      clearTimeout(timer);
      timer = setTimeout(run, TYPING_DELAY);
    }
  });
  form.addEventListener('change', (event) => {
    if (event.target.matches('select')) run();
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    run();
  });

  // "Clear filters" links inside the results.
  results.addEventListener('click', (event) => {
    if (!event.target.closest('[data-clear-filters]')) return;
    event.preventDefault();
    form.querySelectorAll('input, select').forEach((control) => { control.value = ''; });
    run();
  });
}

export function initLiveFilters() {
  document.querySelectorAll('form[data-live-filter]').forEach(initForm);
}
