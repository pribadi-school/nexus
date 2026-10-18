<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title><?= e($name) ?> · Nexus Signage</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/signage-player.css')) ?>">
  <script type="module" src="<?= e(asset('js/signage-player.js')) ?>"></script>
</head>
<body class="signage-player" data-signage-player data-manifest-url="<?= e($manifestUrl) ?>">
  <main class="player-stage" aria-label="Signage content">
    <div class="player-content" data-player-content></div>
    <div class="player-message" data-player-message role="status" aria-live="polite">
      <img src="<?= e(asset('brand/logo-mark-white.svg')) ?>" alt="" width="56" height="56">
      <h1 data-player-heading>Connecting to Nexus</h1>
      <p data-player-detail>The playlist will start automatically.</p>
      <button class="player-button" data-player-start hidden>Start playback</button>
    </div>
  </main>
  <div class="player-tools" data-player-tools>
    <span class="player-label"><?= $preview ? 'Playlist preview' : e($name) ?></span>
    <button class="player-button" data-player-next title="Next item (right arrow)" aria-label="Next item">Next</button>
    <button class="player-button" data-player-fullscreen title="Full screen (F)">Full screen</button>
  </div>
  <div class="player-notice" data-player-notice role="status" hidden></div>
  <noscript><p class="player-noscript">Enable JavaScript in this browser to play the playlist.</p></noscript>
</body>
</html>
