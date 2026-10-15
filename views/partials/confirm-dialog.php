<?php
/*
 * The one confirmation dialog, filled in by assets/js/confirm.js. Any form
 * with data-confirm="Question?" asks before it submits:
 *
 *   <form method="post" action="/x/delete"
 *         data-confirm="Delete this board?"
 *         data-confirm-text="Its notes stay in the database."
 *         data-confirm-button="Delete" data-confirm-tone="danger">
 */
?>
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-text">
  <form method="dialog">
    <div class="dialog__body">
      <h2 class="dialog__title" id="confirm-title"></h2>
      <p class="dialog__text" id="confirm-text"></p>
    </div>
    <div class="dialog__actions">
      <button type="submit" class="btn btn--secondary" value="cancel">Cancel</button>
      <button type="submit" class="btn btn--primary" value="confirm" data-confirm-ok>Confirm</button>
    </div>
  </form>
</dialog>
