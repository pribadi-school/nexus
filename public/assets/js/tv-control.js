// TV control card: send actions without leaving the page and refresh the card
// while the helper PC runs them. Without JavaScript the forms post normally.
import { request, setBusy } from './util.js';
import { toast } from './toast.js';

export function initTvControl() {
  const lifetime = new AbortController();
  const {signal} = lifetime;
  let timer = null;

  const card = () => document.querySelector('[data-tv-control]');
  const replace = html => {
    const current = card();
    if (!current || !html) return;
    const next = document.createRange().createContextualFragment(html).firstElementChild;
    if (next) current.replaceWith(next);
    schedule();
  };
  const refresh = async () => {
    const current = card();
    if (!current) return;
    try {
      replace((await request(current.dataset.tvControl)).html);
    } catch {
      schedule();
    }
  };
  // Poll only while an action is queued or running.
  function schedule() {
    clearTimeout(timer);
    if (!signal.aborted && card()?.hasAttribute('data-tv-control-busy')) timer = setTimeout(refresh, 3000);
  }

  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-tv-action]') || event.defaultPrevented) return;
    event.preventDefault();
    const button = event.submitter ?? form.querySelector('button');
    setBusy(button, true);
    try {
      // getAttribute: the field named "action" shadows form.action.
      const data = await request(form.getAttribute('action'), {method: 'POST', body: new FormData(form)});
      toast(data.message);
      replace(data.html);
    } catch (error) {
      toast(error.message, {tone: 'error'});
      setBusy(button, false);
    }
  }, {signal});

  schedule();
  return () => { lifetime.abort(); clearTimeout(timer); };
}
