<?php
$seen = !empty($screen['last_seen_at']) ? new DateTimeImmutable($screen['last_seen_at'], new DateTimeZone('UTC')) : null;
$pollSeconds = max(10, min(120, (int) config('signage.poll_seconds', 30)));
$online = $seen !== null && time() - $seen->getTimestamp() < max(90, 3 * $pollSeconds);
$state = !(bool) $screen['enabled'] ? 'Paused' : ($seen === null ? 'Never connected' : ($online ? 'Online' : 'Offline'));
$tone = $state === 'Online' ? 'success' : ($state === 'Offline' ? 'warning' : 'quiet');
?>
<span class="chip chip--<?= e($tone) ?>"><?= e($state) ?></span>
