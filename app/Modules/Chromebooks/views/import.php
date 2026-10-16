<?php
/**
 * CSV import: step 1 choose a file, step 2 check the preview and confirm.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array{columns:list<string>, rows:list<array<string, mixed>>, file:string}|null $plan
 * @var string|null $error
 */
$tally = ['add' => 0, 'update' => 0, 'same' => 0, 'error' => 0];
foreach ($plan['rows'] ?? [] as $row) {
    $tally[$row['action']]++;
}
$labels = ['add' => 'New', 'update' => 'Update', 'same' => 'No change', 'error' => 'Skipped'];
$tones = ['add' => 'success', 'update' => 'action', 'same' => 'neutral', 'error' => 'problem'];
?>
<a class="back-link" href="/chromebooks"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks</a>

<header class="page-header">
  <div>
    <h1 class="page-title">Import Chromebooks</h1>
    <p class="page-subtitle"><?= $plan === null ? 'Step 1 of 2: choose a CSV file' : 'Step 2 of 2: check and import' ?></p>
  </div>
</header>

<?php if ($error !== null): ?>
  <div class="flashes">
    <div class="notice notice--error" role="alert"><?= icon('circle-alert') ?><div><?= e($error) ?></div></div>
  </div>
<?php endif ?>

<?php if ($plan === null): ?>
  <form class="card form-card" method="post" action="/chromebooks/import" enctype="multipart/form-data" data-busy-on-submit>
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="file">CSV file</label>
      <input class="input" id="file" name="file" type="file" accept=".csv,text/csv" required>
      <p class="field__hint">Save from Excel or Google Sheets as CSV. Up to 2 MB.</p>
    </div>

    <div class="import-help">
      <p>The first row names the columns. Only <strong>serial_number</strong> is required:</p>
      <ul>
        <li><strong>serial_number</strong>: matches existing Chromebooks; new serials are added.</li>
        <li><strong>student_name</strong>, <strong>class</strong>: who has it. A change is saved as a reassignment in the history. The class must be in the <a href="/chromebooks/classes">class list</a>.</li>
        <li><strong>model</strong>, <strong>status</strong> (OK or Has problem): optional.</li>
      </ul>
      <p>A file from <em>Export CSV</em> on the Chromebooks page can be edited and imported again. Nothing is saved until you confirm on the next step.</p>
      <p><a href="/chromebooks/import/example" download><?= icon('download', 'icon--sm') ?> Download an example file</a></p>
    </div>

    <div class="form-actions">
      <a class="btn btn--secondary" href="/chromebooks">Cancel</a>
      <button type="submit" class="btn btn--primary"><?= icon('upload', 'icon--sm') ?>Check file</button>
    </div>
  </form>
<?php else: ?>
  <section class="card import-summary">
    <p class="import-summary__file"><?= icon('upload', 'icon--sm') ?><?= e($plan['file']) ?> · <?= count($plan['rows']) ?> rows</p>
    <ul class="import-summary__counts">
      <?php foreach ($tally as $action => $n): if ($n === 0) continue; ?>
        <li class="import-tag import-tag--<?= $tones[$action] ?>"><?= $n ?> <?= e(strtolower($labels[$action])) ?></li>
      <?php endforeach ?>
    </ul>
    <?php if ($tally['error'] > 0): ?>
      <p class="import-summary__note">Skipped rows are not imported. Fix them in the file and import it again, or go ahead without them.</p>
    <?php endif ?>
    <form class="import-summary__actions" method="post" action="/chromebooks/import/confirm" data-busy-on-submit>
      <?= csrf_field() ?>
      <a class="btn btn--secondary" href="/chromebooks/import">Choose another file</a>
      <?php if ($tally['add'] + $tally['update'] > 0): ?>
        <button type="submit" class="btn btn--primary">Import <?= $tally['add'] + $tally['update'] ?> <?= $tally['add'] + $tally['update'] === 1 ? 'row' : 'rows' ?></button>
      <?php endif ?>
    </form>
  </section>

  <div class="card table-card table-card--always">
    <table class="data-table">
      <thead>
        <tr>
          <th scope="col">Line</th>
          <th scope="col">Result</th>
          <th scope="col">Serial number</th>
          <th scope="col">Student</th>
          <th scope="col">Class</th>
          <th scope="col">What happens</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plan['rows'] as $row): ?>
          <tr class="<?= $row['action'] === 'error' ? 'is-problem' : '' ?>">
            <td class="num data-table__muted"><?= (int) $row['line'] ?></td>
            <td><span class="import-tag import-tag--<?= $tones[$row['action']] ?>"><?= e($labels[$row['action']]) ?></span></td>
            <td class="num"><?= e($row['serial_number'] ?? '–') ?></td>
            <td><?= e($row['student_name'] ?? '–') ?></td>
            <td><?= e($row['class_name'] ?? '–') ?></td>
            <td>
              <?= e($row['message']) ?>
              <?php if (isset($row['previous'])): ?><span class="data-table__muted"> (was <?= e($row['previous']) ?>)</span><?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
