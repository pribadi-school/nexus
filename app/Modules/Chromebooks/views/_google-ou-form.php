<form class="google-ou-form" method="post" action="/chromebooks/<?= (int) $chromebook['id'] ?>/google/ou" data-google-move-form>
  <?= csrf_field() ?>
  <input type="hidden" name="expected_ou" value="<?= e($currentOu) ?>">
  <div class="field">
    <label class="field__label" for="google-org-unit">Move to</label>
    <select class="input" id="google-org-unit" name="org_unit" required<?= $units === null ? ' disabled' : '' ?>>
      <option value="">Choose an organizational unit</option>
      <?php foreach ($units ?? [] as $unit): ?>
        <option value="<?= e($unit) ?>"<?= $unit === $currentOu ? ' disabled' : '' ?>><?= e($unit) ?><?= $unit === $currentOu ? ' (current)' : '' ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field">
    <label class="field__label" for="google-management-password">Management password</label>
    <input class="input" id="google-management-password" type="password" name="management_password" required autocomplete="off" maxlength="1024">
    <p class="field__hint">The separate password set by your IT administrator.</p>
  </div>
  <label class="google-ou-form__confirm"><input type="checkbox" name="confirm" value="1" required> I confirm this change may apply different device policies.</label>
  <button class="btn btn--primary" type="submit"<?= $units === null ? ' disabled' : '' ?>>Move Chromebook</button>
</form>
