// Short messages at the bottom of the screen, for actions that do not reload
// the page (the status switch). One region per page: <div id="toasts">.

import { iconHtml } from './util.js';

const DURATION = 6000;

/**
 * @param {string} message
 * @param {{ tone?: 'default'|'error', action?: { href: string, label: string } }} [options]
 */
export function toast(message, options = {}) {
  const region = document.getElementById('toasts');
  if (!region) return;

  const el = document.createElement('div');
  el.className = `toast${options.tone === 'error' ? ' toast--error' : ''}`;

  const text = document.createElement('span');
  text.className = 'toast__text';
  text.textContent = message;
  el.append(text);

  if (options.action) {
    const link = document.createElement('a');
    link.className = 'toast__action';
    link.href = options.action.href;
    link.textContent = options.action.label;
    el.append(link);
  }

  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'icon-btn';
  close.setAttribute('aria-label', 'Dismiss');
  close.innerHTML = iconHtml('x', 'icon--sm');
  close.addEventListener('click', () => el.remove());
  el.append(close);

  // Keep at most three on screen.
  while (region.children.length >= 3) region.firstElementChild.remove();
  region.append(el);

  let timer = setTimeout(() => el.remove(), DURATION);
  // Pause while the pointer or focus is on it, so the action can be reached.
  el.addEventListener('mouseenter', () => clearTimeout(timer));
  el.addEventListener('focusin', () => clearTimeout(timer));
  el.addEventListener('mouseleave', () => { timer = setTimeout(() => el.remove(), DURATION); });
}
