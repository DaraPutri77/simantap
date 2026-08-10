<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $vehicleLoan->loan_number }}</title>
    <style>
        @page { margin: 24mm 18mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 10pt; }
        h1, h2, p { margin: 0; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; }
        .header h1 { font-size: 15pt; margin-top: 5px; }
        .header p { font-size: 9pt; margin-top: 3px; color: #475569; }
        .institution-logo { display: block; width: 230px; height: auto; margin: 0 0 7px; }
        .meta { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .meta td { padding: 5px 0; vertical-align: top; }
        .meta .label { width: 31%; color: #475569; font-weight: bold; }
        .box { margin-top: 16px; border: 1px solid #94a3b8; border-radius: 6px; padding: 12px; }
        .box h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .detail { width: 100%; border-collapse: collapse; }
        .detail th, .detail td { border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        .detail th { width: 31%; text-align: left; background: #f1f5f9; }
        .status { display: inline-block; border: 1px solid #64748b; border-radius: 10px; padding: 3px 8px; font-weight: bold; }
        .signature-table { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
        .signature-table td { width: 50%; text-align: center; vertical-align: top; padding: 8px 14px; }
        .signature-space { height: 78px; padding-top: 8px; }
        .signature-space img { max-width: 170px; max-height: 70px; }
        .name { font-weight: bold; text-decoration: underline; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #64748b; }
    </style>
</head>
<body>
    <header class="header">
        <img class="institution-logo" src="{{ public_path(config('simantap.institution.logo')) }}" alt="{{ $institutionName }}">
        <h1>FORM PEMINJAMAN KENDARAAN DINAS</h1>
        <p>Sistem Manajemen Aset dan Persediaan</p>
    </header>

    <table class="meta">
        <tr>
            <td class="label">Nomor Peminjaman</td>
            <td>: {{ $vehicleLoan->loan_number }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td>: <span class="status">{{ $vehicleLoan->status->label() }}</span></td>
        </tr>
        <tr>
            <td class="label">Tanggal Cetak</td>
            <td>: {{ now()->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</td>
        </tr>
    </table>

    <section class="box">
        <h2>Identitas Peminjam</h2>
        <table class="detail">
            <tr><th>Nama</th><td>{{ $vehicleLoan->borrower_name_snapshot }}</td></tr>
            <tr><th>NIP/Nomor Pegawai</th><td>{{ $vehicleLoan->employee_number_snapshot ?: '-' }}</td></tr>
            <tr><th>Unit Kerja</th><td>{{ $vehicleLoan->work_unit_snapshot ?: '-' }}</td></tr>
            <tr><th>Nomor Telepon</th><td>{{ $vehicleLoan->phone_snapshot }}</td></tr>
        </table>
    </section>

    <section class="box">
        <h2>Rincian Peminjaman</h2>
        <table class="detail">
            <tr>
                <th>Kendaraan Dinas</th>
                <td>
                    {{ $vehicleLoan->vehicle_code_snapshot }} ·
                    {{ $vehicleLoan->license_plate_snapshot }} ·
                    {{ $vehicleLoan->vehicle_name_snapshot }}
                </td>
            </tr>
            <tr>
                <th>Mulai</th>
                <td>{{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</td>
            </tr>
            <tr>
                <th>Selesai</th>
                <td>{{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB</td>
            </tr>
            <tr><th>Tujuan</th><td>{{ $vehicleLoan->destination }}</td></tr>
            <tr><th>Keperluan</th><td>{!! nl2br(e($vehicleLoan->purpose)) !!}</td></tr>
            <tr><th>Keterangan</th><td>{{ $vehicleLoan->reason ?: '-' }}</td></tr>
        </table>
    </section>

    @if ($vehicleLoan->rejection_reason || $vehicleLoan->cancellation_reason || $vehicleLoan->admin_notes)
        <section class="box">
            <h2>Catatan Proses</h2>
            <table class="detail">
                @if ($vehicleLoan->admin_notes)
                    <tr><th>Administrator</th><td>{!! nl2br(e($vehicleLoan->admin_notes)) !!}</td></tr>
                @endif
                @if ($vehicleLoan->rejection_reason)
                    <tr><th>Alasan Penolakan</th><td>{!! nl2br(e($vehicleLoan->rejection_reason)) !!}</td></tr>
                @endif
                @if ($vehicleLoan->cancellation_reason)
                    <tr><th>Alasan Pembatalan</th><td>{!! nl2br(e($vehicleLoan->cancellation_reason)) !!}</td></tr>
                @endif
            </table>
        </section>
    @endif

    <table class="signature-table">
        <tr>
            <td>Peminjam,</td>
            <td>Administrator,</td>
        </tr>
        <tr>
            <td class="signature-space">
                @if ($submissionSignature)
                    <img src="{{ $submissionSignature }}" alt="Tanda tangan peminjam">
                @endif
            </td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td><span class="name">{{ $vehicleLoan->borrower_name_snapshot }}</span></td>
            <td><span class="name">{{ $vehicleLoan->approver?->name ?: '................................' }}</span></td>
        </tr>
        <tr>
            <td>{{ $vehicleLoan->employee_number_snapshot ?: '-' }}</td>
            <td>{{ $vehicleLoan->approver?->position ?: '' }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen dibuat oleh SIMANTAP · {{ $vehicleLoan->public_id }}
    </div>
</body>
</html>
