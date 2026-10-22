// Bell pages: a live school clock with the next bell (like an alarm-clock
// app), and in-browser listening for sounds. The real bells are rung by the
// Windows bell PC, never by this page.

const ZONE = 'Asia/Jakarta';
const DAY = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

function schoolNow() {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: ZONE, weekday: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date());
  const get = (type) => parts.find((p) => p.type === type)?.value ?? '0';
  const h = Number(get('hour'));
  const m = Number(get('minute'));
  const s = Number(get('second'));
  return { day: DAY[get('weekday')] ?? 1, seconds: h * 3600 + m * 60 + s, text: `${get('hour')}:${get('minute')}:${get('second')}` };
}

function secondsOf(time) {
  const [h, m, s] = time.split(':').map(Number);
  return h * 3600 + m * 60 + (s || 0);
}

function span(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) return `${h} h ${m} min`;
  if (m > 0) return `${m} min ${s} s`;
  return `${s} s`;
}

/** The next enabled bell today, else the first one on the next day it rings. */
function nextBell(rows, now) {
  let best = null;
  for (const row of rows) {
    if (row.dataset.enabled !== '1') continue;
    const days = [...row.dataset.days].map(Number);
    const at = secondsOf(row.dataset.time);
    for (let ahead = 0; ahead < 8; ahead += 1) {
      const day = ((now.day - 1 + ahead) % 7) + 1;
      if (!days.includes(day)) continue;
      const wait = ahead * 86400 + at - now.seconds;
      if (wait <= 0) continue; // today's time has passed: look at the next day it rings
      if (best === null || wait < best.wait) best = { row, wait, ahead };
      break;
    }
  }
  return best;
}

function initClock() {
  const panel = document.querySelector('[data-bell-panel]');
  const clock = panel?.querySelector('[data-bell-clock]');
  if (!panel || !clock) return () => {};
  const next = panel.querySelector('[data-bell-next]');
  const rows = [...panel.querySelectorAll('[data-bell-row]')];

  const tick = () => {
    const now = schoolNow();
    clock.textContent = now.text;
    const found = nextBell(rows, now);
    rows.forEach((row) => row.classList.toggle('is-next', found?.row === row));
    if (!next) return;
    if (rows.length === 0) next.textContent = 'This profile is not in use, so it does not ring.';
    else if (!found) next.textContent = 'Next bell: none';
    else next.textContent = `Next bell: ${found.row.dataset.time.slice(0, 8)}${found.row.dataset.label ? ` · ${found.row.dataset.label}` : ''} · in ${span(found.wait)}`;
  };
  tick();
  const timer = setInterval(tick, 1000);
  return () => clearInterval(timer);
}

function initPreview() {
  let audio = null;
  let playing = null;

  const stop = () => {
    if (audio) { audio.pause(); audio = null; }
    playing?.removeAttribute('aria-pressed');
    playing = null;
  };

  const play = (button, src) => {
    const same = playing === button;
    stop();
    if (same || !src) return;
    audio = new Audio(src);
    playing = button;
    button.setAttribute('aria-pressed', 'true');
    audio.addEventListener('ended', stop);
    audio.addEventListener('error', stop);
    audio.play().catch(stop);
  };

  const onClick = (event) => {
    const direct = event.target.closest('[data-bell-preview]');
    if (direct) return play(direct, direct.dataset.bellPreview);
    const selected = event.target.closest('[data-bell-preview-selected]');
    if (selected) {
      const select = selected.closest('form')?.querySelector('[data-bell-sound-select]');
      play(selected, select?.selectedOptions[0]?.dataset.src);
    }
  };
  document.addEventListener('click', onClick);
  return () => { document.removeEventListener('click', onClick); stop(); };
}

export function initBell() {
  const stopClock = initClock();
  const stopPreview = initPreview();
  return () => { stopClock(); stopPreview(); };
}
