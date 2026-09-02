<?php

return [
    'name' => env('APP_NAME', 'SIMANTAP'),

    'long_name' => 'Sistem Manajemen Aset dan Persediaan',

    'institution' => [
        'name' => env(
            'SIMANTAP_INSTITUTION_NAME',
            'Badan Pusat Statistik Kabupaten Jombang',
        ),
        'short_name' => env(
            'SIMANTAP_INSTITUTION_SHORT_NAME',
            'BPS Kabupaten Jombang',
        ),
        'logo' => env(
            'SIMANTAP_INSTITUTION_LOGO',
            'images/bps-kabupaten-jombang.png',
        ),
    ],

    'display_timezone' => env(
        'SIMANTAP_DISPLAY_TIMEZONE',
        'Asia/Jakarta',
    ),

    'pagination' => [
        'per_page' => (int) env(
            'SIMANTAP_DEFAULT_PAGINATION',
            15,
        ),
        'max_per_page' => (int) env(
            'SIMANTAP_MAX_PAGINATION',
            100,
        ),
    ],

    'security' => [
        'password_min_length' => 12,
        'activation_expire_minutes' => 60,
        'login_max_attempts' => 5,
        'login_decay_seconds' => 60,
    ],

    'document_numbers' => [
        'inventory_request' => 'REQ',
        'vehicle_loan' => 'LOAN',
        'maintenance' => 'MTC',
        'stock_in' => 'STK-IN',
        'stock_adjustment' => 'STK-ADJ',
        'stock_movement' => 'MOV',
    ],

    'inventory' => [
        'quantity_scale' => 2,
    ],

    'vehicle' => [
        'max_loan_days' => (int) env(
            'SIMANTAP_MAX_VEHICLE_LOAN_DAYS',
            3,
        ),
    ],

    'uploads' => [
        'disk' => env('SIMANTAP_UPLOAD_DISK', 'local'),
        'avatar_max_size_kb' => 2048,
        'evidence_max_size_kb' => 5120,
        'signature_max_size_kb' => 2048,
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

    'signature' => [
        'hash_algorithm' => 'sha256',
    ],

    'production_database' => env(
        'SIMANTAP_PRODUCTION_DATABASE',
        'simantap',
    ),

    'demo' => [
        'enabled' => filter_var(
            env('SIMANTAP_DEMO_MODE', false),
            FILTER_VALIDATE_BOOL,
        ),
        'database' => env(
            'SIMANTAP_DEMO_DATABASE',
            'simantapdemo',
        ),
        'accounts' => [
            'administrator' => [
                'employee_number' => env(
                    'SIMANTAP_DEMO_ADMIN_EMPLOYEE_NUMBER',
                    'DEMO-ADMIN-001',
                ),
                'name' => env(
                    'SIMANTAP_DEMO_ADMIN_NAME',
                    'Administrator Demo',
                ),
                'email' => env(
                    'SIMANTAP_DEMO_ADMIN_EMAIL',
                    '',
                ),
                'password' => env(
                    'SIMANTAP_DEMO_ADMIN_PASSWORD',
                    '',
                ),
                'work_unit' => env(
                    'SIMANTAP_DEMO_ADMIN_WORK_UNIT',
                    'BPS Kabupaten Jombang - Demo',
                ),
                'position' => env(
                    'SIMANTAP_DEMO_ADMIN_POSITION',
                    'Administrator Demo',
                ),
            ],
            'employee' => [
                'employee_number' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_EMPLOYEE_NUMBER',
                    'DEMO-PEGAWAI-001',
                ),
                'name' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_NAME',
                    'Pegawai Demo',
                ),
                'email' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_EMAIL',
                    '',
                ),
                'password' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_PASSWORD',
                    '',
                ),
                'work_unit' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_WORK_UNIT',
                    'BPS Kabupaten Jombang - Demo',
                ),
                'position' => env(
                    'SIMANTAP_DEMO_EMPLOYEE_POSITION',
                    'Pegawai Demo',
                ),
            ],
        ],
    ],

    'admin' => [
        'employee_number' => env(
            'SIMANTAP_ADMIN_EMPLOYEE_NUMBER',
            'ADMIN001',
        ),
        'name' => env(
            'SIMANTAP_ADMIN_NAME',
            'MITHA RAMADHANI PRATIWI',
        ),
        'email' => env(
            'SIMANTAP_ADMIN_EMAIL',
            'mitharamadhanipratiwi@bps.go.id',
        ),
        'password' => env(
            'SIMANTAP_ADMIN_PASSWORD',
            'AdminSimantap!2026',
        ),
        'work_unit' => env(
            'SIMANTAP_ADMIN_WORK_UNIT',
            'BPS',
        ),
        'position' => env(
            'SIMANTAP_ADMIN_POSITION',
            'Administrator Sistem',
        ),
    ],
];
