<?php

return [
    // The one shared sign-in password. Everyone types it together with their
    // name; the name is recorded on the notes they write. Changing it signs
    // everyone out.
    'password' => (string) env('APP_PASSWORD', ''),

    // Wrong passwords allowed per IP address within the window, before that
    // address has to wait.
    'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 8),
    'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
];
