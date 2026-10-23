// Pull down from the top of a page on a touch screen to refresh it, with a
// spinner that follows the finger like a native app. It reuses the page bar's
// Refresh link, so the in-place navigation refetches the page without a full
// reload. Never starts over an open menu, a form field, the playlist editor
// (hold-and-drag lives there) or unsaved changes.
import { iconHtml } from './util.js';
import { toast } from './toast.js';

const START = 24;       // finger travel before a touch counts as a pull; a tap drifts less
const THRESHOLD = 72;   // pulled distance (px) that arms a refresh
const MAX_PULL = 120;
const RESIST = 0.5;     // the finger moves twice as far as the page
const HOLD = 56;        // where the spinner rests while loading
const MIN_SHOW = 600;   // keep the spinner up at least this long (ms)

export function initPullToRefresh() {
  const main = document.getElementById('main');
  if (!main || !window.matchMedia('(pointer: coarse)').matches || document.querySelector('.ptr')) return;

  const ptr = document.createElement('div');
  ptr.className = 'ptr';
  ptr.setAttribute('aria-hidden', 'true');
  ptr.innerHTML = iconHtml('refresh-cw');
  document.body.append(ptr);
  const icon = ptr.querySelector('.icon');

  let startX = 0;
  let startY = 0;
  let distance = 0;
  let tracking = false;
  let pulling = false;
  let armed = false;
  let refreshing = false;

  const blocked = target => !!target.closest?.(
    'input, textarea, select, button, summary, label, [role="button"], [role="switch"], [popovertarget], dialog[open], [popover]:popover-open, .signage-editor, [data-editor-item], [data-content-dialog]'
  ) || document.body.classList.contains('is-signage-dragging');
  const hasDraft = () => !!document.querySelector('[data-unsaved="true"], [data-uploading="true"]');

  const show = (pull, animate) => {
    ptr.classList.toggle('is-animating', animate);
    main.classList.toggle('is-animating', animate);
    ptr.style.setProperty('--ptr-y', `${pull}px`);
    ptr.style.setProperty('--ptr-o', String(Math.min(1, pull / (THRESHOLD * 0.7))));
    icon.style.setProperty('--ptr-r', `${Math.min(1, pull / THRESHOLD) * 270}deg`);
    main.style.transform = pull > 0 ? `translateY(${pull}px)` : '';
  };
  const reset = () => {
    show(0, true);
    ptr.classList.remove('is-refreshing');
    refreshing = false;
    // Drop the transform once it has slid back, so nothing keeps a containing block.
    setTimeout(() => { if (!refreshing && !pulling) main.style.transform = ''; }, 220);
  };

  // Resolves once the in-place navigation that the Refresh link starts has finished.
  const finished = () => new Promise(resolve => {
    let seenBusy = false;
    const observer = new MutationObserver(() => {
      if (main.hasAttribute('aria-busy')) seenBusy = true;
      else if (seenBusy) { observer.disconnect(); resolve(); }
    });
    observer.observe(main, {attributes: true, attributeFilter: ['aria-busy']});
    setTimeout(() => { observer.disconnect(); resolve(); }, 15000);
  });

  const refresh = async () => {
    if (hasDraft()) {
      toast('Save or finish your changes before refreshing.', {tone: 'error'});
      reset();
      return;
    }
    refreshing = true;
    ptr.classList.add('is-refreshing');
    show(HOLD, true);
    const started = Date.now();
    const link = document.querySelector('.pagebar a[title="Refresh"]');
    if (!link) { location.reload(); return; }
    const done = finished();
    link.click();
    await done;
    setTimeout(reset, Math.max(0, MIN_SHOW - (Date.now() - started)));
  };

  document.addEventListener('touchstart', event => {
    tracking = false;
    if (event.touches.length !== 1 || refreshing || window.scrollY > 0 || blocked(event.target)) return;
    startX = event.touches[0].clientX;
    startY = event.touches[0].clientY;
    tracking = true;
    pulling = false;
    armed = false;
    distance = 0;
  }, {passive: true});

  document.addEventListener('touchmove', event => {
    if (!tracking) return;
    const dy = event.touches[0].clientY - startY;
    const dx = event.touches[0].clientX - startX;
    if (!pulling) {
      // Upward or sideways movement means a normal scroll or swipe.
      if (dy < -6 || Math.abs(dx) > Math.abs(dy) + 4 || window.scrollY > 0) { tracking = false; return; }
      if (dy < START) return;
      pulling = true;
    }
    if (event.cancelable) event.preventDefault();
    distance = Math.min(MAX_PULL, (dy - START) * RESIST);
    if (!armed && distance >= THRESHOLD) { armed = true; navigator.vibrate?.(8); }
    else if (armed && distance < THRESHOLD) armed = false;
    show(distance, false);
  }, {passive: false});

  const release = () => {
    if (!tracking) return;
    tracking = false;
    if (!pulling) return;
    pulling = false;
    if (armed) refresh(); else reset();
  };
  document.addEventListener('touchend', release);
  document.addEventListener('touchcancel', () => {
    if (tracking && pulling) { tracking = false; pulling = false; reset(); }
  });
}
