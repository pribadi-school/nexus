<?php
/**
 * Initials in a neutral circle.
 *
 * @var array<string, mixed> $user
 * @var string $size  '' | 'sm'
 */
$size ??= '';
?>
<span class="avatar<?= $size !== '' ? ' avatar--' . e($size) : '' ?>" aria-hidden="true"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
