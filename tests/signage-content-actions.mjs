import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const handlers = {};
const inputs = [1, 2].map((id, index) => ({value: String(id), checked: index === 0, defaultChecked: index === 0, disabled: false, closest: () => form}));
const button = {disabled: false, textContent: ''}; const usage = {textContent: '1 playlist use'};
let deleting = false;
const card = {dataset: {sizeBytes: '100'}, querySelector: () => usage, remove() { this.removed = true; }};
let originals = [{value: '1', remove() { originals = originals.filter(input => input !== this); }}];
const form = {
  dataset: {}, action: '/signage/content/7/screens', inert: false,
  matches: selector => selector.includes(deleting ? '[data-content-delete]' : '[data-content-targets]'),
  closest: selector => selector === '[popover]' ? {matches: () => false} : card,
  querySelectorAll: selector => selector.includes('original_screen_ids') ? originals : inputs,
  querySelector: () => button,
  append(input) { input.remove = function () { originals = originals.filter(item => item !== this); }; originals.push(input); },
};
const requests = []; const notices = [];
const timers = new Map(); let timerId = 0;
const events = [];
const summary = {dataset: {count: '2', bytes: '200'}};
const document = {
  querySelectorAll: () => [form],
  querySelector: selector => selector === '[data-content-summary]' ? summary : selector === '[data-content-grid]' ? {children: [card]} : form.dataset.unsaved === 'true' ? form : null,
  addEventListener(name, fn) { handlers[name] = fn; },
  dispatchEvent(event) { events.push(event.type); }, createElement: () => ({}),
};
const source = readFileSync(new URL('../public/assets/js/signage-content-actions.js', import.meta.url), 'utf8')
  .replace(/^import .*;\n/gm, '').replace('export function', 'function');
const context = {
  document, window: {addEventListener(name, fn) { handlers[name] = fn; }}, AbortController, URL, Event, TypeError,
  setTimeout(action) { timers.set(++timerId, action); return timerId; }, clearTimeout(id) { timers.delete(id); },
  FormData: class {
    constructor() { this.selected = inputs.filter(input => input.checked).map(input => input.value); this.original = originals.map(input => input.value); }
    set(key, value) { this[key] = value; }
  },
  fetch(url, options) { return new Promise((resolve, reject) => {
    requests.push({url, options, resolve, reject, timerId});
    options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
  }); },
  csrfToken: () => 'current-token', toast: text => notices.push(text), setBusy(target, busy) { target.disabled = busy; },
};
vm.runInNewContext(source + '\nvar cleanup = initContentActions();', context);
assert.equal(button.disabled, true, 'unchanged membership cannot be saved');
inputs[0].checked = false; handlers.change({target: inputs[0]});
assert.equal(button.disabled, false, 'unchecking the last TV enables Save');
assert.equal(button.textContent, 'Save 1 change');
let submission = handlers.submit({target: form, preventDefault() {}});
assert.deepEqual(requests[0].options.body.selected, []);
assert.deepEqual(requests[0].options.body.original, ['1'], 'removal submits its baseline even with no checked TVs');
assert.equal(requests[0].options.body._csrf, 'current-token');
assert.equal(requests[0].options.headers['X-CSRF-Token'], 'current-token');
requests[0].resolve({ok: true, json: async () => ({screen_ids: [], used_count: 0, message: 'Removed from 1 TV.'})});
await submission;
assert.equal(usage.textContent, '0 playlist uses');
assert.equal(originals.length, 0);
assert.equal(inputs[0].disabled, false, 'saved TV stays editable');
assert.equal(button.disabled, true, 'successful save resets dirty state');
inputs[1].checked = true; handlers.change({target: inputs[1]});
submission = handlers.submit({target: form, preventDefault() {}});
requests[1].resolve({ok: true, json: async () => ({screen_ids: [2], used_count: 1, message: 'Added to 1 TV.'})});
await submission;
assert.equal(originals[0].value, '2');
assert.equal(inputs[1].defaultChecked, true, 'new baseline tracks additions for the next removal');
assert.equal(inputs[1].disabled, false);
inputs[1].checked = false; inputs[0].checked = true; handlers.change({target: inputs[0]});
assert.equal(button.textContent, 'Save 2 changes');
let warned = false; handlers.beforeunload({preventDefault() { warned = true; }});
assert.equal(warned, true);
submission = handlers.submit({target: form, preventDefault() {}});
requests[2].resolve({ok: false, json: async () => ({error: 'A selected TV no longer exists.'})});
await submission;
assert.equal(originals[0].value, '2', 'failed save preserves original membership');
assert.equal(inputs[0].checked, true, 'failed save preserves requested changes');
assert.equal(inputs[1].checked, false);
assert.equal(form.inert, false); assert.equal(button.disabled, false);
assert.match(notices.at(-1), /no longer exists/);
submission = handlers.submit({target: form, preventDefault() {}});
requests.at(-1).reject(new TypeError('Failed to fetch')); await submission;
assert.match(notices.at(-1), /Connection interrupted/); assert.doesNotMatch(notices.at(-1), /Failed to fetch/);
assert.equal(form.inert, false); assert.equal(button.disabled, false);
assert.equal(inputs[0].checked, true); assert.equal(inputs[1].defaultChecked, true, 'connection loss preserves the draft and baseline');

submission = handlers.submit({target: form, preventDefault() {}});
const stalled = requests.at(-1), countBefore = requests.length;
timers.get(stalled.timerId)(); await submission;
assert.match(notices.at(-1), /too long.*check whether the change was saved/);
assert.equal(stalled.options.signal.aborted, true); assert.equal(form.inert, false); assert.equal(button.disabled, false);
assert.equal(requests.length, countBefore, 'ambiguous writes are never retried automatically');

submission = handlers.submit({target: form, preventDefault() {}});
const stalledBody = requests.at(-1);
stalledBody.resolve({ok: true, json: () => new Promise((resolve, reject) => {
  stalledBody.options.signal.addEventListener('abort', () => reject(new DOMException('Aborted','AbortError')));
})});
await new Promise(resolve => setImmediate(resolve));
timers.get(stalledBody.timerId)(); await submission;
assert.match(notices.at(-1), /too long/); assert.equal(form.inert, false, 'deadline covers a stalled response body');

deleting = true;
submission = handlers.submit({target: form, preventDefault() {}});
const stalledDelete = requests.at(-1);
timers.get(stalledDelete.timerId)(); await submission;
assert.equal(card.removed, undefined); assert.equal(button.disabled, false, 'a timed-out delete leaves the card and controls available');
submission = handlers.submit({target: form, preventDefault() {}});
requests.at(-1).resolve({ok: true, json: async () => ({})}); await submission;
assert.equal(card.removed, undefined, 'incomplete acknowledgements never remove cards');
assert.match(notices.at(-1), /did not confirm/); assert.equal(button.disabled, false);
submission = handlers.submit({target: form, preventDefault() {}});
requests.at(-1).resolve({ok: true, json: async () => ({message:'Content deleted.'})}); await submission;
assert.equal(card.removed, true); assert.equal(summary.dataset.count, '1');

const beforeCleanup = notices.length;
submission = handlers.submit({target: form, preventDefault() {}});
context.cleanup(); await submission;
assert.equal(requests.at(-1).options.signal.aborted, true); assert.equal(notices.length, beforeCleanup, 'navigation cancellation stays silent');
assert.equal(timers.size, 0, 'every exit clears the deadline');
assert.equal(events.filter(name => name === 'signage:action-start').length, events.filter(name => name === 'signage:action-finished').length, 'preview transfers always resume after an action');
console.log('Content add/remove/delete, network loss, response deadlines, cancellation and draft preservation checks passed.');
