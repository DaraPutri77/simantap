<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Kendali Kendaraan</title>

    <style>
        @page {
            size: A4 landscape;
            margin: 7mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: #000;
            font-family: "Times New Roman", Times, serif;
            font-size: 6.8pt;
        }

        .sheet {
            position: relative;
            width: 100%;
            height: 194mm;
            overflow: hidden;
        }

        .sheet.page-break {
            page-break-after: always;
        }

        .card-copy {
            position: absolute;
            top: 0;
            width: 137.5mm;
            height: 191mm;
            overflow: hidden;
        }

        .card-left {
            left: 0;
        }

        .card-right {
            right: 0;
        }

        .header {
            position: relative;
            height: 16mm;
        }

        .institution-logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 34mm;
            height: auto;
        }

        .title {
            margin: 0 25mm 0 36mm;
            padding-top: 3mm;
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            text-decoration: underline;
            line-height: 1.15;
        }

        .document-verification {
            position: absolute;
            top: 0;
            right: 0;
            width: 23mm;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
        }

        .document-verification img {
            display: block;
            width: 8.5mm;
            height: 8.5mm;
            margin: 0 auto 0.3mm;
        }

        .document-verification-meta {
            color: #222;
            font-size: 3.8pt;
            line-height: 1.05;
        }

        .identity {
            width: 100%;
            margin: 1mm 0 2mm;
            border-collapse: collapse;
        }

        .identity td {
            padding: 0.35mm 0;
            line-height: 1.15;
            vertical-align: top;
        }

        .identity-label {
            width: 31%;
            font-weight: bold;
            white-space: nowrap;
        }

        .identity-colon {
            width: 4%;
            font-weight: bold;
            text-align: center;
        }

        .identity-value {
            width: 65%;
        }

        .history {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .history th,
        .history td {
            border: 0.25mm solid #000;
            vertical-align: middle;
        }

        .history th {
            height: 8mm;
            padding: 0.45mm;
            font-size: 5.7pt;
            font-weight: bold;
            line-height: 1.05;
            text-align: center;
        }

        .history td {
            height: 4.45mm;
            padding: 0.35mm 0.5mm;
            font-size: 5.9pt;
            line-height: 1.05;
            text-align: center;
        }

        .history .col-no {
            width: 6%;
        }

        .history .col-date {
            width: 14%;
        }

        .history .col-type {
            width: 27%;
        }

        .history .col-place {
            width: 25%;
        }

        .history .col-sign {
            width: 14%;
        }

        .history .text-left {
            text-align: left;
        }

        .knowing {
            margin-top: 2mm;
            margin-bottom: 1mm;
            font-size: 6.3pt;
            text-align: center;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signatures td {
            width: 50%;
            padding: 0 2mm;
            text-align: center;
            vertical-align: top;
        }

        .role {
            min-height: 7mm;
            font-size: 6.2pt;
            line-height: 1.15;
        }

        .signature-space {
            height: 9mm;
        }

        .signer-line {
            min-height: 3mm;
            font-size: 6pt;
            line-height: 1.1;
        }

        .nip {
            margin-top: 0.5mm;
            font-size: 5.8pt;
        }
    </style>
</head>

<body>
    @php
        $kasubbag = ($documentSignatories ?? [])['kasubbag'] ?? null;
        $administrator = ($documentSignatories ?? [])['administrator']
            ?? null;
    @endphp

    @foreach ($pages as $pageIndex => $rows)
        <section class="sheet {{ ! $loop->last ? 'page-break' : '' }}">
            @foreach ([1, 2] as $copy)
                <div
                    class="card-copy {{ $copy === 1 ? 'card-left' : 'card-right' }}"
                    data-card-copy="{{ $copy }}"
                >
                    <header class="header">
                        <img
                            class="institution-logo"
                            src="{{ public_path(config('simantap.institution.logo')) }}"
                            alt="Badan Pusat Statistik Kabupaten Jombang"
                        >

                        <div class="title">
                            KARTU KENDALI KENDARAAN
                        </div>

                        <div class="document-verification">
                            <img
                                src="{{ $verificationQrDataUri }}"
                                alt="QR verifikasi dokumen"
                            >

                            <div class="document-verification-meta">
                                <strong>Verifikasi SIMANTAP</strong>
                                <br>
                                Versi {{ $documentVerification->version }}
                                ·
                                {{ substr($documentVerification->payload_hash, 0, 12) }}
                                <br>
                                QR bukan tanda tangan digital
                            </div>
                        </div>
                    </header>

                    <table class="identity">
                        <tbody>
                            <tr>
                                <td class="identity-label">
                                    NAMA KENDARAAN
                                </td>
                                <td class="identity-colon">:</td>
                                <td class="identity-value">
                                    {{ $vehicle->displayName() }}
                                </td>
                            </tr>

                            <tr>
                                <td class="identity-label">
                                    NOMOR POLISI
                                </td>
                                <td class="identity-colon">:</td>
                                <td class="identity-value">
                                    {{ $vehicle->license_plate }}
                                </td>
                            </tr>

                            <tr>
                                <td class="identity-label">
                                    MERK/TYPE
                                </td>
                                <td class="identity-colon">:</td>
                                <td class="identity-value">
                                    {{ trim($vehicle->brand.' '.$vehicle->model) }}
                                </td>
                            </tr>

                            <tr>
                                <td class="identity-label">
                                    PENANGGUNG JAWAB
                                </td>
                                <td class="identity-colon">:</td>
                                <td class="identity-value">
                                    {{ $vehicle->responsible_person ?: '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="history">
                        <thead>
                            <tr>
                                <th class="col-no">No</th>
                                <th class="col-date">Tgl</th>
                                <th class="col-type">
                                    Jenis<br>Pemeliharaan
                                </th>
                                <th class="col-place">
                                    Tempat<br>Pemeliharaan
                                </th>
                                <th class="col-sign">
                                    Paraf<br>Pelaksana
                                </th>
                                <th class="col-sign">
                                    Paraf<br>Pengelola
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr data-control-row="1">
                                    <td>
                                        {{
                                            $row !== null
                                                ? (($pageIndex * $rowsPerCard) + $index + 1)
                                                : ''
                                        }}
                                    </td>

                                    <td>
                                        {{ $row['date'] ?? '' }}
                                    </td>

                                    <td class="text-left">
                                        {{ $row['maintenance_type'] ?? '' }}
                                    </td>

                                    <td class="text-left">
                                        {{ $row['service_provider'] ?? '' }}
                                    </td>

                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="knowing">
                        Mengetahui,
                    </div>

                    <table class="signatures">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="role">
                                        {{ $kasubbag['role_label'] ?? 'Kasubbag Umum' }}
                                    </div>

                                    <div class="signature-space"></div>

                                    <div class="signer-line">
                                        {{ $kasubbag['name'] ?? '................................' }}
                                    </div>

                                    <div class="nip">
                                        NIP/Nomor Pegawai: {{ $kasubbag['employee_number'] ?? '................................' }}
                                    </div>
                                </td>

                                <td>
                                    <div class="role">
                                        {{ $administrator['role_label'] ?? 'Administrator / Pengelola Barang' }}
                                    </div>

                                    <div class="signature-space"></div>

                                    <div class="signer-line">
                                        {{ $administrator['name'] ?? '................................' }}
                                    </div>

                                    <div class="nip">
                                        NIP/Nomor Pegawai: {{ $administrator['employee_number'] ?? '................................' }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach
        </section>
    @endforeach
</body>
</html>
