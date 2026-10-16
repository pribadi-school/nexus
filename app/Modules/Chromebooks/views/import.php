<?php
/**
 * Import one academic year's list: step 1 choose the year and a file
 * (Excel or CSV), step 2 check the preview and confirm.
 *
 * @var \App\Modules\Chromebooks\ChromebookCategory $category
 * @var array{columns:list<string>, rows:list<array<string, mixed>>, file:string}|null $plan
 * @var string|null $error
 * @var int $year
 * @var list<int> $years  newest first
 */
$tally = ['add' => 0, 'update' => 0, 'same' => 0, 'error' => 0];
foreach ($plan['rows'] ?? [] as $row) {
    $tally[$row['action']]++;
}
$labels = ['add' => 'New', 'update' => 'Update', 'same' => 'No change', 'error' => 'Skipped'];
$tones = ['add' => 'success', 'update' => 'action', 'same' => 'neutral', 'error' => 'problem'];
?>
<a class="back-link" href="/chromebooks?year=<?= $year ?>"><?= icon('chevron-left', 'icon--sm') ?>Chromebooks <?= $year ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title">Import Chromebooks</h1>
    <p class="page-subtitle"><?= $plan === null ? 'Step 1 of 2: choose the year and the file' : "Step 2 of 2: check and import into $year" ?></p>
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
    <div class="form-grid">
      <div class="field">
        <label class="field__label" for="academic_year">Academic year</label>
        <select class="select" id="academic_year" name="academic_year">
          <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option>
          <?php endforeach ?>
        </select>
        <p class="field__hint">Every Chromebook in the file goes into this year's list.</p>
      </div>
      <div class="field">
        <label class="field__label" for="file">File</label>
        <input class="input" id="file" name="file" type="file" required
               accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        <p class="field__hint">Excel (.xlsx, first sheet) or CSV. Up to 2 MB.</p>
      </div>
    </div>

    <div class="import-help">
      <p>The yearly list can be imported as it is. One of the first rows names the columns:</p>
      <ul>
        <li><strong>ID</strong> (asset ID, e.g. <?= $year ?>-01) or <strong>Serial Number</strong>: at least one is needed. Rows are matched on asset ID, else on serial; new ones are added.</li>
        <li><strong>Nama</strong> / student name and <strong>Email</strong>: who has it. A new name is saved as a handover in the history. A registration number at the end of a name (REG-1140) is left out.</li>
        <li><strong>Model</strong>, <strong>Status</strong> (OK or Has problem): optional. Other columns (No, Tanggal Instalasi) are ignored.</li>
      </ul>
      <p>Empty cells never clear anything, so a later file with serial numbers only fills them in. Nothing is saved until you confirm on the next step.</p>
      <p><a href="/chromebooks/import/example" download><?= icon('download', 'icon--sm') ?> Download an example file</a></p>
    </div>

    <div class="form-actions">
      <a class="btn btn--secondary" href="/chromebooks?year=<?= $year ?>">Cancel</a>
      <button type="submit" class="btn btn--primary"><?= icon('upload', 'icon--sm') ?>Check file</button>
    </div>
  </form>
<?php else: ?>
  <section class="card import-summary">
    <p class="import-summary__file"><?= icon('upload', 'icon--sm') ?><?= e($plan['file']) ?> · <?= count($plan['rows']) ?> rows · into <?= $year ?></p>
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
      <a class="btn btn--secondary" href="/chromebooks/import?year=<?= $year ?>">Choose another file</a>
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
          <th scope="col">Asset ID</th>
          <th scope="col">Serial number</th>
          <th scope="col">Student</th>
          <th scope="col">What happens</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plan['rows'] as $row): ?>
          <tr class="<?= $row['action'] === 'error' ? 'is-problem' : '' ?>">
            <td class="num data-table__muted"><?= (int) $row['line'] ?></td>
            <td><span class="import-tag import-tag--<?= $tones[$row['action']] ?>"><?= e($labels[$row['action']]) ?></span></td>
            <td class="num"><?= e($row['asset_id'] ?? '–') ?></td>
            <td class="num"><?= e($row['serial_number'] ?? '–') ?></td>
            <td>
              <?= e($row['student_name'] ?? '–') ?>
              <?php if ($row['student_email']): ?><span class="data-table__sub"><?= e($row['student_email']) ?></span><?php endif ?>
            </td>
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
