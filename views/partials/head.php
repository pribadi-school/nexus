<?php
/** @var string $title */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $title !== '' ? e($title) . ' · ' : '' ?>Nexus</title>
<meta name="description" content="Nexus, the IT infrastructure dashboard for Pribadi School. Connecting to Pribadi School.">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#FFFFFF">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="<?= e(asset('brand/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('brand/apple-touch-icon.png')) ?>">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/devices.css')) ?>">
<?php if (is_active('/signage')): ?>
<link rel="stylesheet" href="<?= e(asset('css/signage.css')) ?>">
<script type="module" src="<?= e(asset('js/signage-admin.js')) ?>"></script>
<script type="module" src="<?= e(asset('js/signage-editor.js')) ?>"></script>
<?php endif ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
