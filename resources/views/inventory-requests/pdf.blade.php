<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $inventoryRequest->request_number }}</title>
    <style>
        @page {
            margin: 22mm 16mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
        }

        .header td {
            vertical-align: middle;
        }

        .mark {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            line-height: 48px;
        }
        .institution-logo {
            display: block;
            width: 220px;
            height: auto;
        }

        .institution {
            padding-left: 11px;
        }

        .institution strong {
            display: block;
            color: #0f172a;
            font-size: 13pt;
            letter-spacing: .2px;
        }

        .institution span {
            color: #475569;
            font-size: 8.5pt;
        }

        .document-meta {
            width: 190px;
            font-size: 8.5pt;
        }

        .document-meta td {
            padding: 2px 0 2px 8px;
        }

        .title {
            margin: 21px 0 4px;
            color: #0f172a;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
        }

        .subtitle {
            margin: 0 0 17px;
            color: #475569;
            font-size: 8.5pt;
            text-align: center;
        }

        .section-title {
            margin: 16px 0 7px;
            color: #0f172a;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .info {
            width: 100%;
            border: 1px solid #94a3b8;
            border-collapse: collapse;
        }

        .info td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            vertical-align: top;
        }

        .info .label {
            width: 23%;
            background: #f1f5f9;
            color: #334155;
            font-size: 8pt;
            font-weight: bold;
        }

        .items {
            width: 100%;
            border: 1px solid #334155;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        .items thead {
            display: table-header-group;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .items th {
            border: 1px solid #334155;
            background: #e2e8f0;
            padding: 6px 5px;
            color: #0f172a;
            font-size: 7.7pt;
            text-align: center;
        }

        .items td {
            border: 1px solid #64748b;
            padding: 6px 5px;
            vertical-align: top;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #64748b;
            font-size: 7.5pt;
        }

        .status {
            margin-top: 10px;
            border: 1px solid #94a3b8;
            background: #f8fafc;
            padding: 8px 10px;
        }

        .signatures {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signatures td {
            width: 33.333%;
            padding: 0 7px;
            text-align: center;
            vertical-align: top;
        }

        .signature-label {
            min-height: 32px;
            color: #334155;
            font-size: 8pt;
            font-weight: bold;
        }

        .signature-box {
            height: 74px;
            margin-top: 5px;
            border-bottom: 1px solid #334155;
            text-align: center;
        }

        .signature-box img {
            max-width: 145px;
            max-height: 70px;
        }

        .signature-name {
            margin-top: 5px;
            color: #0f172a;
            font-size: 8pt;
            font-weight: bold;
        }

        .signature-guidance {
            padding-top: 28px;
            color: #64748b;
            font-size: 7pt;
            font-style: italic;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -10mm;
            left: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            color: #64748b;
            font-size: 7pt;
            text-align: center;
        }
        .document-verification {
            width: 130px;
            margin-top: 5px;
            margin-left: auto;
            text-align: center;
            page-break-inside: avoid;
        }

        .document-verification img {
            display: block;
            width: 58px;
            height: 58px;
            margin: 0 auto 3px;
        }

        .document-verification-title {
            color: #0f172a;
            font-size: 6.8pt;
            font-weight: bold;
        }

        .document-verification-meta {
            margin-top: 2px;
            color: #64748b;
            font-size: 6pt;
            line-height: 1.25;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 50px;">
                <img class="institution-logo" src="{{ public_path(config('simantap.institution.logo')) }}" alt="{{ $institutionName }}">
            </td>
            <td class="institution">
                <!-- Nama instansi sudah tercantum pada logo resmi. -->
                <span>Sistem Manajemen Aset dan Persediaan</span>
            </td>
            <td class="document-meta">
                <table>
                    <tr>
                        <td><strong>Nomor</strong></td>
                        <td>: {{ $inventoryRequest->request_number }}</td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal</strong></td>
                        <td>: {{ $inventoryRequest->request_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y') }}</td>
                    </tr>
                </table>
                            <div class="document-verification">
                    <img
                        src="{{ $verificationQrDataUri }}"
                        alt="QR verifikasi dokumen"
                    >
                    <div class="document-verification-title">
                        Verifikasi SIMANTAP
                    </div>
                    <div class="document-verification-meta">
                        Versi {{ $documentVerification->version }}
                        · {{ substr($documentVerification->payload_hash, 0, 12) }}
                        <br>
                        QR bukan tanda tangan digital
                    </div>
                </div>
</td>
        </tr>
    </table>

    <h1 class="title">FORM PERMINTAAN PERSEDIAAN</h1>
    <p class="subtitle">
        Dokumen dibuat otomatis oleh SIMANTAP
    </p>

    <h2 class="section-title">Identitas Pemohon</h2>
    <table class="info">
        <tr>
            <td class="label">Nama Pegawai</td>
            <td>{{ $inventoryRequest->requester_name_snapshot }}</td>
            <td class="label">NIP / Identitas</td>
            <td>{{ $inventoryRequest->employee_number_snapshot ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Unit Kerja</td>
            <td>{{ $inventoryRequest->work_unit_snapshot ?: '-' }}</td>
            <td class="label">Jabatan</td>
            <td>{{ $inventoryRequest->requester?->position ?: '-' }}</td>
        </tr>
    </table>

    <h2 class="section-title">Daftar Barang</h2>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 28px;">No.</th>
                <th>Nama Barang</th>
                <th style="width: 58px;">Satuan</th>
                <th style="width: 54px;">Diminta</th>
                <th style="width: 58px;">Disetujui</th>
                <th style="width: 58px;">Diserahkan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inventoryRequest->items as $index => $line)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $line->item_name_snapshot }}</strong><br>
                        <span class="muted">{{ $line->item_code_snapshot }}</span>
                    </td>
                    <td class="center">{{ $line->unit_snapshot }}</td>
                    <td class="center">
                        {{ number_format((float) $line->requested_quantity, 2, ',', '.') }}
                    </td>
                    <td class="center">
                        {{ $line->approved_quantity !== null
                            ? number_format((float) $line->approved_quantity, 2, ',', '.')
                            : '-' }}
                    </td>
                    <td class="center">
                        {{ $line->delivered_quantity !== null
                            ? number_format((float) $line->delivered_quantity, 2, ',', '.')
                            : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="status">
        <strong>Status:</strong>
        {{ $inventoryRequest->status->label() }}
        @if ($inventoryRequest->admin_notes)
            <br><strong>Catatan Administrator:</strong>
            {{ $inventoryRequest->admin_notes }}
        @endif
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-label">Pengelola Barang,</div>
                <div class="signature-box">
                    @if ($approvalSignature)
                        <img src="{{ $approvalSignature }}" alt="Tanda tangan pemeriksa">
                    @endif
                </div>
                <div class="signature-name">
                    {{ $inventoryRequest->approver?->name ?: '(belum disetujui)' }}
                </div>
                <div class="muted">
                    {{ $inventoryRequest->approver?->position ?: '' }}
                </div>
            </td>
            <td>
                <div class="signature-label">Penerima Barang,</div>
                <div class="signature-box">
                    @if ($receiptSignature)
                        <img src="{{ $receiptSignature }}" alt="Tanda tangan penerima">
                    @endif
                </div>
                <div class="signature-name">
                    {{ $receiptSignature
                        ? $inventoryRequest->requester_name_snapshot
                        : '(belum dikonfirmasi)' }}
                </div>
                <div class="muted">
                    {{ $inventoryRequest->received_at
                        ? $inventoryRequest->received_at->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i').' WIB'
                        : '' }}
                </div>
            </td>
            <td>
                @php
                    $kasubbag = ($documentSignatories ?? [])['kasubbag']
                        ?? null;
                @endphp
                <div class="signature-label">
                    Mengetahui / {{ $kasubbag['role_label'] ?? 'Kasubbag Umum' }},
                </div>
                <div class="signature-box">
                    <div class="signature-guidance">
                        Tanda tangan basah (opsional)
                    </div>
                </div>
                <div class="signature-name">
                    {{ $kasubbag['name'] ?? '................................' }}
                </div>
                <div class="muted">
                    NIP/Nomor Pegawai: {{ $kasubbag['employee_number'] ?? '................................' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $inventoryRequest->request_number }}
        · Dicetak {{ $documentVerification->issued_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB
        · Dokumen elektronik SIMANTAP
    </div>
</body>
</html>
