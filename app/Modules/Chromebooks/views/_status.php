<?php
/** @var \App\Modules\Chromebooks\ChromebookCategory $category */
/** @var string $status */
$tone = match ($status) {
    'reported' => 'warning',
    'problem' => 'problem',
    'repaired' => 'success',
    'replaced' => 'action',
    default => 'quiet',
};
?>
<span class="chip chip--<?= e($tone) ?> chromebook-status"><?= e($category->statusLabel($status)) ?></span>
