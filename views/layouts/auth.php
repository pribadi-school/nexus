<?php
/**
 * Signed-out pages (sign in, access pending, errors): one white card on the
 * navy brand surface.
 *
 * @var string $content
 * @var string $title
 */
?>
<!doctype html>
<html lang="en">
<head>
<?= partial('partials/head', ['title' => $title]) ?>
</head>
<body class="auth-page">
<main class="auth-card">
  <?= $content ?>
</main>
<p class="auth-footer"><?= trim(partial('partials/copyright')) ?></p>
</body>
</html>
