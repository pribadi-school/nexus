<?php

declare(strict_types=1);

return [
    // PHP's upload_max_filesize and post_max_size may impose a smaller limit.
    'max_upload_mb' => max(1, min(250, (int) env('SIGNAGE_MAX_UPLOAD_MB', 250))),
    'poll_seconds' => 30,
    // Shared hosting caps disk I/O for the whole account (8 MB/s on the school's
    // plan). Pacing each media download leaves room for Nexus and sibling sites.
    // 0 disables pacing.
    // Shared secret for the helper PC that runs ADB actions on TVs
    // (bin/tv-agent.ps1). Empty hides TV control and disables its API.
    'tv_agent_token' => (string) env('SIGNAGE_TV_AGENT_TOKEN', ''),
    'stream_mb_per_second' => max(0, min(100, (float) env('SIGNAGE_STREAM_MBPS', 2))),
];
