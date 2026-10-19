const root = document.querySelector('[data-signage-player]');

if (root) {
  const content = root.querySelector('[data-player-content]');
  const message = root.querySelector('[data-player-message]');
  const heading = root.querySelector('[data-player-heading]');
  const detail = root.querySelector('[data-player-detail]');
  const startButton = root.querySelector('[data-player-start]');
  const tools = root.querySelector('[data-player-tools]');
  const nextButton = root.querySelector('[data-player-next]');
  const fullscreenButton = root.querySelector('[data-player-fullscreen]');
  const notice = root.querySelector('[data-player-notice]');

  let manifest = null;
  let currentIndex = -1;
  let currentMedia = null;
  let itemTimer = null;
  let pollTimer = null;
  let toolsTimer = null;
  let noticeTimer = null;
  let watchdogTimer = null;
  let failures = 0;
  let generation = 0;
  let lastError = '';
  let lastProgress = 0;
  let awaitingGesture = false;
  let polling = false;

  function showMessage(title, description, needsStart = false) {
    heading.textContent = title;
    detail.textContent = description;
    startButton.hidden = !needsStart;
    message.hidden = false;
    if (needsStart) startButton.focus({ preventScroll: true });
  }

  function flashNotice(text, duration = 6000) {
    clearTimeout(noticeTimer);
    notice.textContent = text;
    notice.hidden = false;
    noticeTimer = setTimeout(() => { notice.hidden = true; }, duration);
  }

  function showTools() {
    clearTimeout(toolsTimer);
    tools.classList.add('player-tools-visible');
    toolsTimer = setTimeout(() => tools.classList.remove('player-tools-visible'), 5000);
  }

  function clearItem() {
    generation += 1;
    clearTimeout(itemTimer);
    itemTimer = null;
    clearInterval(watchdogTimer);
    awaitingGesture = false;
    if (currentMedia instanceof HTMLVideoElement) {
      currentMedia.pause();
      currentMedia.removeAttribute('src');
      currentMedia.load();
    }
    currentMedia = null;
    content.replaceChildren();
  }

  function stop(title, description) {
    clearItem();
    currentIndex = -1;
    showMessage(title, description);
  }

  function failItem(reason, activeGeneration) {
    if (activeGeneration !== generation) return;
    lastError = reason.slice(0, 300);
    failures += 1;
    const total = manifest?.items?.length ?? 0;
    clearItem();
    showMessage('Content could not play', reason);
    const wait = failures >= total ? 30000 : 3000;
    itemTimer = setTimeout(() => {
      if (failures >= total) failures = 0;
      playItem(currentIndex + 1);
    }, wait);
  }

  async function startVideo(video, activeGeneration, userGesture = false) {
    try {
      await video.play();
      if (activeGeneration !== generation) return;
      awaitingGesture = false;
      message.hidden = true;
      lastProgress = Date.now();
    } catch (error) {
      if (activeGeneration !== generation) return;
      if (error.name === 'NotAllowedError') {
        // Sound autoplay is commonly blocked. Keep the playlist moving muted,
        // then let the operator enable the configured sound with the remote.
        if (!video.muted && !userGesture) {
          video.muted = true;
          flashNotice('Sound was blocked by the browser. Press OK or Enter to enable sound.');
          startVideo(video, activeGeneration);
          return;
        }
        awaitingGesture = true;
        lastError = 'Playback needs a remote or mouse interaction.';
        showMessage('Ready to play', 'Press OK, Enter, or Start playback to continue.', true);
      } else if (error.name !== 'AbortError') {
        failItem('The browser could not play this video. Check its encoding and connection.', activeGeneration);
      }
    }
  }

  function playItem(index) {
    clearItem();
    if (!manifest?.screen?.enabled || !manifest.items.length) return;
    currentIndex = ((index % manifest.items.length) + manifest.items.length) % manifest.items.length;
    const item = manifest.items[currentIndex];
    const activeGeneration = generation;
    message.hidden = true;
    if (item.type === 'image') {
      const image = document.createElement('img');
      image.alt = item.name;
      itemTimer = setTimeout(() => failItem('This image took too long to load.', activeGeneration), 30000);
      image.addEventListener('load', () => {
        if (activeGeneration !== generation) return;
        clearTimeout(itemTimer);
        failures = 0;
        lastError = '';
        itemTimer = setTimeout(() => playItem(currentIndex + 1), Math.max(5, item.duration_seconds) * 1000);
      });
      image.addEventListener('error', () => failItem('This image could not load.', activeGeneration));
      image.src = item.url;
      currentMedia = image;
      content.append(image);
      return;
    }
    if (item.type === 'website') {
      const frame = document.createElement('iframe');
      frame.title = item.name;
      frame.sandbox = 'allow-scripts allow-same-origin allow-forms allow-presentation';
      frame.referrerPolicy = 'no-referrer';
      frame.allow = 'autoplay; fullscreen';
      frame.allowFullscreen = true;
      frame.addEventListener('load', () => {
        if (activeGeneration !== generation) return;
        failures = 0;
        lastError = '';
      });
      frame.addEventListener('error', () => failItem('This website could not load. Check its address and connection.', activeGeneration));
      frame.src = item.url;
      currentMedia = frame;
      content.append(frame);
      // Cross-origin sites can refuse framing; browsers do not reliably expose
      // that failure. A duration always lets the next playlist item run.
      itemTimer = setTimeout(() => playItem(currentIndex + 1), Math.max(5, item.duration_seconds) * 1000);
      return;
    }

    const video = document.createElement('video');
    video.autoplay = true;
    video.loop = true;
    video.playsInline = true;
    video.muted = manifest.screen.muted;
    video.preload = 'auto';
    video.setAttribute('aria-label', item.name);
    video.addEventListener('error', () => failItem('This video is unavailable or uses an unsupported codec.', activeGeneration));
    video.addEventListener('timeupdate', () => { lastProgress = Date.now(); });
    video.addEventListener('playing', () => {
      if (activeGeneration !== generation) return;
      message.hidden = true;
      failures = 0;
      lastError = '';
      lastProgress = Date.now();
      if (itemTimer === null) {
        itemTimer = setTimeout(() => {
          if (activeGeneration === generation) playItem(currentIndex + 1);
        }, Math.max(5, item.duration_seconds ?? 30) * 1000);
      }
    });
    video.src = item.url;
    currentMedia = video;
    content.append(video);
    lastProgress = Date.now();
    watchdogTimer = setInterval(() => {
      if (activeGeneration !== generation || awaitingGesture || document.hidden) return;
      if (Date.now() - lastProgress > 30000) {
        failItem('Video playback stalled. Retrying the playlist shortly.', activeGeneration);
      }
    }, 3000);
    startVideo(video, activeGeneration);
  }

  function applyManifest(next) {
    if (!next || !Array.isArray(next.items) || !next.screen || typeof next.revision !== 'string') {
      throw new Error('Invalid playlist response.');
    }
    const changed = next.revision !== manifest?.revision;
    manifest = next;
    root.classList.toggle('player-portrait', next.screen.orientation === 'portrait');
    root.classList.toggle('player-fit-cover', next.screen.fit === 'cover');
    nextButton.disabled = !next.screen.enabled || next.items.length < 2;
    if (!next.screen.enabled) {
      stop('Screen paused', 'Resume this screen from Nexus to continue playback.');
    } else if (!next.items.length) {
      stop('Waiting for content', next.playlist
        ? 'Add content to this playlist in Nexus. This screen will update automatically.'
        : 'Assign a playlist to this screen in Nexus.');
    } else if (changed || currentIndex < 0) {
      failures = 0;
      playItem(0);
    }
  }

  async function poll() {
    if (polling) return;
    polling = true;
    clearTimeout(pollTimer);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const endpoint = new URL(root.dataset.manifestUrl, window.location.href);
      const item = manifest?.items?.[currentIndex];
      if (item) endpoint.searchParams.set('item', String(item.id));
      if (lastError) endpoint.searchParams.set('error', lastError);
      const response = await fetch(endpoint, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
        signal: controller.signal,
      });
      if ([401, 403, 404].includes(response.status)) {
        manifest = null;
        stop('Screen unavailable', 'This player link may have been replaced. Open the current link from Nexus.');
        return;
      }
      if (!response.ok) throw new Error('Playlist connection failed.');
      applyManifest(await response.json());
    } catch (error) {
      if (!manifest) {
        stop('Reconnecting to Nexus', 'Check the network connection. This screen will retry automatically.');
      } else {
        flashNotice('Nexus is unavailable. Continuing the loaded playlist and retrying the connection.');
      }
    } finally {
      clearTimeout(timeout);
      polling = false;
      pollTimer = setTimeout(poll, (manifest?.poll_seconds ?? 30) * 1000);
    }
  }

  function resume() {
    if (!(currentMedia instanceof HTMLVideoElement)) return;
    if (!manifest.screen.muted) currentMedia.muted = false;
    startVideo(currentMedia, generation, true);
  }

  async function toggleFullscreen() {
    try {
      if (document.fullscreenElement) {
        await document.exitFullscreen();
      } else if (root.requestFullscreen) {
        await root.requestFullscreen();
      } else {
        flashNotice('Full screen is unavailable here. Use the TV browser or signage app full-screen setting.');
      }
    } catch (error) {
      flashNotice('Full screen is unavailable here. Use the TV browser or signage app full-screen setting.');
    }
  }

  startButton.addEventListener('click', resume);
  nextButton.addEventListener('click', () => playItem(currentIndex + 1));
  fullscreenButton.addEventListener('click', toggleFullscreen);
  document.addEventListener('fullscreenchange', () => {
    fullscreenButton.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
  });
  document.addEventListener('pointermove', showTools, { passive: true });
  document.addEventListener('pointerdown', showTools, { passive: true });
  document.addEventListener('keydown', event => {
    showTools();
    if (event.key === 'f' || event.key === 'F') {
      event.preventDefault();
      toggleFullscreen();
    } else if (event.key === 'ArrowRight' && event.target === document.body) {
      event.preventDefault();
      playItem(currentIndex + 1);
    } else if (event.key === 'Enter' && event.target === document.body) {
      event.preventDefault();
      resume();
    }
  });
  window.addEventListener('online', poll);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      lastProgress = Date.now();
      poll();
    }
  });
  showTools();
  poll();
}
