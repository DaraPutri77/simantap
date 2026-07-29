<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Identity
    |--------------------------------------------------------------------------
    */

    'name' => 'SIMANTAP',

    'long_name' => 'Sistem Manajemen Aset dan Persediaan',

    'organization' => env('SIMANTAP_ORGANIZATION', 'Badan Pusat Statistik'),

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    'pagination' => [
        'per_page' => (int) env('SIMANTAP_PAGINATION_PER_PAGE', 15),
        'max_per_page' => (int) env('SIMANTAP_PAGINATION_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'password_min_length' => (int) env('SIMANTAP_PASSWORD_MIN_LENGTH', 12),
        'activation_expire_minutes' => (int) env('SIMANTAP_ACTIVATION_EXPIRE_MINUTES', 60),
        'login_max_attempts' => (int) env('SIMANTAP_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('SIMANTAP_LOGIN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'avatar_max_size_kb' => (int) env('SIMANTAP_AVATAR_MAX_SIZE_KB', 2048),
        'evidence_max_size_kb' => (int) env('SIMANTAP_EVIDENCE_MAX_SIZE_KB', 5120),
        'signature_max_size_kb' => (int) env('SIMANTAP_SIGNATURE_MAX_SIZE_KB', 2048),
        'image_mimetypes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'document_mimetypes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],

];
