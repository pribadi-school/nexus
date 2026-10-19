import { toast } from './toast.js';

/** Switch signed-in pages without rebuilding the sidebar, fonts or app shell. */
export function initNavigation({beforeSwap, afterSwap}) {
  if (!document.getElementById('main') || !window.fetch || !window.DOMParser) return;
  let pending = null;
  let ticket = 0;
  const pages = new Map();
  const cacheLifetime = 10000;
  const menuPages = new Set(['/', '/studytouch', '/cctv', '/chromebooks', '/signage', '/signage/content', '/signage/screens']);
  history.replaceState({...history.state, nexusNavigation: true}, '', location.href);

  const hasDraft = () => !!document.querySelector('[data-unsaved="true"], [data-uploading="true"]');
  const pageFor = (address, prefetch = false) => {
    const key = new URL(address, location.href); key.hash = '';
    const cached = pages.get(key.href);
    if (cached && !cached.controller.signal.aborted && Date.now() - cached.created < cacheLifetime) return cached;
    cached?.controller.abort();
    const record = {controller: new AbortController(), created: Date.now(), timedOut: false};
    // Bound the entire response, including a stalled body after headers arrive.
    const timeout = setTimeout(() => {
      record.timedOut = true;
      record.controller.abort();
    }, 12000);
    record.promise = fetch(key.href, {
      credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'text/html'},
      priority: prefetch ? 'low' : 'high', signal: record.controller.signal,
    }).then(async response => {
      if (!response.ok || !response.headers.get('Content-Type')?.includes('text/html')) throw new Error('Use regular navigation.');
      const html = await response.text();
      record.created = Date.now();
      return {html, url: response.url || key.href};
    }).catch(error => {
      if (pages.get(key.href) === record) pages.delete(key.href);
      if (record.timedOut) throw new DOMException('The page request timed out.', 'TimeoutError');
      throw error;
    }).finally(() => clearTimeout(timeout));
    pages.delete(key.href);
    pages.set(key.href, record);
    if (pages.size > 6) pages.delete(pages.keys().next().value);
    return record;
  };
  const navigate = async (address, {push = true, scroll = null} = {}) => {
    const page = pageFor(address);
    if (pending !== page.controller) pending?.abort();
    pending = page.controller;
    const activeTicket = ++ticket;
    if (push) history.replaceState({...history.state, scrollX: window.scrollX, scrollY: window.scrollY}, '', location.href);
    document.getElementById('main').setAttribute('aria-busy', 'true');
    document.dispatchEvent(new Event('nexus:navigation-start'));
    try {
      const response = await page.promise;
      const next = new DOMParser().parseFromString(response.html, 'text/html');
      if (activeTicket !== ticket) return;
      const nextMain = next.querySelector('#main .main__inner');
      const nextBar = next.querySelector('#main .pagebar');
      if (!nextMain || !nextBar) { location.assign(response.url || address); return; }
      // Edits made while a page was loading also keep the normal unload prompt.
      if (hasDraft()) { location.assign(response.url || address); return; }
      beforeSwap();
      document.querySelector('#main .main__inner').replaceWith(nextMain);
      document.querySelector('#main .pagebar').replaceWith(nextBar);
      document.title = next.title;
      const csrf = next.querySelector('meta[name="csrf-token"]');
      if (csrf) document.querySelector('meta[name="csrf-token"]').content = csrf.content;
      const activeLinks = new Set([...next.querySelectorAll('.nav__link[aria-current], .bottom-nav__link[aria-current]')].map(link => link.getAttribute('href')));
      document.querySelectorAll('.nav__link[href], .bottom-nav__link[href]').forEach(link => {
        if (activeLinks.has(link.getAttribute('href'))) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
      });
      const destination = new URL(response.url || address, location.href);
      destination.hash = new URL(address, location.href).hash;
      if (push) history.pushState({nexusNavigation: true}, '', destination);
      afterSwap();
      prepareTabs();
      if (scroll) window.scrollTo(scroll.x, scroll.y);
      else if (destination.hash) document.getElementById(decodeURIComponent(destination.hash.slice(1)))?.scrollIntoView();
      else window.scrollTo(0, 0);
      document.getElementById('main').focus({preventScroll: true});
    } catch (error) {
      if (activeTicket === ticket && error.name === 'TimeoutError') {
        toast('Nexus is taking too long to respond. Please try again.', {
          tone: 'error', action: {href: address, label: 'Retry'},
        });
      } else if (activeTicket === ticket && error.name !== 'AbortError') location.assign(address);
    } finally {
      if (activeTicket === ticket) {
        document.getElementById('main').removeAttribute('aria-busy');
        pending = null;
      }
    }
  };

  const prepareMenu = event => {
    const link = event.target.closest('a[href]');
    if (!link || hasDraft()) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || url.href === location.href || url.search || url.hash || !menuPages.has(url.pathname)) return;
    pageFor(url.href, true).promise.catch(() => {});
  };
  document.addEventListener('pointerover', prepareMenu);
  // Prepare the neighboring Signage tab before the user clicks it.
  const prepareTabs = () => {
    if (hasDraft()) return;
    document.querySelectorAll('.signage-tabs__link[href]').forEach(link => {
      const url = new URL(link.href, location.href);
      if (url.origin === location.origin && url.href !== location.href
          && ['/signage', '/signage/screens'].includes(url.pathname) && !url.search && !url.hash) {
        pageFor(url.href, true).promise.catch(() => {});
      }
    });
  };
  document.addEventListener('focusin', prepareMenu);
  // A mutation invalidates prefetched lists as well as recently visited pages.
  document.addEventListener('submit', event => {
    if ((event.target.method ?? '').toLowerCase() !== 'get') pages.clear();
  }, true);
  document.addEventListener('signage:content-added', () => { pages.clear(); prepareTabs(); });
  document.addEventListener('signage:content-changed', () => { pages.clear(); prepareTabs(); });

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
        || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.closest('[data-native-navigation]') || hasDraft()) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)
        || (url.hash && url.pathname === location.pathname && url.search === location.search)
        || /\/(?:export|download|photo|media)(?:\/|$)/.test(url.pathname) || url.pathname.startsWith('/signage/player/')
        || (url.pathname === '/signage/content/create' && document.querySelector('[data-content-dialog]'))) return;
    event.preventDefault();
    // The pagebar's Refresh link must always ask the server for fresh data.
    if (url.href === location.href) pages.delete(url.href);
    navigate(url.href, {push: url.href !== location.href});
  });
  window.addEventListener('popstate', event => {
    if (!event.state?.nexusNavigation || hasDraft()) { location.reload(); return; }
    navigate(location.href, {push: false, scroll: {x: event.state.scrollX ?? 0, y: event.state.scrollY ?? 0}});
  });
  prepareTabs();
}
