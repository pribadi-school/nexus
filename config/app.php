<?php

return [
    'name' => 'Nexus',
    'tagline' => 'Connecting to Pribadi School',
    'system_name' => 'IT Infrastructure Management System',
    'env' => env('APP_ENV', 'production'),          // local | production
    'debug' => (bool) env('APP_DEBUG', false),       // show error details (never on the live site)
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'session_days' => (int) env('SESSION_LIFETIME_DAYS', 14),
];
