import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const handlers = {};
const inputs = [1, 2].map((id, index) => ({value: String(id), checked: index === 0, defaultChecked: index === 0, disabled: false, closest: () => form}));
const button = {disabled: false, textContent: ''}; const usage = {textContent: '1 playlist use'};
let originals = [{value: '1', remove() { originals = originals.filter(input => input !== this); }}];
const form = {
  dataset: {}, action: '/signage/content/7/screens', inert: false,
  matches: selector => selector.includes('[data-content-targets]'),
  closest: selector => selector === '[popover]' ? {matches: () => false} : {querySelector: () => usage},
  querySelectorAll: selector => selector.includes('original_screen_ids') ? originals : inputs,
  querySelector: () => button,
  append(input) { input.remove = function () { originals = originals.filter(item => item !== this); }; originals.push(input); },
};
const requests = []; const notices = [];
const document = {
  querySelectorAll: () => [form],
  querySelector: () => form.dataset.unsaved === 'true' ? form : null,
  addEventListener(name, fn) { handlers[name] = fn; },
  dispatchEvent() {}, createElement: () => ({}),
};
const source = readFileSync(new URL('../public/assets/js/signage-content-actions.js', import.meta.url), 'utf8')
  .replace(/^import .*;\n/gm, '').replace('export function', 'function');
vm.runInNewContext(source + '\nvar cleanup = initContentActions();', {
  document, window: {addEventListener(name, fn) { handlers[name] = fn; }}, AbortController, URL, Event,
  FormData: class { constructor() { this.selected = inputs.filter(input => input.checked).map(input => input.value); this.original = originals.map(input => input.value); } },
  fetch(url, options) { return new Promise((resolve, reject) => requests.push({url, options, resolve, reject})); },
  toast: text => notices.push(text), setBusy(target, busy) { target.disabled = busy; },
});
assert.equal(button.disabled, true, 'unchanged membership cannot be saved');
inputs[0].checked = false; handlers.change({target: inputs[0]});
assert.equal(button.disabled, false, 'unchecking the last TV enables Save');
assert.equal(button.textContent, 'Save 1 change');
let submission = handlers.submit({target: form, preventDefault() {}});
assert.deepEqual(requests[0].options.body.selected, []);
assert.deepEqual(requests[0].options.body.original, ['1'], 'removal submits its baseline even with no checked TVs');
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
console.log('Content checkbox add/remove and draft preservation checks passed.');
