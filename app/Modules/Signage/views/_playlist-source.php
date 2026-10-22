<?php
// Which playlist this TV plays: its own, or a shared one used by several TVs.
$base = '/signage/screens/' . (int) $screen['id'];
$shared = (bool) ($currentPlaylist['is_shared'] ?? false);
$currentId = $currentPlaylist === null ? 0 : (int) $currentPlaylist['id'];
$sharedUsers = [];
foreach ($sharedPlaylists as $option) {
    if ((int) $option['id'] === $currentId) {
        $sharedUsers = array_values(array_filter($option['screens'], static fn (array $tv): bool => (int) $tv['id'] !== (int) $screen['id']));
    }
}
?>
<section class="card signage-playlist-source" id="playlist-source" aria-labelledby="playlist-source-title">
  <h2 class="card__title" id="playlist-source-title">Playlist for this TV</h2>
  <?php if ($shared): ?>
    <p class="signage-help">Playing the shared playlist <strong><?= e($currentPlaylist['name']) ?></strong><?= $sharedUsers !== [] ? ', also used by ' . e(implode(', ', array_column($sharedUsers, 'name'))) : '' ?>. Changes below apply to every TV that plays it.</p>
  <?php else: ?>
    <p class="signage-help">This TV has its own playlist. Choose a shared playlist to play the same list as other TVs.</p>
  <?php endif ?>
  <form class="signage-playlist-source__form" method="post" action="<?= e($base) ?>/assign-playlist" data-busy-on-submit
        data-confirm="Change this TV's playlist?" data-confirm-text="<?= $shared ? 'This TV will switch playlists. Leaving a shared playlist keeps a copy of it for this TV.' : 'This TV’s own playlist is replaced by the one you choose. Use “Save as shared playlist” first to keep it.' ?>" data-confirm-button="Change playlist">
    <?= csrf_field() ?>
    <label class="visually-hidden" for="assign-playlist">Playlist</label>
    <select class="select" id="assign-playlist" name="playlist_id">
      <option value="0"<?= !$shared ? ' selected' : '' ?>>This TV only (its own playlist)</option>
      <?php foreach ($sharedPlaylists as $option): ?>
        <option value="<?= (int) $option['id'] ?>"<?= (int) $option['id'] === $currentId ? ' selected' : '' ?>><?= e($option['name']) ?> · <?= e(plural((int) $option['item_count'], 'item')) ?></option>
      <?php endforeach ?>
    </select>
    <button class="btn btn--secondary" type="submit">Use playlist</button>
  </form>
  <?php if ($shared): ?>
    <p class="signage-help"><a href="/signage/playlists/<?= $currentId ?>/settings">Rename or copy this shared playlist</a></p>
  <?php else: ?>
    <details class="signage-playlist-source__share">
      <summary>Save as shared playlist</summary>
      <p class="signage-help">Keeps this TV’s playlist as a named shared playlist, so other TVs can play it too. This TV keeps playing it.</p>
      <form class="signage-playlist-new" method="post" action="<?= e($base) ?>/share-playlist" data-busy-on-submit>
        <?= csrf_field() ?>
        <label class="visually-hidden" for="share-name">Shared playlist name</label>
        <input class="input" id="share-name" name="name" value="<?= e($screen['name']) ?>" maxlength="160" required autocomplete="off">
        <button class="btn btn--primary" type="submit">Save as shared</button>
      </form>
    </details>
  <?php endif ?>
</section>
