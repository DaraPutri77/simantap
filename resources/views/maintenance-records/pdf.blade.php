<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $maintenanceRecord->maintenance_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 8mm 9mm 9mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            color: #172033;
            font-family: DejaVu Sans, sans-serif;
            font-size: 6.8pt;
            line-height: 1.25;
        }
        p, h1, h2 { margin: 0; }
        .header {
            position: relative;
            height: 23mm;
            border-bottom: 1.2mm solid #163A5F;
        }
        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 46mm;
            max-height: 17mm;
            object-fit: contain;
        }
        .heading {
            margin: 0 27mm 0 50mm;
            padding-top: 1.5mm;
            text-align: center;
        }
        .heading h1 {
            color: #163A5F;
            font-size: 13.5pt;
            letter-spacing: .4px;
        }
        .heading p {
            margin-top: 1mm;
            color: #52637a;
            font-size: 7pt;
            font-weight: bold;
        }
        .verification {
            position: absolute;
            top: 0;
            right: 0;
            width: 25mm;
            text-align: center;
        }
        .verification img {
            width: 14mm;
            height: 14mm;
        }
        .verification div {
            color: #52637a;
            font-size: 4.3pt;
            line-height: 1.1;
        }
        .meta {
            width: 100%;
            margin-top: 2.5mm;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .meta td {
            border: .25mm solid #cad4df;
            padding: 1.6mm 2mm;
            vertical-align: top;
        }
        .meta-label {
            color: #66758a;
            font-size: 5.2pt;
            font-weight: bold;
            letter-spacing: .25px;
            text-transform: uppercase;
        }
        .meta-value {
            margin-top: .5mm;
            color: #16233a;
            font-size: 7.2pt;
            font-weight: bold;
        }
        .status {
            display: inline-block;
            padding: .8mm 2mm;
            color: #fff;
            background: #163A5F;
            border-left: 1.2mm solid #C9A227;
        }
        .section { margin-top: 2.6mm; page-break-inside: avoid; }
        .section-title {
            padding: 1mm 1.8mm;
            color: #fff;
            background: #163A5F;
            border-left: 1.3mm solid #C9A227;
            font-size: 6.3pt;
            font-weight: bold;
            letter-spacing: .35px;
            text-transform: uppercase;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .grid th, .grid td {
            border: .25mm solid #cad4df;
            padding: 1.2mm 1.6mm;
            vertical-align: top;
        }
        .grid th {
            width: 19%;
            color: #52637a;
            background: #f2f5f8;
            font-size: 5.5pt;
            text-align: left;
        }
        .narrative {
            width: 100%;
            border-collapse: separate;
            border-spacing: 1.5mm 0;
            table-layout: fixed;
        }
        .narrative td {
            width: 25%;
            padding: 1.5mm;
            border: .25mm solid #cad4df;
            vertical-align: top;
        }
        .narrative-label {
            color: #52637a;
            font-size: 5.2pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .narrative-text {
            height: 15mm;
            margin-top: .8mm;
            overflow: hidden;
            font-size: 6pt;
            line-height: 1.22;
        }
        .evidence-wrap {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .evidence-wrap td {
            width: 50%;
            border: .25mm solid #cad4df;
            padding: 1.5mm;
            vertical-align: top;
        }
        .thumb {
            float: left;
            width: 25mm;
            height: 17mm;
            margin-right: 2mm;
            border: .25mm solid #cad4df;
            object-fit: cover;
        }
        .evidence-title { font-size: 6pt; font-weight: bold; }
        .evidence-meta {
            margin-top: .8mm;
            color: #66758a;
            font-size: 4.8pt;
            word-break: break-all;
        }
        .history {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .history th, .history td {
            border: .25mm solid #cad4df;
            padding: 1mm 1.2mm;
            vertical-align: top;
        }
        .history th {
            color: #fff;
            background: #4e6177;
            font-size: 5.2pt;
            text-align: left;
        }
        .history td { font-size: 5.2pt; }
        .people {
            width: 100%;
            border-collapse: separate;
            border-spacing: 1.5mm 0;
            table-layout: fixed;
        }
        .people td {
            width: 33.33%;
            height: 16mm;
            padding: 1.5mm;
            border: .25mm solid #cad4df;
            text-align: center;
            vertical-align: top;
        }
        .person-role {
            color: #52637a;
            font-size: 5.2pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .person-name { margin-top: 3mm; font-weight: bold; }
        .person-meta { margin-top: .5mm; color: #66758a; font-size: 5pt; }
        .footer {
            position: fixed;
            right: 0;
            bottom: -5mm;
            left: 0;
            padding-top: 1mm;
            border-top: .25mm solid #cad4df;
            color: #66758a;
            font-size: 4.8pt;
            text-align: center;
        }
        .gold { color: #9a7915; }
    </style>
</head>
<body>
    @php
        $subject = $maintenanceRecord->vehicle ?: $maintenanceRecord->operationalAsset;
        $subjectCode = $maintenanceRecord->vehicle?->vehicle_code
            ?: $maintenanceRecord->operationalAsset?->asset_code;
        $subjectName = $maintenanceRecord->vehicle?->displayName()
            ?: $maintenanceRecord->operationalAsset?->displayName();
        $subjectReference = $maintenanceRecord->vehicle?->license_plate
            ?: $maintenanceRecord->operationalAsset?->administrativeCode();
        $historyRows = $maintenanceRecord->statusHistories->take(-5)->values();
        $imageAttachments = $maintenanceRecord->attachments
            ->filter(fn ($attachment) => $attachment->isImage())
            ->take(2)
            ->values();
    @endphp

    <header class="header">
        <img
            class="logo"
            src="{{ public_path(config('simantap.institution.logo')) }}"
            alt="Logo resmi BPS"
        >
        <div class="heading">
            <h1>LAPORAN PEMELIHARAAN</h1>
            <p>{{ $institutionName }} · SIMANTAP</p>
        </div>
        <div class="verification">
            <img src="{{ $verificationQrDataUri }}" alt="QR verifikasi dokumen">
            <div>
                <strong>VERIFIKASI</strong><br>
                Versi {{ $documentVerification->version }}<br>
                {{ substr($documentVerification->payload_hash, 0, 12) }}
            </div>
        </div>
    </header>

    <table class="meta">
        <tr>
            <td style="width: 30%">
                <div class="meta-label">Nomor Pemeliharaan</div>
                <div class="meta-value">{{ $maintenanceRecord->maintenance_number }}</div>
            </td>
            <td style="width: 25%">
                <div class="meta-label">Status</div>
                <div class="meta-value"><span class="status">{{ $maintenanceRecord->status->label() }}</span></div>
            </td>
            <td style="width: 20%">
                <div class="meta-label">Tanggal Laporan</div>
                <div class="meta-value">{{ $maintenanceRecord->reported_date->translatedFormat('d M Y') }}</div>
            </td>
            <td style="width: 25%">
                <div class="meta-label">Jenis Subjek</div>
                <div class="meta-value">{{ $maintenanceRecord->subjectType()->label() }}</div>
            </td>
        </tr>
    </table>

    <section class="section">
        <div class="section-title">Identitas Aset / Kendaraan</div>
        <table class="grid">
            <tr>
                <th>Kode</th>
                <td>{{ $subjectCode ?: '-' }}</td>
                <th>Referensi</th>
                <td>{{ $subjectReference ?: '-' }}</td>
            </tr>
            <tr>
                <th>Nama / Tipe</th>
                <td>{{ $subjectName ?: $maintenanceRecord->subjectSnapshot() }}</td>
                <th>Status Sekarang</th>
                <td>{{ $subject?->status?->label() ?: '-' }}</td>
            </tr>
            <tr>
                <th>Sumber Peminjaman</th>
                <td>{{ $maintenanceRecord->sourceVehicleLoan?->loan_number ?: '-' }}</td>
                <th>Lokasi / Odometer</th>
                <td>
                    {{ $maintenanceRecord->operationalAsset?->location
                        ?: ($maintenanceRecord->vehicle
                            ? number_format((float) $maintenanceRecord->vehicle->current_odometer, 1, ',', '.').' km'
                            : '-') }}
                </td>
            </tr>
        </table>
    </section>

    <section class="section">
        <div class="section-title">Rincian Pemeliharaan</div>
        <table class="grid">
            <tr>
                <th>Jenis Pekerjaan</th>
                <td>{{ $maintenanceRecord->maintenance_type }}</td>
                <th>Penyedia Jasa</th>
                <td>{{ $maintenanceRecord->service_provider ?: '-' }}</td>
            </tr>
            <tr>
                <th>Mulai</th>
                <td>{{ $maintenanceRecord->start_date?->translatedFormat('d M Y') ?: '-' }}</td>
                <th>Selesai / Evaluasi</th>
                <td>{{ $maintenanceRecord->completion_date?->translatedFormat('d M Y') ?: '-' }}</td>
            </tr>
            <tr>
                <th>Biaya</th>
                <td>{{ $maintenanceRecord->cost !== null ? 'Rp '.number_format((float) $maintenanceRecord->cost, 0, ',', '.') : '-' }}</td>
                <th>Catatan Persetujuan</th>
                <td>{{ \Illuminate\Support\Str::limit($maintenanceRecord->approval_notes ?: '-', 120) }}</td>
            </tr>
        </table>
        <table class="narrative">
            <tr>
                <td>
                    <div class="narrative-label">Keluhan</div>
                    <div class="narrative-text">{{ \Illuminate\Support\Str::limit($maintenanceRecord->complaint, 360) }}</div>
                </td>
                <td>
                    <div class="narrative-label">Kondisi Awal</div>
                    <div class="narrative-text">{{ \Illuminate\Support\Str::limit($maintenanceRecord->initial_condition, 360) }}</div>
                </td>
                <td>
                    <div class="narrative-label">Hasil Pekerjaan</div>
                    <div class="narrative-text">{{ \Illuminate\Support\Str::limit($maintenanceRecord->result ?: '-', 360) }}</div>
                </td>
                <td>
                    <div class="narrative-label">Kondisi Akhir</div>
                    <div class="narrative-text">{{ \Illuminate\Support\Str::limit($maintenanceRecord->final_condition ?: $maintenanceRecord->cancellation_reason ?: '-', 360) }}</div>
                </td>
            </tr>
        </table>
    </section>

    <section class="section">
        <div class="section-title">Bukti Digital Terverifikasi</div>
        <table class="evidence-wrap">
            <tr>
                @forelse ($imageAttachments as $attachment)
                    <td>
                        @if ($evidenceData[$attachment->getKey()] ?? null)
                            <img class="thumb" src="{{ $evidenceData[$attachment->getKey()] }}" alt="{{ $attachment->file_category->label() }}">
                        @endif
                        <div class="evidence-title">{{ $attachment->file_category->label() }}</div>
                        <div class="evidence-meta">
                            {{ \Illuminate\Support\Str::limit($attachment->original_name, 34) }}<br>
                            SHA256 {{ substr($attachment->checksum, 0, 20) }}...
                        </div>
                    </td>
                @empty
                    <td colspan="2">Belum ada bukti foto pada transaksi ini.</td>
                @endforelse
                @if ($imageAttachments->count() === 1)
                    <td>
                        Total {{ $maintenanceRecord->attachments->count() }} bukti tersimpan.<br>
                        Seluruh checksum bukti termasuk dalam payload verifikasi dokumen.
                    </td>
                @endif
            </tr>
        </table>
    </section>

    <section class="section">
        <div class="section-title">Riwayat Status ({{ $maintenanceRecord->statusHistories->count() }} Entri)</div>
        <table class="history">
            <thead>
                <tr>
                    <th style="width: 18%">Waktu</th>
                    <th style="width: 21%">Status</th>
                    <th style="width: 22%">Pelaksana</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($historyRows as $history)
                    <tr>
                        <td>{{ $history->changed_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB</td>
                        <td>{{ $history->new_status->label() }}</td>
                        <td>{{ $history->changer?->name ?: 'Sistem' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($history->notes ?: '-', 110) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada riwayat status.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="section">
        <div class="section-title">Pihak Terkait</div>
        <table class="people">
            <tr>
                @foreach ([
                    ['role' => 'Pelapor', 'user' => $maintenanceRecord->reporter],
                    ['role' => 'Penanggung Jawab', 'user' => $maintenanceRecord->handler],
                    ['role' => 'Penyetuju', 'user' => $maintenanceRecord->approver],
                ] as $person)
                    <td>
                        <div class="person-role">{{ $person['role'] }}</div>
                        <div class="person-name">{{ $person['user']?->name ?: 'Belum ditetapkan' }}</div>
                        <div class="person-meta">
                            {{ $person['user']?->employee_number ?: '-' }}
                            @if ($person['user']?->position)
                                · {{ $person['user']->position }}
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </section>

    @include('pdf.official-signatories')

    <div class="footer">
        {{ $institutionShortName }} · SIMANTAP · Dokumen elektronik
        <span class="gold">SHA256 {{ $documentVerification->payload_hash }}</span>
        · Dicetak {{ $documentVerification->issued_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
        · QR bukan tanda tangan digital
    </div>
</body>
</html>
