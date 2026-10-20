<?php
/** A bounded excerpt centred on the search text, escaped before highlighting. */
use App\Devices\NoteKind;

$body = (string) $row['matching_note'];
$position = mb_stripos($body, $query);
$start = max(0, ($position === false ? 0 : $position) - 65);
$excerpt = ($start > 0 ? '…' : '') . mb_substr($body, $start, 220);
if (mb_strlen($body) > $start + 220) {
    $excerpt .= '…';
}
$parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $excerpt, -1, PREG_SPLIT_DELIM_CAPTURE);
?>
<span class="search-result__note">
  <span class="search-result__excerpt"><?php foreach ($parts === false ? [$excerpt] : $parts as $i => $part): ?><?php if ($i % 2 === 1): ?><mark><?= e($part) ?></mark><?php else: ?><?= e($part) ?><?php endif ?><?php endforeach ?></span>
  <span class="search-result__context"><?= e(NoteKind::meta((string) $row['matching_note_kind'])['label']) ?> · <?= e(format_datetime($row['matching_note_at'])) ?><?= $row['matching_note_author'] ? ' · ' . e($row['matching_note_author']) : '' ?></span>
</span>
