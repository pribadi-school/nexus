// Nexus front-end: small progressive enhancements. Every page works without
// JavaScript; these add confirmations, busy states and in-place loading.

import { initConfirm } from './confirm.js';
import { initFeed } from './feed.js';
import { setBusy } from './util.js';

function initDismiss() {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-dismiss]');
    if (button) button.closest('[data-flash]')?.remove();
  });
}

function initBusyForms() {
  // A form marked data-busy-on-submit shows a spinner on its submit button
  // once it really submits (after the browser's own validation passed).
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-busy-on-submit') || event.defaultPrevented) return;
    const button = event.submitter ?? form.querySelector('[type="submit"]');
    // Deferred so the button's own name/value is still sent with the form.
    setTimeout(() => setBusy(button, true));
  });
  // Coming back with the Back button must not leave a spinner behind.
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('.is-busy').forEach((el) => setBusy(el, false));
  });
}

initDismiss();
initBusyForms();
initConfirm();
initFeed();
