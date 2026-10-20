<?php
use App\Core\Session;

$id = (int) $chromebook['id'];
$draft = Session::get('chromebook.student.draft');
if (($draft['id'] ?? null) === $id) Session::forget('chromebook.student.draft'); else $draft = null;
$values = $draft['values'] ?? $values ?? $chromebook;
$errors = $draft['errors'] ?? $errors ?? [];
?>
<section class="card detail__assign" aria-labelledby="student-details-title">
  <div class="card__header"><h2 class="card__title" id="student-details-title">Name and email</h2></div>
  <div class="assign-form" id="assign">
    <div class="field">
      <label class="field__label" for="student_name">Name</label>
      <input class="input" id="student_name" name="student_name" type="text" maxlength="120" autocomplete="off" form="<?= e($formId) ?>"
             value="<?= e($values['student_name'] ?? '') ?>"<?= isset($errors['student_name']) ? ' aria-invalid="true" aria-describedby="student-name-error"' : '' ?>>
      <?php if (isset($errors['student_name'])): ?><p class="field__error" id="student-name-error"><?= e($errors['student_name']) ?></p><?php endif ?>
    </div>
    <div class="field">
      <label class="field__label" for="student_email">Email</label>
      <input class="input" id="student_email" name="student_email" type="email" maxlength="160" autocomplete="off" spellcheck="false" form="<?= e($formId) ?>"
             value="<?= e($values['student_email'] ?? '') ?>"<?= isset($errors['student_email']) ? ' aria-invalid="true" aria-describedby="student-email-error"' : '' ?>>
      <?php if (isset($errors['student_email'])): ?><p class="field__error" id="student-email-error"><?= e($errors['student_email']) ?></p><?php endif ?>
    </div>
  </div>
</section>
