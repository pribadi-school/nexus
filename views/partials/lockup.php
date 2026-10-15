<?php
/**
 * Logo mark + "nexus" wordmark. Size it with font-size on .lockup.
 * @var string|null $href  link target, or null for plain text
 */
$href ??= null;
$tag = $href !== null ? 'a' : 'span';
?>
<<?= $tag ?> class="lockup"<?= $href !== null ? ' href="' . e($href) . '" aria-label="Nexus, go to the dashboard"' : '' ?>>
  <img class="lockup__mark" src="<?= e(asset('brand/logo-mark.svg')) ?>" alt="" width="32" height="32">
  <span class="lockup__wordmark"<?= $href !== null ? ' aria-hidden="true"' : '' ?>>nexus</span>
</<?= $tag ?>>
