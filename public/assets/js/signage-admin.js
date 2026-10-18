import { toast } from './toast.js';

// Decode real frames in the browser: shared hosting does not need FFmpeg.
// Only two visible previews load at a time, and their video decoders are freed.
const thumbnailQueue = [];
let loadingThumbnails = 0;

function thumbnailDuration(seconds) {
  const total = Math.floor(seconds);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const remainder = String(total % 60).padStart(2, '0');
  return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${remainder}` : `${minutes}:${remainder}`;
}

function loadThumbnail(card) {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    const image = card.querySelector('[data-thumbnail-image]');
    const fallback = card.querySelector('[data-thumbnail-fallback]');
    const message = card.querySelector('[data-thumbnail-message]');
    let completed = false;
    let target = 0;
    const finish = (success) => {
      if (completed) return;
      completed = true;
      clearTimeout(timeout);
      video.onloadedmetadata = video.onseeked = video.onloadeddata = video.onerror = null;
      video.removeAttribute('src');
      video.load();
      card.dataset.thumbnailState = success ? 'ready' : 'unavailable';
      if (!success) message.textContent = 'Preview unavailable';
      resolve();
    };
    const timeout = setTimeout(() => finish(false), 20000);
    const capture = () => {
      if (completed || video.seeking || video.readyState < 2 || !video.videoWidth || !video.videoHeight) return;
      try {
        const scale = Math.min(640 / video.videoWidth, 360 / video.videoHeight, 1);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        image.src = canvas.toDataURL('image/jpeg', 0.82);
        image.hidden = false;
        fallback.hidden = true;
        finish(true);
      } catch {
        finish(false);
      }
    };
    video.muted = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      if (Number.isFinite(video.duration) && video.duration > 0) {
        const badge = card.querySelector('[data-thumbnail-duration]');
        badge.textContent = thumbnailDuration(video.duration);
        badge.hidden = false;
        target = Math.min(1, video.duration / 10);
      }
      if (video.videoWidth && video.videoHeight) {
        card.querySelector('[data-thumbnail-orientation]').textContent = video.videoHeight > video.videoWidth ? ' · Portrait' : ' · Landscape';
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
  while (loadingThumbnails < 2 && thumbnailQueue.length) {
    const card = thumbnailQueue.shift();
    loadingThumbnails += 1;
    loadThumbnail(card).finally(() => {
      loadingThumbnails -= 1;
      processThumbnails();
    });
  }
}

const thumbnailCards = document.querySelectorAll('[data-signage-thumbnail]');
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      observer.unobserve(entry.target);
      thumbnailQueue.push(entry.target);
    }
    processThumbnails();
  }, { rootMargin: '200px' });
  thumbnailCards.forEach((card) => observer.observe(card));
} else {
  thumbnailQueue.push(...thumbnailCards);
  processThumbnails();
}

const contentForm = document.querySelector('[data-signage-content-form]');
if (contentForm) {
  const type = contentForm.querySelector('[data-content-type]');
  const updateType = () => {
    contentForm.querySelectorAll('[data-content-fields]').forEach((group) => {
      const active = group.dataset.contentFields === type.value;
      group.hidden = !active;
      group.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
    });
  };
  type.addEventListener('change', updateType);
  updateType();

  const file = contentForm.querySelector('[name="file"]');
  const name = contentForm.querySelector('[name="name"]');
  file?.addEventListener('change', () => {
    const selected = file.files[0];
    if (selected && !name.value.trim()) name.value = selected.name.replace(/\.mp4$/i, '').slice(0, name.maxLength);
  });
  contentForm.addEventListener('submit', () => {
    if (type.value === 'video' && file?.files.length) {
      contentForm.querySelector('[data-upload-feedback]').textContent = 'Uploading video. Keep this page open until the upload finishes.';
    }
  });
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
