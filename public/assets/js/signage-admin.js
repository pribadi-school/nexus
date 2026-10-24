import { initContentForms } from './signage-content.js?v=20261024-library-url';
import { initContentActions } from './signage-content-actions.js?v=20261020-actions';
import { toast } from './toast.js';
import { request } from './util.js';

// Keep previews across in-place page switches; logout/full reload clears them.
// Media URLs include a file revision, so replacing a file cannot reuse its frame.
const thumbnailCache = new Map();
const thumbnailCacheLimit = 40;

export function initSignageAdmin() {
const lifetime = new AbortController();
const {signal} = lifetime;
let disposed = false;
let thumbnailObserver = null;
const pendingThumbnails = new Set();
// Decode real frames in the browser: shared hosting does not need FFmpeg.
// Limit preview traffic so the local PHP server stays available for navigation.
const thumbnailQueue = [];
let loadingThumbnails = 0;
let thumbnailStartTimer = null;

function thumbnailDuration(seconds) {
  const total = Math.floor(seconds);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const remainder = String(total % 60).padStart(2, '0');
  return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${remainder}` : `${minutes}:${remainder}`;
}

// Keep the decoded frame on the server, so later visits show a small image
// instead of streaming the whole video through the shared hosting account.
function savePoster(source, frame, duration, width, height) {
  const url = new URL(source, window.location.href);
  const id = url.pathname.match(/\/signage\/preview\/media\/(\d+)$/)?.[1];
  if (!id || !Number.isFinite(duration) || duration <= 0) return;
  const body = new FormData();
  body.set('v', url.searchParams.get('v') ?? '');
  body.set('image', frame);
  body.set('duration', String(duration));
  body.set('width', String(width));
  body.set('height', String(height));
  request(`/signage/preview/poster/${id}`, {method: 'POST', body}).catch(() => {});
}

function loadThumbnail(card) {
  const source = card.dataset.videoSrc;
  const showMetadata = (duration, width, height) => {
    if (Number.isFinite(duration) && duration > 0) {
      const badge = card.querySelector('[data-thumbnail-duration]');
      badge.textContent = thumbnailDuration(duration);
      badge.hidden = false;
      card.dataset.videoDuration = String(duration);
      document.dispatchEvent(new CustomEvent('signage:duration', {detail: {url: source, seconds: duration}}));
    }
    if (width && height) {
      card.querySelector('[data-thumbnail-orientation]').textContent = height > width ? ' · Portrait' : ' · Landscape';
    }
  };
  const showImage = frame => {
    const image = card.querySelector('[data-thumbnail-image]');
    image.src = frame;
    image.hidden = false;
    card.querySelector('[data-thumbnail-fallback]').hidden = true;
    card.dataset.thumbnailState = 'ready';
  };
  const cached = thumbnailCache.get(source);
  if (cached) {
    thumbnailCache.delete(source);
    thumbnailCache.set(source, cached);
    showMetadata(cached.duration, cached.width, cached.height);
    showImage(cached.frame);
    return Promise.resolve();
  }
  return new Promise((resolve) => {
    const video = document.createElement('video');
    const message = card.querySelector('[data-thumbnail-message]');
    let completed = false;
    let target = 0;
    const finish = (success, deferred = false) => {
      if (completed) return;
      completed = true;
      pendingThumbnails.delete(finish);
      clearTimeout(timeout);
      video.onloadedmetadata = video.onseeked = video.onloadeddata = video.onerror = null;
      video.removeAttribute('src');
      video.load();
      card.dataset.thumbnailState = success ? 'ready' : deferred ? 'pending' : 'unavailable';
      if (deferred && !disposed) thumbnailQueue.unshift(card);
      if (!success && !deferred) message.textContent = 'Preview unavailable';
      if (!disposed && !deferred && !Number(card.dataset.videoDuration)) {
        document.dispatchEvent(new CustomEvent('signage:duration', {detail: {url: card.dataset.videoSrc, seconds: null}}));
      }
      resolve();
    };
    pendingThumbnails.add(finish);
    const timeout = setTimeout(() => finish(false), 20000);
    const capture = () => {
      if (completed || video.seeking || video.readyState < 2 || !video.videoWidth || !video.videoHeight) return;
      try {
        const scale = Math.min(640 / video.videoWidth, 360 / video.videoHeight, 1);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const frame = canvas.toDataURL('image/jpeg', 0.82);
        thumbnailCache.set(source, {frame, duration: video.duration, width: video.videoWidth, height: video.videoHeight});
        if (thumbnailCache.size > thumbnailCacheLimit) thumbnailCache.delete(thumbnailCache.keys().next().value);
        showImage(frame);
        savePoster(source, frame, video.duration, video.videoWidth, video.videoHeight);
        finish(true);
      } catch {
        finish(false);
      }
    };
    video.muted = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      showMetadata(video.duration, video.videoWidth, video.videoHeight);
      if (Number.isFinite(video.duration) && video.duration > 0) {
        target = Math.min(1, video.duration / 10);
      }
      if (target > 0) video.currentTime = target;
      else capture();
    };
    video.onseeked = capture;
    video.onloadeddata = capture;
    video.onerror = () => finish(false);
    video.src = card.dataset.videoSrc;
  });
}

function processThumbnails() {
  if (document.querySelector('[data-uploading="true"], [data-saving="true"]')) return;
  while (!disposed && loadingThumbnails < 1 && thumbnailQueue.length) {
    const card = thumbnailQueue.shift();
    if (!card.isConnected) continue;
    loadingThumbnails += 1;
    loadThumbnail(card).finally(() => {
      loadingThumbnails -= 1;
      processThumbnails();
    });
  }
}

function scheduleThumbnails() {
  clearTimeout(thumbnailStartTimer);
  thumbnailStartTimer = setTimeout(processThumbnails, 180);
}
document.addEventListener('signage:uploads-finished', scheduleThumbnails, {signal});
document.addEventListener('signage:action-start', () => {
  clearTimeout(thumbnailStartTimer);
  // Give saves/deletes priority over optional video-preview transfers.
  pendingThumbnails.forEach(finish => finish(false, true));
}, {signal});
document.addEventListener('signage:action-finished', scheduleThumbnails, {signal});

const thumbnailCards = document.querySelectorAll('[data-signage-thumbnail]');
document.addEventListener('signage:preview-added', event => {
  const preview = event.detail.matches('[data-signage-thumbnail]') ? event.detail : event.detail.querySelector('[data-signage-thumbnail]');
  if (preview && preview.querySelector('[data-thumbnail-image]').hidden) {
    thumbnailQueue.push(preview);
    scheduleThumbnails();
  }
}, {signal});
if ('IntersectionObserver' in window) {
  const observer = thumbnailObserver = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      observer.unobserve(entry.target);
      thumbnailQueue.push(entry.target);
    }
    scheduleThumbnails();
  }, { rootMargin: '200px' });
  thumbnailCards.forEach((card) => observer.observe(card));
} else {
  thumbnailQueue.push(...thumbnailCards);
  scheduleThumbnails();
}

document.querySelectorAll('[data-signage-add-item]').forEach((form) => {
  const content = form.querySelector('[data-playlist-content]');
  const duration = form.querySelector('[data-website-duration]');
  const update = () => { duration.hidden = content.selectedOptions[0]?.dataset.type !== 'website'; };
  content.addEventListener('change', update);
  update();
});

const copy = document.querySelector('[data-copy-player]');
const address = document.querySelector('[data-player-address]');
if (copy && address && navigator.clipboard?.writeText) {
  copy.hidden = false;
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(address.value);
      toast('Player address copied.');
    } catch {
      address.focus();
      address.select();
      toast('Select and copy the player address.', { tone: 'error' });
    }
  });
}

const cleanupContent = initContentForms();
const cleanupActions = initContentActions();
const stopPreviews = () => {
  disposed = true;
  clearTimeout(thumbnailStartTimer);
  thumbnailObserver?.disconnect();
  thumbnailQueue.length = 0;
  pendingThumbnails.forEach(finish => finish(false));
};
document.addEventListener('nexus:navigation-start', stopPreviews, {signal});
return () => { stopPreviews(); lifetime.abort(); cleanupContent(); cleanupActions(); };
}
