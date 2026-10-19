import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const timers = new Map(); let timerId = 0;
const videos = [];
const listeners = new Map();
const durations = [];
let cards = [];
const makeCard = url => {
  const nodes = new Map();
  for (const name of ['image', 'fallback', 'message', 'duration', 'orientation']) nodes.set(`[data-thumbnail-${name}]`, {hidden: name === 'image' || name === 'duration', textContent: ''});
  return {dataset: {videoSrc: url}, querySelector: selector => nodes.get(selector)};
};
const context = {
  AbortController, Map, Set, Number, Math, Promise,
  CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
  setTimeout(action, delay) { timers.set(++timerId, {action, delay}); return timerId; }, clearTimeout(id) { timers.delete(id); },
  window: {}, navigator: {}, initContentForms: () => () => {}, initContentActions: () => () => {}, toast() {},
  document: {
    querySelectorAll: selector => selector === '[data-signage-thumbnail]' ? cards : [], querySelector: () => null,
    addEventListener(type, handler, {signal}) {
      if (!listeners.has(type)) listeners.set(type, new Set());
      listeners.get(type).add(handler);
      signal.addEventListener('abort', () => listeners.get(type).delete(handler));
    },
    dispatchEvent(event) { if (event.type === 'signage:duration') durations.push(event.detail); },
    createElement(tag) {
      if (tag === 'canvas') return {getContext: () => ({drawImage() {}}), toDataURL: () => 'data:image/jpeg;base64,frame'};
      assert.equal(tag, 'video');
      const video = {duration: 96, videoWidth: 1920, videoHeight: 1080, readyState: 2, seeking: false,
        removeAttribute() { this.src = ''; }, load() { this.released = true; }};
      videos.push(video); return video;
    },
  },
};
const source = readFileSync(new URL('../public/assets/js/signage-admin.js', import.meta.url), 'utf8')
  .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');
vm.createContext(context);
vm.runInContext(source, context);
const init = () => vm.runInContext('initSignageAdmin()', context);
const start = () => {
  for (const [id, timer] of [...timers]) if (timer.delay === 180) { timers.delete(id); timer.action(); }
};
const settle = () => new Promise(resolve => setImmediate(resolve));
const capture = video => { video.onloadedmetadata(); video.onseeked(); };

cards = [makeCard('/media/10?v=first'), makeCard('/media/10?v=first')];
let cleanup = init(); start();
assert.equal(videos.length, 1);
capture(videos[0]); await settle();
assert.equal(videos.length, 1, 'duplicate playlist/library entries share one video download');
for (const card of cards) {
  assert.equal(card.dataset.thumbnailState, 'ready');
  assert.equal(card.dataset.videoDuration, '96');
  assert.equal(card.querySelector('[data-thumbnail-duration]').textContent, '1:36');
  assert.equal(card.querySelector('[data-thumbnail-orientation]').textContent, ' · Landscape');
  assert.equal(card.querySelector('[data-thumbnail-image]').hidden, false);
}
assert.equal(durations.length, 2, 'cached frames still report duration to playlist editors');
assert.equal(videos[0].released, true);
cleanup();

cards = [makeCard('/media/10?v=first')];
cleanup = init(); start(); await settle();
assert.equal(videos.length, 1, 'revisiting Signage reuses the generated frame');
assert.equal(cards[0].dataset.thumbnailState, 'ready');
cleanup();

cards = [makeCard('/media/10?v=replaced')];
cleanup = init(); start();
assert.equal(videos.length, 2, 'replacing the file invalidates its cached preview');
videos[1].onerror(); await settle(); cleanup();
cleanup = init(); start();
assert.equal(videos.length, 3, 'failed previews can be retried');
for (const handler of listeners.get('nexus:navigation-start')) handler();
assert.equal(videos[2].released, true, 'navigation releases an unfinished media transfer');
cleanup();
cleanup = init(); start();
assert.equal(videos.length, 4, 'cancelled transfers do not poison the preview cache');
capture(videos[3]); await settle(); cleanup();
assert.equal(timers.size, 0);
console.log('Passed thumbnail reuse, metadata restoration, file replacement, failure retry and navigation cleanup checks.');
