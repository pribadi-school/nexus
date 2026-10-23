// Nexus front-end: small progressive enhancements. Every page works without
// JavaScript; these add confirmations, busy states and in-place loading.

import { initConfirm } from './confirm.js';
import { initFeed } from './feed.js';
import { initLiveFilters } from './filters.js?v=20261022-search-all';
import { initNoteForms, initNoteImages } from './note-form.js';
import { initStatusToggles } from './status-toggle.js';
import { setBusy } from './util.js';
import { initNavigation } from './navigation.js?v=20261019-performance';
// cPanel caches modules for a year; new behavior needs fresh module URLs.
import { initSignageAdmin } from './signage-admin.js?v=20261022-posters';
import { initSignageEditor } from './signage-editor.js?v=20261023-menu';
import { initChromebookGoogle } from './chromebook-google.js';
import { initTvControl } from './tv-control.js?v=20261022-tv-control2';
import { initBell } from './bell.js?v=20261022-bell2';
import { initPullToRefresh } from './pull-refresh.js?v=20261022-ptr';

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
    document.querySelectorAll('[aria-busy="true"]').forEach((el) => setBusy(el, false));
  });
}

initDismiss();
initBusyForms();
initConfirm();
initFeed();
initStatusToggles();
let cleanupPage = () => {};
function initPage() {
  const cleanupFilters = initLiveFilters();
  initNoteForms();
  initNoteImages();
  const cleanupEditor = initSignageEditor();
  const cleanupSignage = initSignageAdmin();
  const cleanupGoogle = initChromebookGoogle();
  const cleanupTv = initTvControl();
  const cleanupBell = initBell();
  cleanupPage = () => { cleanupFilters(); cleanupEditor(); cleanupSignage(); cleanupGoogle(); cleanupTv(); cleanupBell(); };
}
initPullToRefresh();
initPage();
initNavigation({beforeSwap: () => cleanupPage(), afterSwap: initPage});
