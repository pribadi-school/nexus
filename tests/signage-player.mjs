// Exercise the actual browser player with deterministic network and media events.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

class Element {
  constructor(tag = '') { this.tagName = tag; this.children = []; this.events = {}; this.dataset = {}; this.hidden = false; }
  addEventListener(name, action) { (this.events[name] ??= []).push(action); }
  emit(name, event = {}) { for (const action of this.events[name] ?? []) action(event); }
  append(child) { this.children.push(child); }
  replaceChildren() { this.children = []; }
  setAttribute() {}
  removeAttribute() {}
  focus() {}
  classList = {add() {}, remove() {}, toggle() {}};
}
class Video extends Element { pause() {} load() {} async play() {} }
const selectors = Object.fromEntries(['content', 'message', 'heading', 'detail', 'start', 'tools', 'next', 'fullscreen', 'notice'].map(key => [`[data-player-${key}]`, new Element()]));
const root = new Element();
root.dataset.manifestUrl = '/manifest';
root.querySelector = selector => selectors[selector];
const timers = new Map(); let timerId = 0;
const setTimer = (action, milliseconds) => { timers.set(++timerId, {action, milliseconds}); return timerId; };
const clearTimer = id => timers.delete(id);
const manifest = {
  revision: 'test', screen: {enabled: true, muted: true, orientation: 'landscape', fit: 'contain'}, poll_seconds: 30,
  items: [
    {id: 1, name: 'Poster', type: 'image', url: '/poster.png', duration_seconds: 150},
    {id: 2, name: 'Video', type: 'video', url: '/clip.mov', duration_seconds: 120},
    {id: 3, name: 'Website', type: 'website', url: 'https://example.com', duration_seconds: 60},
  ],
};
vm.runInNewContext(readFileSync(new URL('../public/assets/js/signage-player.js', import.meta.url), 'utf8'), {
  document: {querySelector: () => root, createElement: tag => tag === 'video' ? new Video(tag) : new Element(tag), addEventListener() {}, hidden: false},
  window: {location: {href: 'http://localhost/player'}, addEventListener() {}},
  HTMLVideoElement: Video, URL, AbortController, Date,
  setTimeout: setTimer, clearTimeout: clearTimer, setInterval: setTimer, clearInterval: clearTimer,
  fetch: async () => ({ok: true, status: 200, json: async () => manifest}),
});
// Resolve both manifest response promises before inspecting playback.
await new Promise(resolve => setImmediate(resolve));
const content = selectors['[data-player-content]'];
const next = selectors['[data-player-next]'];
const image = content.children[0];
assert.equal(image.tagName, 'img');
assert.equal(image.src, '/poster.png');
assert.ok(![...timers.values()].some(timer => timer.milliseconds === 150000), 'display duration waits until the image loads');
image.emit('load');
assert.ok([...timers.values()].some(timer => timer.milliseconds === 150000), '2.5 minutes displays for 150 seconds');
next.emit('click');
assert.ok(content.children[0] instanceof Video, 'Next advances from image to MOV video');
assert.ok(![...timers.values()].some(timer => timer.milliseconds === 150000), 'advancing cancels the image timer');
image.emit('load');
assert.ok(![...timers.values()].some(timer => timer.milliseconds === 150000), 'a stale image load cannot change current playback');
const video = content.children[0];
assert.equal(video.loop, true, 'video repeats when the selected duration exceeds its length');
assert.ok(![...timers.values()].some(timer => timer.milliseconds === 120000), 'video display time starts when playback starts');
video.emit('playing');
const videoTimer = [...timers.values()].find(timer => timer.milliseconds === 120000);
assert.ok(videoTimer, 'the selected two-minute video duration is applied');
video.emit('playing');
assert.equal([...timers.values()].filter(timer => timer.milliseconds === 120000).length, 1, 'repeated playing events do not reset the video duration');
video.emit('ended');
assert.equal(content.children[0], video, 'the natural video ending does not advance before the selected duration');
videoTimer.action();
assert.equal(content.children[0].tagName, 'iframe');
video.emit('playing');
assert.equal(content.children[0].tagName, 'iframe', 'stale video events cannot replace the current item');
assert.ok([...timers.values()].some(timer => timer.milliseconds === 60000), 'website duration is preserved');
next.emit('click');
content.children[0].emit('error');
assert.equal(selectors['[data-player-heading]'].textContent, 'Content could not play');
assert.ok([...timers.values()].some(timer => timer.milliseconds === 3000), 'an unavailable image schedules recovery');
console.log('Passed browser player video/image duration, looping, stale-event and error-recovery checks.');
