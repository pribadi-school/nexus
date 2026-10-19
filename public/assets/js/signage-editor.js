// Draft changes stay in the browser until Save changes succeeds.
export function initSignageEditor() {
const lifetime = new AbortController();
const {signal} = lifetime;
let cleanupDrag = () => {};
let menuSequence = 0;
const editor = document.querySelector('[data-screen-editor]');
document.querySelector('[href="#screen-settings"]')?.addEventListener('click', () => {
  document.getElementById('screen-settings').open = true;
});
// Coming back from a photo upload (#screen-photo) opens the settings it sits in.
if (location.hash.length > 1) {
  document.getElementById(location.hash.slice(1))?.closest('details')?.setAttribute('open', '');
}
if (editor) {
  const list = editor.querySelector('[data-playlist-drop]');
  const library = editor.querySelector('[data-screen-library]');
  const save = editor.querySelector('[data-playlist-save]');
  const saveButton = save.querySelector('[data-save-playlist]');
  const feedback = editor.querySelector('[data-editor-feedback]');
  const empty = editor.querySelector('[data-playlist-empty]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const rows = () => [...list.querySelectorAll('[data-editor-item]')];
  const serialize = () => rows().map(row => ({
    item_id: row.dataset.itemId ? Number(row.dataset.itemId) : null,
    content_id: Number(row.dataset.contentId),
    duration_seconds: Math.round(Number(row.querySelector('[data-item-duration]').value) * 60),
  }));
  const initial = JSON.stringify(serialize());
  let dirty = false;
  let saving = false;
  let dragging = null;
  const slot = document.createElement('li');
  slot.className = 'signage-drag-slot';
  slot.setAttribute('aria-hidden', 'true');

  // Keep the complete library visible; repeated content can have its own duration.
  const update = (message = '') => {
    const items = rows();
    dirty = JSON.stringify(serialize()) !== initial;
    editor.dataset.unsaved = String(dirty || saving);
    items.forEach((row, index) => {
      row.querySelector('[data-item-position]').textContent = index + 1;
      row.querySelector('[data-item-move="up"] button').disabled = index === 0;
      row.querySelector('[data-item-move="down"] button').disabled = index === items.length - 1;
    });
    empty.hidden = items.length > 0;
    saveButton.disabled = !dirty || saving;
    feedback.classList.remove('is-error');
    let seconds = 0;
    items.forEach(row => {
      seconds += Math.round(Number(row.querySelector('[data-item-duration]').value) * 60) || 0;
    });
    const minutes = new Intl.NumberFormat(undefined, {maximumFractionDigits: 2}).format(seconds / 60);
    const total = `${minutes} min`;
    editor.querySelector('[data-playlist-duration]').textContent = total;
    editor.querySelector('[data-playlist-count]').textContent = `${items.length} ${items.length === 1 ? 'item' : 'items'}${dirty ? ' · Unsaved changes' : ''}`;
    feedback.textContent = message && dirty ? message : '';
  };
  document.addEventListener('signage:duration', event => {
    editor.querySelectorAll('[data-video-src]').forEach(preview => {
      if (preview.dataset.videoSrc !== event.detail.url) return;
      preview.dataset.videoDuration = String(event.detail.seconds);
      preview.dataset.durationUnavailable = String(!event.detail.seconds);
      preview.closest('[data-editor-item]').querySelector('[data-video-minutes]').textContent = event.detail.seconds
        ? `Video · ${new Intl.NumberFormat(undefined, {maximumFractionDigits: 2}).format(event.detail.seconds / 60)} min` : 'Video';
    });
    update();
  }, {signal});
  const animateChange = change => {
    const before = new Map(rows().map(row => [row, row.getBoundingClientRect().top]));
    change();
    if (!reducedMotion.matches) {
      rows().forEach(row => {
        const distance = (before.get(row) ?? row.getBoundingClientRect().top) - row.getBoundingClientRect().top;
        if (distance) row.animate([{transform: `translateY(${distance}px)`}, {transform: 'translateY(0)'}], {duration: 180, easing: 'cubic-bezier(.16,1,.3,1)'});
      });
    }
  };
  const prepare = row => {
    const menu = row.querySelector('.signage-editor-item__menu');
    const menuButton = row.querySelector('[data-row-menu-button]');
    if (menu && menuButton) {
      menu.id = `playlist-actions-${Date.now()}-${++menuSequence}`;
      menuButton.setAttribute('popovertarget', menu.id);
    }
    row.draggable = false;
    row.dataset.draggable = 'true';
    row.querySelectorAll('a, img').forEach(node => { node.draggable = false; });
  };
  const add = (source, before = null) => {
    if (rows().length >= 1000) {
      feedback.textContent = 'A playlist can contain up to 1,000 items.';
      feedback.classList.add('is-error');
      return;
    }
    const row = source.cloneNode(true);
    row.dataset.itemId = '';
    row.classList.remove('is-dragging');
    row.querySelector('[data-library-add]').hidden = true;
    row.querySelector('[data-item-actions]').hidden = false;
    row.querySelector('[data-item-position]').hidden = false;
    const timing = row.querySelector('.signage-editor-item__timing');
    if (timing) timing.hidden = false;
    prepare(row);
    animateChange(() => list.insertBefore(row, before));
    document.dispatchEvent(new CustomEvent('signage:preview-added', {detail: row}));
    if (!reducedMotion.matches) row.animate([{opacity: .3, transform: 'scale(.98)'}, {opacity: 1, transform: 'scale(1)'}], {duration: 180});
    update(`Added ${row.dataset.contentName}. Save changes to update this TV.`);
  };
  editor.querySelectorAll('[data-editor-item]').forEach(prepare);
  document.addEventListener('signage:content-added', event => {
    if (!event.detail.editor_html) return;
    const template = document.createElement('template');
    template.innerHTML = event.detail.editor_html;
    const row = template.content.firstElementChild;
    prepare(row);
    library.prepend(row);
    editor.querySelector('[data-library-empty]')?.remove();
    document.dispatchEvent(new CustomEvent('signage:preview-added', {detail: row}));
    editor.querySelector('[data-library-search]').dispatchEvent(new Event('input'));
    update();
  }, {signal});
  saveButton.hidden = false;
  update();

  editor.addEventListener('submit', event => {
    const form = event.target;
    const row = form.closest('[data-editor-item]');
    const menu = form.closest('[popover]');
    if (menu?.matches(':popover-open')) menu.hidePopover();
    if (form.matches('[data-duration-form]')) {
      event.preventDefault();
      save.requestSubmit();
    } else if (form.matches('[data-library-add]')) {
      event.preventDefault();
      add(row);
    } else if (form.matches('[data-item-remove]')) {
      event.preventDefault();
      removeRow(row);
    } else if (form.matches('[data-item-move]')) {
      event.preventDefault();
      const items = rows();
      const position = items.indexOf(row);
      const target = items[position + (form.dataset.itemMove === 'up' ? -1 : 1)];
      if (!target) return;
      animateChange(() => list.insertBefore(row, form.dataset.itemMove === 'up' ? target : target.nextElementSibling));
      update(`Moved ${row.dataset.contentName}. Save changes to update this TV.`);
      row.querySelector('[data-row-menu-button]').focus();
    }
  });
  editor.addEventListener('input', event => {
    if (event.target.matches('[data-item-duration]')) update();
  });
  editor.querySelector('[data-library-search]').addEventListener('input', event => {
    const query = event.target.value.trim().toLocaleLowerCase();
    const available = [...library.querySelectorAll('[data-editor-item]')];
    available.forEach(row => { row.hidden = !row.dataset.contentName.toLocaleLowerCase().includes(query); });
    editor.querySelector('[data-library-no-results]').hidden = !available.length || available.some(row => !row.hidden);
  });

  // Pointer dragging keeps a lifted preview under the mouse. Touch users
  // retain normal scrolling and the accessible Add / Move buttons.
  const inDropArea = (x, y) => {
    const target = document.elementFromPoint(x, y);
    return target && (list.contains(target) || empty.contains(target));
  };
  // Dropping a playlist item back on the library removes it from the playlist.
  const removeArea = library.closest('[data-library-panel]') ?? library;
  const inRemoveArea = (x, y) => {
    const target = document.elementFromPoint(x, y);
    return !!target && removeArea.contains(target);
  };
  const removeRow = row => {
    const name = row.dataset.contentName;
    animateChange(() => row.remove());
    update(`Removed ${name}. Save changes to update this TV.`);
  };
  const stopDrag = () => {
    if (!dragging) return;
    dragging.row.classList.remove('is-dragging');
    dragging.ghost?.remove();
    if (dragging.row.hasPointerCapture(dragging.pointerId)) dragging.row.releasePointerCapture(dragging.pointerId);
    dragging = null;
    animateChange(() => slot.remove());
    list.classList.remove('is-drop-active');
    removeArea.classList.remove('is-drop-remove');
    document.body.classList.remove('is-signage-dragging');
  };
  cleanupDrag = stopDrag;
  // The whole card drags, the thumbnail link included: a press that moves
  // 6px drags, a plain click still opens the link. Only real controls
  // (buttons, fields) keep their own behaviour.
  let suppressClick = false;
  editor.addEventListener('pointerdown', event => {
    const row = event.target.closest('[data-editor-item]');
    if (!row || event.pointerType !== 'mouse' || event.button !== 0 || saving || event.target.closest('button, input, select, textarea')) return;
    event.preventDefault();
    dragging = {row, fromLibrary: library.contains(row), pointerId: event.pointerId, x: event.clientX, y: event.clientY, active: false};
  });
  // After a real drag, the release must not also follow the thumbnail link.
  editor.addEventListener('click', event => {
    if (!suppressClick) return;
    suppressClick = false;
    event.preventDefault();
    event.stopPropagation();
  }, true);
  window.addEventListener('pointermove', event => {
    if (!dragging || event.pointerId !== dragging.pointerId) return;
    if (!dragging.active) {
      if (Math.hypot(event.clientX - dragging.x, event.clientY - dragging.y) < 6) return;
      dragging.active = true;
      // Capture only once it is a drag, so a plain click reaches the link.
      dragging.row.setPointerCapture(event.pointerId);
      dragging.ghost = dragging.row.cloneNode(true);
      dragging.ghost.classList.add('signage-drag-ghost');
      dragging.ghost.setAttribute('aria-hidden', 'true');
      dragging.ghost.inert = true;
      document.body.append(dragging.ghost);
      dragging.row.classList.add('is-dragging');
      document.body.classList.add('is-signage-dragging');
    }
    event.preventDefault();
    dragging.ghost.style.left = `${Math.min(innerWidth - 330, Math.max(8, event.clientX - 60))}px`;
    dragging.ghost.style.top = `${event.clientY - 30}px`;
    const over = inDropArea(event.clientX, event.clientY);
    list.classList.toggle('is-drop-active', !!over);
    if (over) {
      const candidates = rows().filter(row => row !== dragging.row);
      const before = candidates.find(row => event.clientY < row.getBoundingClientRect().top + row.getBoundingClientRect().height / 2) ?? null;
      if (slot.parentElement !== list || slot.nextElementSibling !== before) animateChange(() => list.insertBefore(slot, before));
    } else slot.remove();
    removeArea.classList.toggle('is-drop-remove', !dragging.fromLibrary && inRemoveArea(event.clientX, event.clientY));
    if (event.clientY > innerHeight - 40) window.scrollBy(0, 16);
    else if (event.clientY < 60) window.scrollBy(0, -16);
  }, {signal});
  window.addEventListener('pointerup', event => {
    if (!dragging || event.pointerId !== dragging.pointerId) return;
    if (dragging.active) {
      suppressClick = true;
      // No click follows if the release lands outside the editor.
      setTimeout(() => { suppressClick = false; }, 0);
    }
    if (dragging.active && inDropArea(event.clientX, event.clientY)) {
      if (dragging.fromLibrary) add(dragging.row, slot.parentElement === list ? slot : null);
      else {
        animateChange(() => list.insertBefore(dragging.row, slot.parentElement === list ? slot : null));
        update();
      }
    } else if (dragging.active && !dragging.fromLibrary && inRemoveArea(event.clientX, event.clientY)) {
      const row = dragging.row;
      stopDrag();
      removeRow(row);
      return;
    }
    stopDrag();
  }, {signal});
  window.addEventListener('pointercancel', stopDrag, {signal});
  window.addEventListener('keydown', event => { if (event.key === 'Escape') stopDrag(); }, {signal});

  save.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !dirty) return;
    for (const input of list.querySelectorAll('[data-item-duration]')) {
      input.required = true;
      if (!input.reportValidity()) return;
    }
    save.querySelector('[name="playlist_items"]').value = JSON.stringify(serialize());
    saving = true;
    editor.dataset.unsaved = 'true';
    saveButton.disabled = true;
    saveButton.textContent = 'Saving…';
    editor.inert = true;
    try {
      const response = await fetch(save.action, {method: 'POST', body: new FormData(save), credentials: 'same-origin', headers: {'X-Requested-With': 'fetch', 'Accept': 'application/json'}});
      if (response.redirected && new URL(response.url).pathname === '/login') throw new Error('Your session expired. Sign in in another tab, then try saving again.');
      let result;
      try { result = await response.json(); } catch { throw new Error('The server could not save your changes. Check your connection and try again.'); }
      if (!response.ok) throw new Error(result.error || 'Could not save. Please try again.');
      dirty = false;
      // A fragment-only navigation would leave the old draft inert on screen.
      window.history.replaceState(null, '', result.redirect);
      window.location.reload();
    } catch (error) {
      feedback.textContent = error.message || 'Could not save. Please try again.';
      feedback.classList.add('is-error');
      saving = false;
      saveButton.disabled = false;
      saveButton.textContent = 'Save changes';
      editor.inert = false;
    }
  });
  window.addEventListener('beforeunload', event => {
    if (dirty) { event.preventDefault(); event.returnValue = ''; }
  }, {signal});
}

// Measure open popovers instead of estimating their height while they are hidden.
const positionMenu = menu => {
  const trigger = document.querySelector(`[popovertarget="${menu.id}"]`);
  if (!trigger) return;
  const viewport = window.visualViewport;
  const leftEdge = (viewport?.offsetLeft ?? 0) + 16;
  const topEdge = (viewport?.offsetTop ?? 0) + 16;
  const rightEdge = (viewport?.offsetLeft ?? 0) + (viewport?.width ?? innerWidth) - 16;
  let bottomEdge = (viewport?.offsetTop ?? 0) + (viewport?.height ?? innerHeight) - 16;
  const nav = document.querySelector('.bottom-nav')?.getBoundingClientRect();
  if (nav?.height && nav.top > topEdge) bottomEdge = Math.min(bottomEdge, nav.top - 16);
  const maxHeight = Math.max(1, bottomEdge - topEdge);
  const width = Math.min(280, rightEdge - leftEdge);
  menu.style.width = `${width}px`;
  menu.style.maxHeight = `${maxHeight}px`;
  const rect = trigger.getBoundingClientRect();
  const height = menu.getBoundingClientRect().height || maxHeight;
  const below = rect.bottom + 4;
  const above = rect.top - height - 4;
  const preferred = below + height <= bottomEdge ? below : above >= topEdge ? above : bottomEdge - height;
  menu.style.left = `${Math.max(leftEdge, Math.min(rect.right - width, rightEdge - width))}px`;
  menu.style.top = `${Math.max(topEdge, Math.min(preferred, bottomEdge - height))}px`;
};
for (const name of ['beforetoggle', 'toggle']) document.addEventListener(name, event => {
  if (event.newState === 'open' && event.target.matches('.signage-content-menu')) positionMenu(event.target);
}, {capture: true, signal});
const repositionMenus = () => document.querySelectorAll('.signage-content-menu:popover-open').forEach(positionMenu);
window.addEventListener('resize', repositionMenus, {signal});
window.addEventListener('scroll', repositionMenus, {signal});
window.visualViewport?.addEventListener('resize', repositionMenus, {signal});
window.visualViewport?.addEventListener('scroll', repositionMenus, {signal});
return () => { cleanupDrag(); lifetime.abort(); };
}
