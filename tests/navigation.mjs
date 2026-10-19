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
const requests = [];
const nextDocument = {
  title: 'Signage · Nexus',
  querySelector(selector) { return selector === '#main .main__inner' ? new Element() : selector === '#main .pagebar' ? new Element() : selector === 'meta[name="csrf-token"]' ? {content: 'new'} : null; },
  querySelectorAll() { return [new Element('/signage')]; },
};
const source = readFileSync(new URL('../public/assets/js/navigation.js', import.meta.url), 'utf8').replace('export function', 'function');
// Capture initialization without depending on private navigation functions.
const context = {
  URL, AbortController, Event, Date: {now: () => now}, history, location, DOMParser: class { parseFromString() { return nextDocument; } },
  window: {fetch() {}, DOMParser: true, scrollX: 0, scrollY: 80, scrollTo() {}, addEventListener(name, action) { handlers[name] = action; }},
  document: {body: new Element(), getElementById: () => main,
    querySelector(selector) { if (selector.startsWith('[data-unsaved')) return draft ? {} : null; if (selector === '[data-content-dialog]') return hasDialog ? {} : null; return selector === '#main .main__inner' ? inner : selector === '#main .pagebar' ? bar : selector === 'meta[name="csrf-token"]' ? csrf : null; },
    querySelectorAll: () => nav, addEventListener(name, action) { handlers[name] = action; }, dispatchEvent() {}},
  fetch(url, options) { fetchCount++; return new Promise(resolve => requests.push({url, options, resolve})); },
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
console.log('Passed menu navigation, history, stale-request, popup and draft-protection checks.');
