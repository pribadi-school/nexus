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
