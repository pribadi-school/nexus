// "Show older activity" on the dashboard feed: loads the next page in place,
// with skeleton rows while it waits. Without JavaScript the button is a
// plain link to /activity?before=ID.

import { request, setBusy } from './util.js';

const SKELETON_ROWS = 3;

function skeleton() {
  const wrap = document.createElement('div');
  wrap.className = 'feed__loading';
  wrap.setAttribute('aria-hidden', 'true');
  for (let i = 0; i < SKELETON_ROWS; i += 1) {
    wrap.insertAdjacentHTML('beforeend',
      '<div class="feed__loading-row">'
      + '<span class="skeleton skeleton--line skeleton--w40"></span>'
      + '<span class="skeleton skeleton--line skeleton--w80"></span>'
      + '<span class="skeleton skeleton--line skeleton--w60"></span>'
      + '</div>');
  }
  return wrap;
}

export function initFeed() {
  document.addEventListener('click', async (event) => {
    const link = event.target.closest('[data-feed-more]');
    if (!link) return;
    const card = link.closest('.card');
    const list = card?.querySelector('[data-feed]');
    if (!list) return;
    event.preventDefault();

    const more = link.closest('.feed__more');
    const loading = skeleton();
    more.before(loading);
    setBusy(link, true);

    try {
      const url = new URL(link.href, window.location.href);
      const data = await request(url.pathname + url.search);

      const template = document.createElement('template');
      template.innerHTML = data.html;
      // Same day as the last entry already shown? Drop the repeated heading.
      const firstDay = template.content.querySelector('.feed__day');
      const lastDay = [...list.querySelectorAll('.feed__day')].pop();
      if (firstDay && lastDay && firstDay.dataset.day === lastDay.dataset.day) firstDay.remove();
      list.append(template.content);

      if (data.next) {
        url.searchParams.set('before', data.next);
        link.href = url.pathname + url.search;
      } else {
        more.remove();
      }
    } catch (error) {
      const message = document.createElement('p');
      message.className = 'field__hint';
      message.setAttribute('role', 'alert');
      message.textContent = `${error.message} `;
      more.prepend(message);
    } finally {
      loading.remove();
      setBusy(link, false);
    }
  });
}
