<?php

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
                'SIM-JBG-020',
            ),
        ],

        'inventory_manager' => [
            'role_label' => 'Pengelola Barang',
            'employee_number' => env(
                'SIMANTAP_SIGNATORY_STOCK_CARD_MANAGER',
                'SIM-JBG-017',
            ),
        ],
    ],
];
