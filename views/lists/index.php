<?php
/**
 * One editable list (rooms, CCTV areas): add, rename, reorder, delete.
 *
 * @var \App\Devices\LookupList $list
 * @var list<array{id:int, name:string, used:int}> $items
 */
use App\Core\Session;

$draft = (string) Session::pull('lookup.draft', '');
$category = $list->category;
$last = count($items) - 1;
?>
<a class="back-link" href="<?= e($category->path()) ?>"><?= icon('chevron-left', 'icon--sm') ?><?= e($category->name()) ?></a>

<header class="page-header">
  <div>
    <h1 class="page-title"><?= e($list->title) ?></h1>
    <p class="page-subtitle"><?= e($list->intro) ?></p>
  </div>
</header>

<div class="stack">
  <section class="card" aria-labelledby="add-title">
    <div class="card__header"><h2 class="card__title" id="add-title">Add a <?= e($list->singular) ?></h2></div>
    <div class="card__body">
      <form class="inline-form" method="post" action="<?= e($list->path) ?>" data-busy-on-submit>
        <?= csrf_field() ?>
        <label class="visually-hidden" for="new-name">Name</label>
        <input class="input" id="new-name" name="name" type="text" required maxlength="<?= $list->maxLength ?>"
               value="<?= e($draft) ?>" placeholder="Name" autocomplete="off"<?= $draft !== '' ? ' autofocus' : '' ?>>
        <button type="submit" class="btn btn--primary"><?= icon('plus', 'icon--sm') ?>Add</button>
      </form>
    </div>
  </section>

  <section class="card" aria-labelledby="list-title">
    <div class="card__header">
      <h2 class="card__title" id="list-title">All <?= e(strtolower($list->title)) ?></h2>
      <span class="chip chip--quiet num"><?= count($items) ?></span>
    </div>

    <?php if ($items === []): ?>
      <?= partial('partials/empty-state', [
          'icon' => 'map-pin',
          'title' => 'No ' . strtolower($list->title) . ' yet',
          'text' => 'Add the first ' . $list->singular . ' above. You can rename and reorder them any time.',
          'compact' => true,
      ]) ?>
    <?php else: ?>
      <ol class="lookup-list">
        <?php foreach ($items as $i => $item): $id = (int) $item['id']; ?>
          <li class="lookup-item" id="item-<?= $id ?>">
            <div class="lookup-item__text">
              <span class="lookup-item__name"><?= e($item['name']) ?></span>
              <span class="lookup-item__used">
                <?= $item['used'] > 0 ? e(plural((int) $item['used'], $list->usageNoun[0], $list->usageNoun[1])) : 'Not used' ?>
              </span>
            </div>

            <div class="lookup-item__actions">
              <form method="post" action="<?= e($list->path . '/' . $id) ?>">
                <?= csrf_field() ?>
                <button type="submit" name="move" value="up" class="icon-btn"<?= $i === 0 ? ' disabled' : '' ?> title="Move up"><?= icon('chevron-up', '', 'Move ' . $item['name'] . ' up') ?></button>
                <button type="submit" name="move" value="down" class="icon-btn"<?= $i === $last ? ' disabled' : '' ?> title="Move down"><?= icon('chevron-down', '', 'Move ' . $item['name'] . ' down') ?></button>
              </form>

              <details class="rename">
                <summary class="btn btn--secondary btn--sm"><?= icon('pencil', 'icon--sm') ?><span class="rename__label">Rename</span></summary>
                <form class="inline-form rename__form" method="post" action="<?= e($list->path . '/' . $id) ?>" data-busy-on-submit>
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="rename-<?= $id ?>">New name for <?= e($item['name']) ?></label>
                  <input class="input" id="rename-<?= $id ?>" name="name" type="text" required maxlength="<?= $list->maxLength ?>" value="<?= e($item['name']) ?>" autocomplete="off">
                  <button type="submit" class="btn btn--primary btn--sm">Save</button>
                </form>
              </details>

              <?php if ($item['used'] === 0): ?>
                <form method="post" action="<?= e($list->path . '/' . $id . '/delete') ?>"
                      data-confirm="Delete &quot;<?= e($item['name']) ?>&quot;?" data-confirm-button="Delete" data-confirm-tone="danger">
                  <?= csrf_field() ?>
                  <button type="submit" class="icon-btn icon-btn--danger" title="Delete"><?= icon('trash-2', '', 'Delete ' . $item['name']) ?></button>
                </form>
              <?php else: ?>
                <span class="icon-btn" aria-hidden="true"></span>
              <?php endif ?>
            </div>
          </li>
        <?php endforeach ?>
      </ol>
    <?php endif ?>
  </section>
</div>
