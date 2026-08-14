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

        body {
            margin: 0;
            padding: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 7.6pt;
            color: #000;
        }

        .page {
            width: 100%;
        }

        .page-break {
            page-break-after: always;
        }

        .pair {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pair > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }

        .card-cell-left {
            padding-right: 2.5mm;
        }

        .card-cell-right {
            padding-left: 2.5mm;
        }

        .card {
            width: 100%;
        }

        .title {
            margin: 0 0 4mm 0;
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-decoration: underline;
        }

        .identity {
            width: 100%;
            margin-bottom: 3mm;
            border-collapse: collapse;
        }

        .identity td {
            padding: 0.55mm 0;
            vertical-align: top;
            line-height: 1.25;
        }

        .identity-label {
            width: 31%;
            font-weight: bold;
            white-space: nowrap;
        }

        .identity-colon {
            width: 4%;
            text-align: center;
            font-weight: bold;
        }

        .identity-value {
            width: 65%;
            font-weight: normal;
        }

        .history {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .history th,
        .history td {
            border: 0.25mm solid #000;
            text-align: center;
            vertical-align: middle;
            padding: 0.7mm 0.6mm;
        }

        .history th {
            font-size: 6.6pt;
            line-height: 1.15;
            font-weight: bold;
            height: 9mm;
        }

        .history td {
            height: 5.1mm;
            font-size: 6.8pt;
            line-height: 1.1;
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
            padding-left: 1mm;
            padding-right: 1mm;
        }

        .knowing {
            width: 50%;
            margin-top: 3mm;
            margin-bottom: 1mm;
            text-align: center;
            font-size: 7.4pt;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signatures td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding: 0;
        }

        .signatures .left {
            padding-right: 5mm;
        }

        .signatures .right {
            padding-left: 5mm;
        }

        .role {
            min-height: 9mm;
            line-height: 1.25;
        }

        .signature-space {
            height: 15mm;
        }

        .signer-name {
            min-height: 4mm;
            font-weight: bold;
            line-height: 1.2;
        }

        .nip {
            margin-top: 1mm;
            min-height: 4mm;
            line-height: 1.2;
        }
    </style>
</head>

<body>
    @foreach ($pages as $pageIndex => $rows)
        <div class="page {{ ! $loop->last ? 'page-break' : '' }}">
            <table class="pair">
                <tbody>
                    <tr>
                        @foreach ([1, 2] as $copy)
                            <td class="{{ $copy === 1 ? 'card-cell-left' : 'card-cell-right' }}">
                                <div class="card">
                                    <div class="title">
                                        KARTU KENDALI KENDARAAN
                                    </div>

                                    <table class="identity">
                                        <tbody>
                                            <tr>
                                                <td class="identity-label">NAMA KENDARAAN</td>
                                                <td class="identity-colon">:</td>
                                                <td class="identity-value">
                                                    {{ $vehicle->displayName() }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="identity-label">NOMOR POLISI</td>
                                                <td class="identity-colon">:</td>
                                                <td class="identity-value">
                                                    {{ $vehicle->license_plate }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="identity-label">MERK/TYPE</td>
                                                <td class="identity-colon">:</td>
                                                <td class="identity-value">
                                                    {{ trim($vehicle->brand.' '.$vehicle->model) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="identity-label">PENANGGUNG JAWAB</td>
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
                                                    Jenis<br>
                                                    Pemeliharaan
                                                </th>
                                                <th class="col-place">
                                                    Tempat<br>
                                                    Pemeliharaan
                                                </th>
                                                <th class="col-sign">
                                                    Paraf<br>
                                                    Pelaksana
                                                </th>
                                                <th class="col-sign">
                                                    Paraf<br>
                                                    Pengelola
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach ($rows as $index => $row)
                                                <tr data-control-row="1">
                                                    <td>
                                                        {{ $row !== null ? (($pageIndex * $rowsPerCard) + $index + 1) : '' }}
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
                                                <td class="left">
                                                    <div class="role">
                                                        Kepala Badan Pusat Statistik<br>
                                                        Kabupaten Jombang
                                                    </div>

                                                    <div class="signature-space"></div>

                                                    <div class="signer-name">
                                                        &nbsp;
                                                    </div>

                                                    <div class="nip">
                                                        NIP. ....................................
                                                    </div>
                                                </td>

                                                <td class="right">
                                                    <div class="role">
                                                        Pengelola Barang
                                                    </div>

                                                    <div class="signature-space"></div>

                                                    <div class="signer-name">
                                                        &nbsp;
                                                    </div>

                                                    <div class="nip">
                                                        NIP. ....................................
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>