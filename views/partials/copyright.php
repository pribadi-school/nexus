<?php
// Copyright line for the login page and the signed-in footer. The year
// follows the school's calendar, so it never needs updating by hand.
$year = (new DateTimeImmutable('now', local_tz()))->format('Y');
?>
&copy; <?= e($year) ?> Nexus by Pribadi School. All rights reserved.
