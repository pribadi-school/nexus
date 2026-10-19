import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

class Element {
  constructor() { this.events = {}; this.dataset = {}; this.attrs = {}; this.value = ''; this.id = ''; this.files = []; this.maxLength = 160; }
  addEventListener(name, action) { (this.events[name] ??= []).push(action); }
  emit(name, event = {}) { return Promise.all((this.events[name] ?? []).map(action => action(event))); }
  setAttribute(key, value) { this.attrs[key] = value; }
  removeAttribute(key) { delete this.attrs[key]; }
  closest() { return group; }
  after(error) { errors.push(error); }
  remove() { errors.splice(errors.indexOf(this), 1); }
  focus() {}
  classList = {add() {}, remove() {}};
}
class Dialog extends Element {
  open = false;
  showModal() { this.open = true; }
  close() { this.open = false; this.emit('close'); }
  querySelectorAll() { return []; }
}
const group = new Element(); const errors = [];
const file = new Element(); const type = new Element(); type.value = 'video';
file.id = 'content-dialog-file';
const website = new Element(); const feedback = new Element(); const label = new Element();
const errorNotice = new Element(); const button = new Element(); const form = new Element(); const dialog = new Dialog();
form.dataset.uploadLimitBytes = String(500 * 1048576);
const fields = {file, source_url: website};
form.action = 'http://localhost/signage/content';
form.elements = {namedItem: key => fields[key]};
let valid = true;
form.reportValidity = () => valid;
form.reset = () => { file.files = []; type.value = 'video'; };
form.querySelector = selector => ({'[data-content-type]': type, '[name="file"]': file, '[data-upload-feedback]': feedback,
  '[name="source_url"]': website, '[data-file-label]': label, '[data-content-error]': errorNotice, '[type="submit"]': button,
  '[aria-invalid="true"]': file}[selector] ?? null);
form.querySelectorAll = selector => selector === '[data-upload-field-error]' ? [...errors] : selector === '[aria-invalid]' ? Object.values(fields) : [];
const handlers = {}; const requests = []; const added = []; const notices = [];
const document = {
  querySelector: selector => selector === '[data-content-dialog]' ? dialog : selector === '[data-signage-content-form]' ? form : null,
  addEventListener(name, action) { (handlers[name] ??= []).push(action); },
  dispatchEvent(event) { if (event.type === 'signage:content-added') added.push(event.detail); for (const action of handlers[event.type] ?? []) action(event); },
  createElement: () => new Element(),
};
const source = readFileSync(new URL('../public/assets/js/signage-content.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
vm.runInNewContext(source + '\ninitContentForms();', {
  HTMLElement: Element, HTMLDialogElement: Dialog, AbortController, URL,
  CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
  FormData: class { values = {}; set(key, value) { this.values[key] = value; } }, window: {addEventListener() {}}, document,
  csrfToken: () => 'current-session-token',
  setBusy(target, busy) { target.disabled = busy; }, toast: text => notices.push(text),
  fetch(url, options) { return new Promise(resolve => requests.push({url, options, resolve})); },
});
let prevented = false;
handlers.click[0]({target: {closest: () => ({})}, button: 0, preventDefault() { prevented = true; }});
assert.equal(prevented, true); assert.equal(dialog.open, true); assert.equal(requests.length, 0, 'opening the popup needs no page request');
valid = false;
await form.emit('submit', {preventDefault() {}});
assert.equal(requests.length, 0, 'invalid forms do not upload');
valid = true;
const oversized = [{name: 'too-large.mp4', size: 500 * 1048576 + 1}]; file.files = oversized;
await form.emit('submit', {preventDefault() {}});
assert.equal(requests.length, 0, 'a file over 500 MB is rejected before uploading');
assert.match(errorNotice.textContent, /500 MB/);
assert.equal(file.files, oversized, 'size validation preserves the selection');
const selection = [{name: 'clip.mov', size: 500 * 1048576}]; file.files = selection;
let submission = form.emit('submit', {preventDefault() {}});
assert.equal(requests.length, 1, 'a file at the 500 MB limit may upload');
assert.equal(requests.at(-1).options.headers['X-CSRF-Token'], 'current-session-token');
assert.equal(requests.at(-1).options.body.values._csrf, 'current-session-token', 'cached form token is replaced');
let cancelled = false;
await dialog.emit('cancel', {preventDefault() { cancelled = true; }});
assert.equal(cancelled, true, 'Escape cannot interrupt an active upload');
requests.at(-1).resolve({ok: false, status: 422, json: async () => ({error: 'Invalid file', errors: {file: 'Choose a supported file'}})});
await submission;
assert.equal(dialog.open, true); assert.equal(file.files, selection, 'validation preserves the selected file');
assert.equal(errorNotice.hidden, false); assert.equal(file.attrs['aria-invalid'], 'true');
submission = form.emit('submit', {preventDefault() {}});
requests.at(-1).resolve({ok: false, status: 413, json: async () => { throw new Error('Proxy HTML, not JSON'); }});
await submission;
assert.match(errorNotice.textContent, /upload limit/);
assert.equal(file.files, selection, 'a host size rejection preserves the selected file');
assert.equal(form.inert, false); assert.equal(button.disabled, false);
submission = form.emit('submit', {preventDefault() {}});
requests.at(-1).resolve({ok: true, status: 201, json: async () => ({id: 42, name: 'Clip', editor_html: '<li>Clip</li>'})});
await submission;
assert.equal(dialog.open, false); assert.equal(added[0].id, 42); assert.equal(notices.length, 1);
assert.equal(form.inert, false); assert.equal(button.disabled, false);
console.log('Passed popup upload, 500 MB boundaries, current CSRF token, server limit errors and file-preservation checks.');
