// Confirmation before risky actions. Any form with data-confirm asks first,
// using the one <dialog id="confirm-dialog"> in the layout:
//
//   <form method="post" action="..." data-confirm="Delete this board?"
//         data-confirm-text="..." data-confirm-button="Delete" data-confirm-tone="danger">
//
// Without JavaScript the form simply submits.

export function initConfirm() {
  const dialog = document.getElementById('confirm-dialog');
  if (!(dialog instanceof HTMLDialogElement)) return;

  const title = dialog.querySelector('#confirm-title');
  const text = dialog.querySelector('#confirm-text');
  const ok = dialog.querySelector('[data-confirm-ok]');
  let pendingForm = null;
  let pendingSubmitter = null;

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (form.dataset.confirmed === 'yes') {
      delete form.dataset.confirmed;
      return;
    }
    event.preventDefault();
    pendingForm = form;
    pendingSubmitter = event.submitter ?? null;

    title.textContent = form.dataset.confirm;
    text.textContent = form.dataset.confirmText ?? '';
    text.hidden = !form.dataset.confirmText;
    ok.textContent = form.dataset.confirmButton ?? 'Confirm';
    ok.className = `btn ${form.dataset.confirmTone === 'danger' ? 'btn--danger' : 'btn--primary'}`;
    dialog.showModal();
    // Safer default: focus Cancel, so a stray Enter does not confirm.
    dialog.querySelector('[value="cancel"]')?.focus();
  }, true);

  dialog.addEventListener('close', () => {
    const form = pendingForm;
    pendingForm = null;
    if (form && dialog.returnValue === 'confirm') {
      form.dataset.confirmed = 'yes';
      form.requestSubmit(pendingSubmitter && form.contains(pendingSubmitter) ? pendingSubmitter : undefined);
    }
    pendingSubmitter = null;
  });

  // Click on the backdrop = cancel.
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close('cancel');
  });
}
