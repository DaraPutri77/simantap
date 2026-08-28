<?php

$administratorEmployeeNumber = env(
    'SIMANTAP_SIGNATORY_ADMINISTRATOR',
    'SIM-JBG-017',
);

$kasubbagEmployeeNumber = env(
    'SIMANTAP_SIGNATORY_KASUBBAG',
    'SIM-JBG-020',
);

$officialSignatories = static fn (): array => [
    'kasubbag' => [
        'role_label' => 'Kasubbag Umum',
        'employee_number' => $kasubbagEmployeeNumber,
    ],
    'administrator' => [
        'role_label' => 'Administrator / Pengelola Barang',
        'employee_number' => $administratorEmployeeNumber,
    ],
];

return [
    /*
    |--------------------------------------------------------------------------
    | Document Signatories
    |--------------------------------------------------------------------------
    |
    | Jabatan di sini adalah kapasitas administratif pada dokumen, bukan
    | role/permission aplikasi. Nama penandatangan tetap dibaca dari akun
    | aktif berdasarkan employee_number agar nama tidak di-hardcode di Blade.
    |
    | Environment variable dapat mengganti pejabat tanpa mengubah template.
    |
    */

    'stock_card' => [
        'kasubbag' => [
            'role_label' => 'Kasubbag Umum',
            'employee_number' => env(
                'SIMANTAP_SIGNATORY_STOCK_CARD_KASUBBAG',
                $kasubbagEmployeeNumber,
            ),
        ],

        'inventory_manager' => [
            'role_label' => 'Pengelola Barang',
            'employee_number' => env(
                'SIMANTAP_SIGNATORY_STOCK_CARD_MANAGER',
                $administratorEmployeeNumber,
            ),
        ],
    ],

    'inventory_request' => $officialSignatories(),
    'maintenance_record' => $officialSignatories(),
    'reports' => $officialSignatories(),
    'vehicle_control_card' => $officialSignatories(),
    'vehicle_loan' => $officialSignatories(),
    'vehicle_loan_lifecycle' => $officialSignatories(),
];
