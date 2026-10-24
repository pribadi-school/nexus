import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

class Element {
  constructor() { this.events = {}; this.dataset = {}; this.attrs = {}; this.files = []; this.children = []; this.value = ''; this.textContent = ''; this.hidden = false; }
  addEventListener(name, action) { (this.events[name] ??= []).push(action); }
  emit(name, event = {}) { return Promise.all((this.events[name] ?? []).map(action => action(event))); }
  setAttribute(key, value) { this.attrs[key] = value; }
  hasAttribute(key) { return key in this.attrs; }
  removeAttribute(key) { delete this.attrs[key]; }
  replaceChildren(...nodes) { this.children = nodes; }
  append(...nodes) { this.children.push(...nodes); }
  after(node) { this.afterNode = node; }
  closest() { return null; }
  contains() { return false; }
  classList = {add() {}, remove() {}, toggle() {}};
}
class Dialog extends Element {
  open = false;
  showModal() { this.open = true; }
  close() { this.open = false; this.emit('close'); }
  querySelectorAll() { return []; }
}
const source = ['signage-upload.js', 'signage-content.js'].map(file => readFileSync(new URL(`../public/assets/js/${file}`, import.meta.url), 'utf8')
  .replace(/^import .*;\r?\n/gm, '').replaceAll('export function', 'function')).join('\n');
function harness(inDialog = true) {
  const type = new Element(); type.value = 'video';
  const file = new Element(), form = new Element(), dialog = new Dialog(), feedback = new Element(), error = new Element();
  const queue = new Element(), controls = new Element(), meter = new Element(), percentage = new Element(), progress = new Element(), progressStatus = new Element();
  const button = new Element(); button.textContent = 'Add content';
  const nodes = {'[data-content-type]':type, '[name="file"]':file, '[data-upload-feedback]':feedback, '[name="source_url"]':new Element(),
    '[data-file-label]':new Element(), '[data-content-error]':error, '[type="submit"]':button, '[data-upload-controls]':controls,
    '[data-upload-drop]':new Element(), '[data-upload-queue]':queue, '[data-upload-progress]':progress, '[data-upload-meter]':meter,
    '[data-upload-percentage]':percentage, '[data-upload-progress-status]':progressStatus, '[data-upload-drop-hint]':new Element()};
  form.dataset.uploadLimitBytes = String(250 * 1048576); form.attrs['data-content-adding'] = ''; form.action = 'http://localhost/signage/content';
  form.querySelector = selector => nodes[selector] ?? null; form.querySelectorAll = () => [];
  form.elements = {namedItem: name => nodes[`[name="${name}"]`]};
  let valid = true;
  form.reportValidity = () => valid; form.reset = () => { file.files = []; type.value = 'video'; };
  const requests = [], added = [], notices = [], handlers = {};
  class XHR {
    upload = {}; headers = {}; status = 0;
    open(method, url) { this.method = method; this.url = url; }
    setRequestHeader(key, value) { this.headers[key] = value; }
    send(body) { this.body = {...body.values}; requests.push(this); }
    abort() { this.aborted = true; this.onabort(); }
    progress(ratio) { this.upload.onprogress({lengthComputable: ratio !== null, loaded: ratio, total: 1}); }
    uploaded() { this.upload.onload(); }
    reply(status = 201, data = {id: requests.length, name: 'Fixture'}) {
      this.status = status; this.responseText = typeof data === 'string' ? data : JSON.stringify(data); this.onload();
    }
  }
  const context = {
    HTMLElement:Element, HTMLDialogElement:Dialog, XMLHttpRequest:XHR, AbortController, DOMException, URL, Event,
    CustomEvent:class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    FormData:class { values = {_csrf:'old-token'}; set(key, value) { this.values[key] = value; } },
    window:{addEventListener() {}}, location:{assign(url) { this.assigned = url; }}, csrfToken:() => 'current-token',
    setBusy(target, busy) { target.disabled = busy; }, toast: message => notices.push(message),
    document:{querySelector: selector => selector === '[data-content-dialog]' ? (inDialog ? dialog : null) : selector === '[data-signage-content-form]' ? form : null,
      addEventListener(name, action) { (handlers[name] ??= []).push(action); },
      dispatchEvent(event) { if (event.type === 'signage:content-added') added.push(event.detail); (handlers[event.type] ?? []).forEach(action => action(event)); },
      createElement: () => new Element()},
  };
  vm.runInNewContext(source + '\nvar cleanup = initContentForms();', context);
  const choose = async files => { file.files = files; await file.emit('change'); };
  const submit = () => form.emit('submit', {preventDefault() {}});
  return {type, file, form, dialog, queue, controls, meter, percentage, progress, progressStatus, button, feedback, error, requests, added, notices, handlers, choose, submit,
    context, invalid: () => { valid = false; }, valid: () => { valid = true; }, cleanup: context.cleanup,
    state: index => queue.children[index].children[0].children[1].textContent,
    remove: index => queue.children[index].children[1].emit('click')};
}
const settle = () => new Promise(resolve => setImmediate(resolve));
const media = (name, size = 100, lastModified = 1) => ({name, size, lastModified});

const h = harness();
let prevented = false;
h.handlers.click[0]({target:{closest:() => ({})},button:0,preventDefault() { prevented = true; }});
assert.equal(prevented, true); assert.equal(h.dialog.open, true); assert.equal(h.requests.length, 0, 'popup opens without a request');
assert.equal(h.file.multiple, true);
await h.choose([media('first.mp4'), media('second.png')]);
h.invalid(); await h.submit(); assert.equal(h.requests.length, 0); h.valid();
const batch = h.submit();
assert.equal(h.requests.length, 1, 'files upload sequentially');
assert.equal(h.controls.disabled, true);
assert.equal(h.requests[0].body.type, 'video'); assert.equal(h.requests[0].body.file.name, 'first.mp4');
assert.equal(h.requests[0].headers['X-CSRF-Token'], 'current-token'); assert.equal(h.requests[0].body._csrf, 'current-token');
h.requests[0].progress(.5);
assert.equal(h.percentage.textContent, '25%', 'total percentage is weighted by file bytes');
assert.match(h.state(0), /^50%/);
h.requests[0].uploaded(); assert.match(h.state(0), /Saving/); assert.equal(h.dialog.open, true, 'sending bytes is not server success');
let cancelled = false; await h.dialog.emit('cancel', {preventDefault() { cancelled = true; }}); assert.equal(cancelled, true);
h.requests[0].reply(); await settle();
assert.equal(h.requests.length, 2); assert.equal(h.requests[1].body.type, 'image', 'mixed drops infer each content type');
assert.equal(h.requests[1].body.file.name, 'second.png'); assert.equal(h.added.length, 1, 'each successful file updates the library immediately');
h.requests[1].progress(.5); assert.equal(h.percentage.textContent, '75%');
h.requests[1].uploaded(); assert.equal(h.percentage.textContent, '99%', 'completion waits for server acknowledgement');
h.requests[1].reply(); await batch;
assert.equal(h.percentage.textContent, '100%'); assert.equal(h.added.length, 2); assert.equal(h.dialog.open, false); assert.equal(h.controls.disabled, false);

const retry = harness(); retry.dialog.showModal();
await retry.dialog.emit('drop', {dataTransfer:{types:['Files'],files:[media('one.mov'),media('two.jpg')]},preventDefault() {}});
const partial = retry.submit(); retry.requests[0].reply(); await settle();
retry.requests[1].reply(422, {error:'Invalid image',errors:{file:'Invalid image'}}); await partial;
assert.equal(retry.dialog.open, true); assert.equal(retry.queue.children.length, 2); assert.equal(retry.state(0), 'Added'); assert.equal(retry.state(1), 'Invalid image');
const again = retry.submit(); assert.equal(retry.requests.length, 3); assert.equal(retry.requests[2].body.file.name, 'two.jpg', 'retry never resends acknowledged successful files');
retry.requests[2].reply(); await again; assert.equal(retry.added.length, 2);

const limits = harness();
await limits.choose([media('oversized.mp4',250 * 1048576 + 1),media('bad.exe')]);
await limits.submit(); assert.equal(limits.requests.length, 0); assert.match(limits.state(0), /250 MB/); assert.match(limits.state(1), /MP4/);
await limits.remove(1); await limits.remove(0);
await limits.choose([media('exact.mp4',250 * 1048576)]);
const exact = limits.submit(); assert.equal(limits.requests.length, 1, 'the per-file size boundary is inclusive');
limits.requests[0].reply(413, '<html>proxy size limit</html>'); await exact;
assert.match(limits.error.textContent, /upload limit/); assert.equal(limits.queue.children.length, 1); assert.equal(limits.controls.disabled, false);

const auth = harness(); await auth.choose([media('one.mp4'),media('two.mp4')]);
const expired = auth.submit(); auth.requests[0].reply(419,{error:'Form expired'}); await expired;
assert.equal(auth.requests.length, 1, 'an expired session stops the rest of the batch');
const aborted = auth.submit(); auth.cleanup(); await aborted; assert.equal(auth.requests.at(-1).aborted, true);

const plain = harness(false); await plain.choose([media('one.png'), media('one.png')]);
assert.equal(plain.queue.children.length, 1, 'duplicate selections are not queued twice');
const direct = plain.submit(); plain.requests[0].progress(null); assert.equal(plain.percentage.textContent, 'Uploading…', 'unknown transfer length stays indeterminate');
plain.requests[0].reply(); await direct; assert.equal(plain.context.location.assigned, '/signage/content', 'regular Add page returns to the library');

const website = harness(); website.type.value = 'website';
const url = website.submit(); assert.equal(website.progress.hidden, true); website.requests[0].reply(); await url; assert.equal(website.added.length, 1);
const invalidUrl = harness(); invalidUrl.type.value = 'website';
const invalidWebsite = invalidUrl.submit(); invalidUrl.requests[0].reply(422,{error:'Check the highlighted fields.',errors:{source_url:'Use an HTTPS website address.'}}); await invalidWebsite;
const urlInput = invalidUrl.form.elements.namedItem('source_url');
assert.equal(urlInput.attrs['aria-invalid'], 'true'); assert.equal(urlInput.afterNode.textContent, 'Use an HTTPS website address.');
const unconfirmed = harness(); await unconfirmed.choose([media('one.png')]);
const unexpected = unconfirmed.submit(); unconfirmed.requests[0].reply(200,{}); await unexpected;
assert.equal(unconfirmed.added.length,0); assert.match(unconfirmed.error.textContent,/did not confirm/);
console.log('Passed multi-file picking/drop, mixed types, byte progress, size boundaries, partial retry, session/abort handling and standalone-page checks.');
