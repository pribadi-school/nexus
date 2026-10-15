// Helpers shared by the front-end modules.

/** CSRF token for fetch() calls (forms carry it as a hidden field). */
export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** fetch() with the headers the server expects; throws with a readable message. */
export async function request(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'X-Requested-With': 'fetch',
      'X-CSRF-Token': csrfToken(),
      Accept: 'application/json',
      ...(options.headers ?? {}),
    },
  });
  if (response.status === 401) {
    window.location.href = '/login';
    throw new Error('Your session ended. Sign in again.');
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error ?? 'Something went wrong. Try again.');
  }
  return data;
}

/** Show a button or link as working (spinner, disabled) until reset. */
export function setBusy(button, busy) {
  if (!button) return;
  button.classList.toggle('is-busy', busy);
  if (button.tagName === 'BUTTON') button.disabled = busy;
  button.setAttribute('aria-busy', busy ? 'true' : 'false');
}
