<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Kendali Kendaraan</title>
    <style>
        /* Mempertebal margin terluar kertas menjadi 1.5 cm agar tidak menabrak tepi */
        @page {
            size: A4 landscape;
            margin: 15mm; 
        }
        body {
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 7.5pt;
        }
        .page-break {
            page-break-after: always;
        }
        /* Pembungkus utama agar terbagi 2 dengan aman */
        .wrapper-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        /* Memberi jarak ekstra di tengah dan di sisi paling luar */
        .wrapper-td-left {
            width: 50%;
            padding-right: 8mm;
            padding-left: 2mm;
            vertical-align: top;
        }
        .wrapper-td-right {
            width: 50%;
            padding-left: 8mm;
            padding-right: 2mm;
            vertical-align: top;
        }
        
        /* Header Kop Surat */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }
        .header-logo {
            width: 25%;
            vertical-align: top;
        }
        .header-title {
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding-top: 3mm;
        }
        .header-title-text {
            font-size: 10pt;
            font-weight: bold;
            text-decoration: underline;
        }
        .header-qr {
            width: 25%;
            vertical-align: top;
            text-align: right;
        }
        .qr-meta {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 4.5pt;
            color: #222;
            margin-top: 1mm;
        }

        /* Identitas Kendaraan */
        .identity-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        .identity-table td {
            padding: 0.5mm 0;
            vertical-align: top;
            line-height: 1.3;
        }

        /* Tabel Riwayat Service */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .history-table th, .history-table td {
            border: 0.5px solid #000;
            padding: 1.8mm 1mm;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }
        .history-table th {
            font-weight: bold;
            text-align: center;
            background-color: #f9f9f9;
        }
        .history-table td {
            text-align: center;
        }
        .text-left {
            text-align: left !important;
        }

        /* Tanda Tangan */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        .sign-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .sign-role {
            min-height: 8mm;
        }
        .sign-space {
            height: 12mm;
        }
        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $kasubbag = ($documentSignatories ?? [])['kasubbag'] ?? null;
        $administrator = ($documentSignatories ?? [])['administrator'] ?? null;
    @endphp

    @foreach ($pages as $pageIndex => $rows)
        <div class="{{ ! $loop->last ? 'page-break' : '' }}">
            <table class="wrapper-table">
                <tr>
                    @foreach ([1, 2] as $copy)
                        <td class="{{ $copy === 1 ? 'wrapper-td-left' : 'wrapper-td-right' }}">
                            
                            <!-- HEADER -->
                            <table class="header-table">
                                <tr>
                                    <td class="header-logo">
                                        <img src="{{ public_path(config('simantap.institution.logo')) }}" style="width: 30mm; height: auto;" alt="Logo BPS">
                                    </td>
                                    <td class="header-title">
                                        <div class="header-title-text">KARTU KENDALI KENDARAAN</div>
                                    </td>
                                    <td class="header-qr">
                                        <img src="{{ $verificationQrDataUri }}" style="width: 10mm; height: 10mm;" alt="QR Code">
                                        <div class="qr-meta">
                                            <strong>Verifikasi SIMANTAP</strong><br>
                                            Versi {{ $documentVerification->version }}<br>
                                            {{ substr($documentVerification->payload_hash, 0, 12) }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- IDENTITAS -->
                            <table class="identity-table">
                                <tr>
                                    <td style="width: 28%; font-weight: bold;">NAMA KENDARAAN</td>
                                    <td style="width: 3%;">:</td>
                                    <td style="width: 69%;">{{ $vehicle->displayName() }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold;">NOMOR POLISI</td>
                                    <td>:</td>
                                    <td>{{ $vehicle->license_plate }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold;">MERK/TYPE</td>
                                    <td>:</td>
                                    <td>{{ trim($vehicle->brand.' '.$vehicle->model) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold;">PENANGGUNG JAWAB</td>
                                    <td>:</td>
                                    <td>{{ $vehicle->responsible_person ?: '-' }}</td>
                                </tr>
                            </table>

                            <!-- HISTORY -->
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th style="width: 6%;">No</th>
                                        <th style="width: 14%;">Tgl</th>
                                        <th style="width: 26%;">Jenis Pemeliharaan</th>
                                        <th style="width: 24%;">Tempat Pemeliharaan</th>
                                        <th style="width: 15%;">Paraf Pelaksana</th>
                                        <th style="width: 15%;">Paraf Pengelola</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $index => $row)
                                        <tr>
                                            <td>{{ $row !== null ? (($pageIndex * $rowsPerCard) + $index + 1) : '' }}</td>
                                            <td>{{ $row['date'] ?? '' }}</td>
                                            <td class="text-left">{{ $row['maintenance_type'] ?? '' }}</td>
                                            <td class="text-left">{{ $row['service_provider'] ?? '' }}</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- SIGNATURES -->
                            <div style="text-align: center; margin-top: 4mm; margin-bottom: 1mm;">Mengetahui,</div>
                            <table class="sign-table">
                                <tr>
                                    <td>
                                        <div class="sign-role">{{ $kasubbag['role_label'] ?? 'Kasubbag Umum' }}</div>
                                        <div class="sign-space"></div>
                                        <div class="sign-name">{{ $kasubbag['name'] ?? '................................' }}</div>
                                        <div>NIP. {{ $kasubbag['employee_number'] ?? '................................' }}</div>
                                    </td>
                                    <td>
                                        <div class="sign-role">{{ $administrator['role_label'] ?? 'Administrator / Pengelola Barang' }}</div>
                                        <div class="sign-space"></div>
                                        <div class="sign-name">{{ $administrator['name'] ?? '................................' }}</div>
                                        <div>NIP. {{ $administrator['employee_number'] ?? '................................' }}</div>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>