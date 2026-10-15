// Instant status switch on device lists. A switch form marked
// data-status-toggle changes the status in place (no page reload): it flips
// at once, saves in the background, and flips back if saving fails.
// Without JavaScript the form posts normally and the page reloads.

import { request } from './util.js';
import { toast } from './toast.js';

/** Every switch and row for one device (the list has a table row and a card). */
function partsFor(action) {
  return {
    forms: [...document.querySelectorAll(`form[data-status-toggle][action="${action}"]`)],
    rows: [...document.querySelectorAll(`[data-device-row="${action.match(/\/devices\/(\d+)\//)?.[1]}"]`)],
  };
}

function show(action, status) {
  const ok = status === 'ok';
  const { forms, rows } = partsFor(action);
  for (const form of forms) {
    const button = form.querySelector('.switch');
    button.classList.toggle('is-ok', ok);
    button.classList.toggle('is-problem', !ok);
    button.querySelector('.switch__label').textContent = ok ? button.dataset.labelOk : button.dataset.labelProblem;
    button.querySelector('[data-switch-hint]').textContent =
      `: ${button.dataset.title}. Change to ${ok ? button.dataset.labelProblem : button.dataset.labelOk}`;
    form.elements.status.value = ok ? 'problem' : 'ok';
  }
  rows.forEach((row) => row.classList.toggle('is-problem', !ok));
}

function setDisabled(action, disabled) {
  partsFor(action).forms.forEach((form) => { form.querySelector('.switch').disabled = disabled; });
}

export function initStatusToggles() {
  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-status-toggle')) return;
    event.preventDefault();

    const action = form.getAttribute('action');
    const next = form.elements.status.value;
    const previous = next === 'ok' ? 'problem' : 'ok';
    const body = new FormData(form);

    show(action, next);
    setDisabled(action, true);
    try {
      const data = await request(action, { method: 'POST', body });
      toast(data.message, data.status === 'problem' ? { action: { href: data.noteUrl, label: 'Add a note' } } : {});
    } catch (error) {
      show(action, previous);
      toast(`Not saved: ${error.message}`, { tone: 'error' });
    } finally {
      setDisabled(action, false);
    }
  });
}
