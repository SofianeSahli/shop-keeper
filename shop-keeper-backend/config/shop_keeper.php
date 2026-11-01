<?php

return [
    'terms_version' => env('TERMS_VERSION', '2026-11'),

    'google_client_ids' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))),
    )),

    'auth_test_mode' => (bool) env('AUTH_TEST_MODE', false),
];
