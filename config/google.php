<?php

return [
    'credentials' => (string) env('GOOGLE_SERVICE_ACCOUNT_FILE', ''),
    'admin_email' => (string) env('GOOGLE_ADMIN_EMAIL', ''),
    // Telemetry requires the actual customer ID, rather than my_customer.
    'customer_id' => (string) env('GOOGLE_CUSTOMER_ID', ''),
    'telemetry' => (bool) env('GOOGLE_TELEMETRY_ENABLED', true),
    'ou_password_hash' => (string) env('GOOGLE_OU_PASSWORD_HASH', ''),
];
