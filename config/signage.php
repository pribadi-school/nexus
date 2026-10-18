<?php

declare(strict_types=1);

return [
    // PHP's upload_max_filesize and post_max_size may impose a smaller limit.
    'max_upload_mb' => max(1, (int) env('SIGNAGE_MAX_UPLOAD_MB', 500)),
    'poll_seconds' => 30,
];
