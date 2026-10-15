<?php
/**
 * Logo mark + "nexus" wordmark. Size it with font-size on .lockup.
 *
 * @var string|null $href  link target, or null for plain text
 * @var bool $large        true = full mark (56px and up, login page);
 *                         false = medium mark (sidebar, top bar)
 */
$href ??= null;
$large ??= false;
$tag = $href !== null ? 'a' : 'span';
$mark = $large ? 'brand/logo-mark.svg' : 'brand/logo-mark-48.svg';
?>
<<?= $tag ?> class="lockup<?= $large ? ' lockup--lg' : '' ?>"<?= $href !== null ? ' href="' . e($href) . '" aria-label="Nexus, go to the dashboard"' : '' ?>>
  <img class="lockup__mark" src="<?= e(asset($mark)) ?>" alt="" width="40" height="40">
  <span class="lockup__wordmark"<?= $href !== null ? ' aria-hidden="true"' : '' ?>>nexus</span>
</<?= $tag ?>>
