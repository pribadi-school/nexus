import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

class Element {
  events = {}; dataset = {}; disabled = false; textContent = ''; value = '';
  addEventListener(name, fn, {signal} = {}) {
    (this.events[name] ??= []).push({fn, signal});
  }
  async emit(name, event = {}) {
    for (const {fn, signal} of this.events[name] ?? []) if (!signal?.aborted) await fn(event);
  }
}
function makePanel() {
  const panel = new Element(); const details = new Element(); const form = new Element();
  const refresh = new Element(); const refreshButton = new Element(); const moveButton = new Element();
  const message = new Element(); const status = new Element(); const select = new Element();
  const expected = new Element(); expected.value = '/Students';
  const password = new Element(); const options = [];
  select.add = option => options.push(option);
  form.elements = {org_unit: select, expected_ou: expected, management_password: password};
  form.action = '/chromebooks/1/google/ou';
  form.reset = () => { password.value = ''; select.value = ''; };
  form.querySelector = () => moveButton;
  details.open = false; details.dataset.unitsUrl = '/chromebooks/1/google/ou';
  details.querySelector = selector => selector === '[data-google-move-form]' ? form : status;
  panel.dataset.googleRefresh = '/chromebooks/1/google/refresh';
  panel.hasAttribute = () => true;
  panel.querySelector = selector => ({'[data-google-refresh-form]': refresh, '[data-google-refresh-form] button': refreshButton,
    '[data-google-message]': message, '[data-google-ou]': details, '[data-google-ou][open]': details.open ? details : null}[selector] ?? null);
  panel.replaceWith = next => { root = next; };
  return Object.assign(panel, {details, form, refresh, refreshButton, moveButton, message, status, select, options, password});
}
let root = makePanel();
const requests = []; const timers = new Map(); const windowElement = new Element();
let timerId = 0;
let nextPanel = makePanel();
const document = {
  hidden: false,
  querySelector: () => root,
  createElement: () => ({content: {querySelector: () => nextPanel}}),
};
const source = readFileSync(new URL('../public/assets/js/chromebook-google.js', import.meta.url), 'utf8')
  .replace(/^import .*;\n/gm, '').replace('export function', 'function');
const context = {
  document, window: windowElement, URLSearchParams, AbortController,
  FormData: class { constructor(form) { this.password = form.elements.management_password.value; } },
  Option: class { constructor(text, value) { this.text = text; this.value = value; } },
  request(url, options) { return new Promise((resolve, reject) => requests.push({url, options, resolve, reject})); },
  setBusy(button, busy) { button.disabled = busy; },
  setTimeout(fn, delay) { timers.set(++timerId, {fn, delay}); return timerId; }, clearTimeout(id) { timers.delete(id); },
};
vm.runInNewContext(source + '\nvar cleanup = initChromebookGoogle();', context);
const settle = () => new Promise(resolve => setImmediate(resolve));
assert.equal(requests.length, 1, 'entry refreshes in the background');
assert.equal(requests[0].options.method, 'POST');
assert.equal(requests[0].options.body.get('force'), '0');
requests[0].resolve({html: '<saved report>'}); await settle();
assert.equal(root, nextPanel, 'response replaces only Google details');
assert.equal([...timers.values()][0].delay, 300000, 'polling waits five minutes');
root.details.open = true;
const loading = root.details.emit('toggle');
requests.at(-1).resolve({units: ['/', '/Staff', '/Students']}); await loading;
assert.equal(root.options.length, 3);
assert.equal(root.options[2].disabled, true, 'current OU cannot be selected as a target');
root.password.value = 'temporary-secret';
await root.form.emit('input');
assert.equal(root.dataset.unsaved, 'true');
let prevented = false;
await windowElement.emit('beforeunload', {preventDefault() { prevented = true; }});
assert.equal(prevented, true, 'draft OU changes warn before leaving');
const calls = requests.length;
await root.refresh.emit('submit', {preventDefault() {}});
assert.equal(requests.length, calls, 'manual refresh does not discard a draft');
assert.match(root.message.textContent, /Finish or close/);
const moving = root.form.emit('submit', {preventDefault() {}});
assert.equal(root.password.value, '', 'password cleared immediately after submission');
assert.equal(requests.at(-1).options.body.password, 'temporary-secret', 'only the intended request receives the password');
requests.at(-1).reject(new Error('Google denied access.')); await moving;
assert.equal(root.message.textContent, 'Google denied access.');
assert.equal(root.moveButton.disabled, false, 'failed move allows explicit retry');
root.details.open = false; await root.details.emit('toggle');
assert.equal(root.dataset.unsaved, undefined, 'closing OU form discards draft');
const refreshing = root.refresh.emit('submit', {preventDefault() {}});
// The event handler starts an async refresh without returning its promise.
await refreshing;
const lateRequest = requests.at(-1);
context.cleanup();
assert.equal(lateRequest.options.signal.aborted, true, 'fast navigation aborts page requests');
const oldRoot = root;
nextPanel = makePanel();
lateRequest.resolve({html: '<late response>'}); await settle();
assert.equal(root, oldRoot, 'response arriving after navigation never changes the page');
assert.equal(timers.size, 0, 'cleanup stops all polling');
console.log('Google UI lifecycle checks passed.');
