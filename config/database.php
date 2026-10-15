<?php

return [
    'host' => env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_DATABASE', 'nexus'),
    'user' => env('DB_USERNAME', 'nexus'),
    'password' => (string) env('DB_PASSWORD', ''),
];
