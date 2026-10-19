import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

class Element {
  constructor(href = '') { this.href = href; this.attrs = {}; this.target = ''; this.replacedBy = null; }
  getAttribute(key) { return key === 'href' ? this.href : this.attrs[key] ?? null; }
  setAttribute(key, value) { this.attrs[key] = value; }
  removeAttribute(key) { delete this.attrs[key]; }
  hasAttribute(key) { return key in this.attrs; }
  closest(selector) { return selector === 'a[href]' ? this : null; }
  replaceWith(next) { this.replacedBy = next; }
  focus() {}
  classList = {add() {}, remove() {}};
}
const location = {
  href: 'http://localhost/studytouch', origin: 'http://localhost', pathname: '/studytouch', search: '',
  assign(url) { this.assigned = url; }, reload() { this.reloaded = true; },
};
const history = {
  state: null, pushed: [],
  replaceState(state) { this.state = state; },
  pushState(state, title, url) { this.state = state; this.pushed.push(String(url)); location.href = String(url); location.pathname = url.pathname; },
};
const handlers = {};
const main = new Element(); const inner = new Element(); const bar = new Element();
const nav = [new Element('/studytouch'), new Element('/signage')];
const csrf = {content: 'old'};
let draft = false; let hasDialog = false; let before = 0; let after = 0; let fetchCount = 0;
let now = 0;
let tabs = [];
const timers = new Map(); let timerId = 0;
const notices = [];
const requests = [];
const nextDocument = {
  title: 'Signage · Nexus',
  querySelector(selector) { return selector === '#main .main__inner' ? new Element() : selector === '#main .pagebar' ? new Element() : selector === 'meta[name="csrf-token"]' ? {content: 'new'} : null; },
  querySelectorAll() { return [new Element('/signage')]; },
};
const source = readFileSync(new URL('../public/assets/js/navigation.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
// Capture initialization without depending on private navigation functions.
const context = {
  URL, AbortController, DOMException, Event, Date: {now: () => now}, history, location, DOMParser: class { parseFromString() { return nextDocument; } },
  setTimeout(action) { timers.set(++timerId, action); return timerId; }, clearTimeout(id) { timers.delete(id); },
  toast(message, options) { notices.push({message, options}); },
  window: {fetch() {}, DOMParser: true, scrollX: 0, scrollY: 80, scrollTo() {}, addEventListener(name, action) { handlers[name] = action; }},
  document: {body: new Element(), getElementById: () => main,
    querySelector(selector) { if (selector.startsWith('[data-unsaved')) return draft ? {} : null; if (selector === '[data-content-dialog]') return hasDialog ? {} : null; return selector === '#main .main__inner' ? inner : selector === '#main .pagebar' ? bar : selector === 'meta[name="csrf-token"]' ? csrf : null; },
    querySelectorAll: selector => selector === '.signage-tabs__link[href]' ? tabs : nav, addEventListener(name, action) { handlers[name] = action; }, dispatchEvent() {}},
  fetch(url, options) {
    fetchCount++;
    return new Promise((resolve, reject) => {
      requests.push({url, options, resolve, reject, timerId});
      options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
    });
  },
  initOptions: {beforeSwap() { before++; }, afterSwap() { after++; }},
};
vm.runInNewContext(source + '\ninitNavigation(initOptions);', context);
const click = (href, extra = {}) => {
  let prevented = false;
  handlers.click({target: new Element(href), button: 0, preventDefault() { prevented = true; }, ...extra});
  return prevented;
};
const complete = (request, url = request.url) => request.resolve({ok: true, url, headers: {get: () => 'text/html'}, text: async () => '<html></html>'});
const settle = () => new Promise(resolve => setImmediate(resolve));
assert.ok(click('http://localhost/signage'));
complete(requests.at(-1)); await settle();
assert.equal(after, 1); assert.equal(before, 1);
assert.ok(inner.replacedBy && bar.replacedBy, 'only content and breadcrumb are swapped');
assert.equal(csrf.content, 'new');
assert.equal(nav[1].attrs['aria-current'], 'page');
assert.deepEqual(history.pushed, ['http://localhost/signage']);
draft = true;
assert.equal(click('http://localhost/cctv'), false, 'unsaved drafts keep normal unload handling');
draft = false; hasDialog = true;
assert.equal(click('http://localhost/signage/content/create'), false, 'Add content is left for the immediate popup');
assert.equal(click('https://example.com'), false);
assert.equal(click('http://localhost/cctv', {ctrlKey: true}), false);
hasDialog = false;
click('http://localhost/cctv'); const older = requests.at(-1);
click('http://localhost/chromebooks'); const latest = requests.at(-1);
assert.equal(older.options.signal.aborted, true);
complete(older); await settle(); assert.equal(after, 1, 'a stale page response is ignored');
complete(latest); await settle(); assert.equal(after, 2);
handlers.popstate({state: {nexusNavigation: true, scrollX: 0, scrollY: 40}});
complete(requests.at(-1)); await settle();
assert.equal(after, 3); assert.equal(history.pushed.length, 2, 'Back loads content without pushing another history entry');
click('http://localhost/cctv');
requests.at(-1).resolve({ok: false}); await settle();
assert.equal(location.assigned, 'http://localhost/cctv', 'failed requests fall back to ordinary navigation');
const requestCount = requests.length;
handlers.pointerover({target: new Element('http://localhost/studytouch')});
assert.equal(requests.length, requestCount + 1, 'menu intent begins loading before the click');
const prefetched = requests.at(-1);
click('http://localhost/studytouch');
assert.equal(requests.length, requestCount + 1, 'click reuses the in-flight menu request');
complete(prefetched); await settle();
click('http://localhost/signage'); await settle();
assert.equal(requests.length, requestCount + 1, 'recently visited pages switch without another request');
handlers.submit({target: {method: 'post'}});
click('http://localhost/studytouch');
assert.equal(requests.length, requestCount + 2, 'saving invalidates cached lists');
complete(requests.at(-1)); await settle();
now += 11000;
click('http://localhost/signage');
assert.equal(requests.length, requestCount + 3, 'expired pages are refreshed');
complete(requests.at(-1)); await settle();
tabs = [new Element('http://localhost/signage'), new Element('http://localhost/signage/screens')];
handlers['signage:content-added']();
assert.equal(requests.at(-1).url, 'http://localhost/signage/screens', 'the Screens tab is prepared before a click');
const screensRequest = requests.at(-1);
const beforeTabClick = requests.length;
click('http://localhost/signage/screens');
assert.equal(requests.length, beforeTabClick, 'opening Screens reuses its prepared page');
complete(screensRequest); await settle();
tabs = [];
for (const request of requests) complete(request);
await settle();
const afterBeforeTimeout = after;
const assignedBeforeTimeout = location.assigned;
click('http://localhost/chromebooks/123');
const stalled = requests.at(-1);
assert.equal(main.attrs['aria-busy'], 'true');
timers.get(stalled.timerId)(); await settle();
assert.equal(stalled.options.signal.aborted, true, 'a stalled request is cancelled');
assert.equal(after, afterBeforeTimeout, 'timeout keeps the existing page');
assert.equal(location.assigned, assignedBeforeTimeout, 'timeout does not start another potentially unbounded page load');
assert.equal(main.attrs['aria-busy'], undefined, 'timeout clears the loading state');
assert.equal(notices.length, 1, 'superseded requests do not show timeout messages');
assert.equal(notices[0].options.action.href, stalled.url, 'the message offers a retry of the destination');
click(stalled.url);
assert.notEqual(requests.at(-1), stalled, 'retry starts a fresh request');
complete(requests.at(-1)); await settle();
assert.equal(after, afterBeforeTimeout + 1);

// The deadline also covers a server that sends headers then stops sending HTML.
click('http://localhost/chromebooks/456');
const stalledBody = requests.at(-1);
stalledBody.resolve({ok: true, headers: {get: () => 'text/html'}, text: () => new Promise((resolve, reject) => {
  stalledBody.options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
})});
await settle();
timers.get(stalledBody.timerId)(); await settle();
assert.equal(main.attrs['aria-busy'], undefined);
assert.equal(notices.length, 2, 'a stalled response body also offers retry');

handlers.submit({target: {method: 'post'}});
handlers.pointerover({target: new Element('http://localhost/cctv')});
const stalledPrefetch = requests.at(-1);
timers.get(stalledPrefetch.timerId)(); await settle();
assert.equal(notices.length, 2, 'background prefetch timeouts stay silent');
click(stalledPrefetch.url);
assert.notEqual(requests.at(-1), stalledPrefetch);
complete(requests.at(-1)); await settle();
assert.equal(timers.size, 0, 'finished requests release their deadline timers');
console.log('Passed navigation, history, prefetch, cancellation, stalled-response and retry checks.');
