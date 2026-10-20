<?php
/** @var \App\Modules\Chromebooks\ChromebookCategory $category */
/** @var string $status */
$tone = $category->statusMeta($status)['tone'];
?>
<span class="chip chip--<?= e($tone) ?> chromebook-status"><?= e($category->statusLabel($status)) ?></span>
