<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $vehicleLoan->loan_number }}</title>
    <style>
        @page {
            margin: 11mm 12mm 13mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 8.7pt;
            line-height: 1.35;
        }

        table {
            border-collapse: collapse;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f2747;
            padding-bottom: 7px;
        }

        .header-table td {
            vertical-align: middle;
        }

        .header-logo {
            width: 25%;
            text-align: left;
        }

        .header-logo img {
            width: 150px;
            height: auto;
        }

        .header-title {
            width: 53%;
            text-align: center;
            padding: 0 8px;
        }

        .header-title h1 {
            margin: 0;
            color: #0f2747;
            font-size: 14pt;
            line-height: 1.15;
            letter-spacing: .2px;
        }

        .header-title p {
            margin: 4px 0 0;
            color: #526078;
            font-size: 8.3pt;
        }

        .header-verify {
            width: 22%;
            text-align: center;
            color: #526078;
            font-size: 6.4pt;
            line-height: 1.25;
        }

        .header-verify img {
            display: block;
            width: 52px;
            height: 52px;
            margin: 0 auto 2px;
        }

        .header-verify strong {
            display: block;
            color: #172033;
            font-size: 6.8pt;
        }

        .document-strip {
            width: 100%;
            margin-top: 9px;
            table-layout: fixed;
        }

        .document-strip td {
            border: 1px solid #d7dee9;
            background: #f7f9fc;
            padding: 6px 8px;
            vertical-align: top;
        }

        .document-strip .kicker {
            display: block;
            margin-bottom: 2px;
            color: #66758d;
            font-size: 6.6pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .document-strip .value {
            color: #172033;
            font-size: 8.5pt;
            font-weight: bold;
        }

        .status {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            color: #1d4ed8;
            background: #eff6ff;
            font-size: 7.5pt;
            font-weight: bold;
        }

        .section {
            margin-top: 9px;
            page-break-inside: avoid;
        }

        .section-title {
            margin: 0;
            padding: 5px 8px;
            border-left: 4px solid #0b7db5;
            background: #eef7fb;
            color: #0f2747;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .55px;
        }

        .info-grid,
        .detail-grid {
            width: 100%;
            table-layout: fixed;
            border: 1px solid #d7dee9;
            border-top: 0;
        }

        .info-grid td,
        .detail-grid td {
            border: 1px solid #e1e6ef;
            padding: 5px 7px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .field-label {
            width: 18%;
            background: #f7f9fc;
            color: #526078;
            font-size: 7.2pt;
            font-weight: bold;
        }

        .field-value {
            width: 32%;
            color: #172033;
            font-size: 8.2pt;
        }

        .full-label {
            width: 20%;
            background: #f7f9fc;
            color: #526078;
            font-size: 7.2pt;
            font-weight: bold;
        }

        .full-value {
            width: 80%;
            color: #172033;
            font-size: 8.2pt;
        }

        .process-grid {
            width: 100%;
            table-layout: fixed;
            border: 1px solid #d7dee9;
            border-top: 0;
        }

        .process-grid td {
            width: 50%;
            border: 1px solid #e1e6ef;
            padding: 6px 8px;
            vertical-align: top;
        }

        .process-grid .label {
            display: block;
            margin-bottom: 2px;
            color: #66758d;
            font-size: 6.7pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .process-grid .person {
            font-size: 8.1pt;
            font-weight: bold;
        }

        .process-grid .muted {
            margin-top: 2px;
            color: #66758d;
            font-size: 6.8pt;
        }

        .note-box {
            margin-top: 6px;
            border: 1px solid #d7dee9;
            padding: 6px 8px;
            background: #fbfcfe;
        }

        .note-box strong {
            color: #526078;
            font-size: 7pt;
        }

        .signature-section {
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            table-layout: fixed;
        }

        .signature-table td {
            width: 33.333%;
            vertical-align: top;
            padding: 0 5px;
        }

        .signature-card {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 6px 8px 7px;
            text-align: center;
            min-height: 105px;
        }

        .signature-role {
            color: #526078;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .signature-image {
            height: 42px;
            margin: 4px 0 2px;
            text-align: center;
        }

        .signature-image img {
            max-width: 135px;
            max-height: 40px;
        }

        .signature-placeholder {
            padding-top: 13px;
            color: #94a3b8;
            font-size: 7pt;
            font-style: italic;
        }

        .signature-name {
            color: #172033;
            font-size: 8pt;
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-meta {
            margin-top: 2px;
            color: #66758d;
            font-size: 6.8pt;
        }

        .verification-note {
            margin-top: 8px;
            padding: 5px 7px;
            border-top: 1px solid #d7dee9;
            color: #66758d;
            font-size: 6.5pt;
            line-height: 1.3;
            text-align: center;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -8mm;
            left: 0;
            color: #7b879a;
            font-size: 6.2pt;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $returnCheck = $vehicleLoan->returnCheck();
        $returnRequestHistory = $vehicleLoan->returnRequestHistory();
    @endphp

    <table class="header-table">
        <tr>
            <td class="header-logo">
                <img
                    src="{{ public_path(config('simantap.institution.logo')) }}"
                    alt="{{ $institutionName }}"
                >
            </td>
            <td class="header-title">
                <h1>FORM PEMINJAMAN KENDARAAN DINAS</h1>
                <p>{{ $institutionName }} · SIMANTAP</p>
            </td>
            <td class="header-verify">
                <img src="{{ $verificationQrDataUri }}" alt="QR verifikasi dokumen">
                <strong>VERIFIKASI DOKUMEN</strong>
                Versi {{ $documentVerification->version }} · {{ substr($documentVerification->payload_hash, 0, 10) }}<br>
                QR bukan tanda tangan digital
            </td>
        </tr>
    </table>

    <table class="document-strip">
        <tr>
            <td style="width: 38%;">
                <span class="kicker">Nomor Peminjaman</span>
                <span class="value">{{ $vehicleLoan->loan_number }}</span>
            </td>
            <td style="width: 24%;">
                <span class="kicker">Status</span>
                <span class="status">{{ $vehicleLoan->status->label() }}</span>
            </td>
            <td style="width: 38%;">
                <span class="kicker">Tanggal Cetak</span>
                <span class="value">{{ $documentVerification->issued_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</span>
            </td>
        </tr>
    </table>

    <section class="section">
        <div class="section-title">Identitas Peminjam</div>
        <table class="info-grid">
            <tr>
                <td class="field-label">Nama</td>
                <td class="field-value">{{ $vehicleLoan->borrower_name_snapshot }}</td>
                <td class="field-label">NIP / Nomor Pegawai</td>
                <td class="field-value">{{ $vehicleLoan->employee_number_snapshot ?: '-' }}</td>
            </tr>
            <tr>
                <td class="field-label">Unit Kerja</td>
                <td class="field-value">{{ $vehicleLoan->work_unit_snapshot ?: '-' }}</td>
                <td class="field-label">Nomor Telepon</td>
                <td class="field-value">{{ $vehicleLoan->phone_snapshot ?: '-' }}</td>
            </tr>
        </table>
    </section>

    <section class="section">
        <div class="section-title">Rincian Peminjaman</div>
        <table class="detail-grid">
            <tr>
                <td class="full-label">Kendaraan Dinas</td>
                <td class="full-value" colspan="3">
                    <strong>{{ $vehicleLoan->vehicle_code_snapshot }}</strong>
                    · {{ $vehicleLoan->license_plate_snapshot }}
                    · {{ $vehicleLoan->vehicle_name_snapshot }}
                </td>
            </tr>
            <tr>
                <td class="field-label">Mulai</td>
                <td class="field-value">{{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</td>
                <td class="field-label">Selesai</td>
                <td class="field-value">{{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</td>
            </tr>
            <tr>
                <td class="full-label">Tujuan</td>
                <td class="full-value" colspan="3">{{ $vehicleLoan->destination }}</td>
            </tr>
            <tr>
                <td class="full-label">Keperluan</td>
                <td class="full-value" colspan="3">{!! nl2br(e($vehicleLoan->purpose)) !!}</td>
            </tr>
            <tr>
                <td class="full-label">Keterangan</td>
                <td class="full-value" colspan="3">{{ $vehicleLoan->reason ?: '-' }}</td>
            </tr>
        </table>
    </section>

    @if (
        $vehicleLoan->actual_end_at !== null
        || $returnRequestHistory !== null
        || $returnCheck !== null
    )
        <section class="section">
            <div class="section-title">Pengembalian Kendaraan</div>
            <table class="detail-grid">
                <tr>
                    <td class="field-label">Tanggal Kembali</td>
                    <td class="field-value">
                        {{ $vehicleLoan->actual_end_at?->timezone($displayTimezone)->translatedFormat('d F Y, H:i') ?: '-' }}{{ $vehicleLoan->actual_end_at ? ' WIB' : '' }}
                    </td>
                    <td class="field-label">Odometer Akhir</td>
                    <td class="field-value">
                        {{ $returnCheck ? number_format((float) $returnCheck->odometer, 1, ',', '.').' km' : 'Menunggu pemeriksaan' }}
                    </td>
                </tr>
                <tr>
                    <td class="field-label">Kondisi</td>
                    <td class="field-value">
                        {{ $returnCheck?->overall_condition->label() ?: 'Menunggu pemeriksaan' }}
                    </td>
                    <td class="field-label">Diterima Oleh</td>
                    <td class="field-value">
                        {{ $returnCheck?->checker_name_snapshot ?: ($returnCheck?->checker?->name ?: '-') }}
                        @if ($returnCheck?->checker_employee_number_snapshot || $returnCheck?->checker?->employee_number)
                            <br>
                            <span style="color: #66758d; font-size: 7pt;">
                                NIP/Nomor Pegawai: {{ $returnCheck->checker_employee_number_snapshot ?: $returnCheck->checker?->employee_number }}
                            </span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="full-label">Catatan</td>
                    <td class="full-value" colspan="3">
                        {!! nl2br(e($returnRequestHistory?->notes ?: '-')) !!}
                    </td>
                </tr>
                @if ($returnCheck?->damage_notes)
                    <tr>
                        <td class="full-label">Catatan Kondisi</td>
                        <td class="full-value" colspan="3">
                            {!! nl2br(e($returnCheck->damage_notes)) !!}
                        </td>
                    </tr>
                @endif
            </table>
        </section>
    @endif

    <section class="section">
        <div class="section-title">Verifikasi dan Persetujuan</div>
        <table class="process-grid">
            <tr>
                <td>
                    <span class="label">Diperiksa oleh</span>
                    <span class="person">{{ $vehicleLoan->reviewer?->name ?: 'Belum diperiksa' }}</span>
                    @if ($vehicleLoan->reviewed_at)
                        <div class="muted">{{ $vehicleLoan->reviewed_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</div>
                    @endif
                </td>
                <td>
                    <span class="label">Disetujui oleh</span>
                    <span class="person">{{ $approvalSignerName ?: ($vehicleLoan->approver?->name ?: 'Menunggu persetujuan') }}</span>
                    @if ($vehicleLoan->approved_at)
                        <div class="muted">{{ $vehicleLoan->approved_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</div>
                    @endif
                </td>
            </tr>
        </table>

        @if ($vehicleLoan->admin_notes || $vehicleLoan->rejection_reason || $vehicleLoan->cancellation_reason)
            <div class="note-box">
                @if ($vehicleLoan->admin_notes)
                    <strong>Catatan Administrator:</strong>
                    {!! nl2br(e($vehicleLoan->admin_notes)) !!}
                @endif
                @if ($vehicleLoan->rejection_reason)
                    @if ($vehicleLoan->admin_notes)<br>@endif
                    <strong>Alasan Penolakan:</strong>
                    {!! nl2br(e($vehicleLoan->rejection_reason)) !!}
                @endif
                @if ($vehicleLoan->cancellation_reason)
                    @if ($vehicleLoan->admin_notes || $vehicleLoan->rejection_reason)<br>@endif
                    <strong>Alasan Pembatalan:</strong>
                    {!! nl2br(e($vehicleLoan->cancellation_reason)) !!}
                @endif
            </div>
        @endif
    </section>

    <section class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-card">
                        <div class="signature-role">Peminjam</div>
                        <div class="signature-image">
                            @if ($submissionSignature)
                                <img src="{{ $submissionSignature }}" alt="Tanda tangan peminjam">
                            @else
                                <div class="signature-placeholder">Belum ditandatangani</div>
                            @endif
                        </div>
                        <div class="signature-name">{{ $vehicleLoan->borrower_name_snapshot }}</div>
                        <div class="signature-meta">{{ $vehicleLoan->employee_number_snapshot ?: '-' }}</div>
                    </div>
                </td>
                <td>
                    <div class="signature-card">
                        <div class="signature-role">Penyetuju / Pengelola</div>
                        <div class="signature-image">
                            @if ($approvalSignature)
                                <img src="{{ $approvalSignature }}" alt="Tanda tangan Penyetuju/Pengelola">
                            @else
                                <div class="signature-placeholder">Menunggu persetujuan Administrator</div>
                            @endif
                        </div>
                        <div class="signature-name">{{ $approvalSignerName ?: '................................' }}</div>
                        <div class="signature-meta">{{ $approvalSignerEmployeeNumber ?: '' }}</div>
                    </div>
                </td>
                <td>
                    @php
                        $kasubbag = ($documentSignatories ?? [])['kasubbag']
                            ?? null;
                    @endphp
                    <div class="signature-card">
                        <div class="signature-role">
                            Mengetahui / {{ $kasubbag['role_label'] ?? 'Kasubbag Umum' }}
                        </div>
                        <div class="signature-image"></div>
                        <div class="signature-name">
                            {{ $kasubbag['name'] ?? '................................' }}
                        </div>
                        <div class="signature-meta">
                            NIP/Nomor Pegawai: {{ $kasubbag['employee_number'] ?? '................................' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </section>

    <div class="verification-note">
        Dokumen elektronik SIMANTAP. Keaslian dokumen dapat diperiksa melalui QR verifikasi di bagian atas.
        Nomor referensi: {{ $vehicleLoan->loan_number }} · ID dokumen: {{ $vehicleLoan->public_id }}.
    </div>

    <div class="footer">
        {{ $institutionShortName }} · SIMANTAP · {{ $vehicleLoan->loan_number }}
    </div>
</body>
</html>
