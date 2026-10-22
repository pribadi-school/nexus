// Notes log form: picking "Problem" on a device that is running well ticks
// "Also mark as Has problem"; picking "Fix" on a device with a problem ticks
// "Also mark as Running well". A box the person ticked or unticked themselves
// is left alone.

export function initNoteForms() {
  document.querySelectorAll('form[data-note-form]').forEach((form) => {
    const box = form.querySelector('[data-set-status]');
    if (!box) return;
    const current = form.dataset.currentStatus;
    let touched = false;
    box.addEventListener('change', () => { touched = true; });

    form.addEventListener('change', (event) => {
      if (touched || event.target.name !== 'kind') return;
      const kind = event.target.value;
      box.checked = (kind === 'problem' && current === 'ok') || (kind === 'fix' && current === 'problem');
    });
  });
}

// Image on a note: tell the person right away when a picture is over the
// limit, instead of after a slow upload (the server checks again).
export function initNoteImages() {
  document.querySelectorAll('input[type="file"][data-max-bytes]').forEach((input) => {
    const message = input.closest('.field')?.querySelector('[data-image-error]');
    input.addEventListener('change', () => {
      const file = input.files?.[0];
      const tooBig = file && file.size > Number(input.dataset.maxBytes);
      if (tooBig) {
        input.value = '';
        if (message) {
          message.textContent = `That image is ${(file.size / 1048576).toFixed(1)} MB. Choose one of 2 MB or less.`;
          message.hidden = false;
        }
      } else if (message) {
        message.hidden = true;
      }
    });
  });
}
