<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $vehicleLoan->loan_number }} - Serah Terima Kendaraan</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1, h2, h3, p { margin: 0; }
        .header { text-align: center; margin-bottom: 18px; }
        .header h1 { font-size: 15px; margin-top: 5px; }
        .header p { margin-top: 4px; color: #475569; }
        .institution-logo { display: block; width: 230px; height: auto; margin: 0 0 7px; }
        .section { margin-top: 14px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; padding: 6px 8px; background: #e2e8f0; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        th { width: 28%; background: #f8fafc; text-align: left; }
        .status { font-weight: bold; }
        .evidence-grid { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .evidence-cell { width: 50%; border: 1px solid #cbd5e1; padding: 5px; text-align: center; }
        .evidence-cell img { max-width: 100%; max-height: 135px; }
        .evidence-label { margin-top: 4px; font-size: 8px; color: #475569; }
        .signature { max-height: 85px; max-width: 220px; }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .signature-space {
            height: 72px;
            vertical-align: middle !important;
        }
        .signature-space img {
            max-height: 65px;
            max-width: 180px;
        }
        .muted { color: #64748b; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $checkout = $vehicleLoan->checkoutCheck();
        $return = $vehicleLoan->returnCheck();
    @endphp

    <div class="header">
        <img class="institution-logo" src="{{ public_path(config('simantap.institution.logo')) }}" alt="{{ $institutionName }}">
        <h1>FORM SERAH TERIMA DAN PENGEMBALIAN KENDARAAN DINAS</h1>
        <p>{{ $vehicleLoan->loan_number }}</p>
    </div>

    <div class="section">
        <div class="section-title">Identitas Peminjaman</div>
        <table>
            <tr><th>Nama Peminjam</th><td>{{ $vehicleLoan->borrower_name_snapshot }}</td></tr>
            <tr><th>NIP/Nomor Pegawai</th><td>{{ $vehicleLoan->employee_number_snapshot ?: '-' }}</td></tr>
            <tr><th>Unit Kerja</th><td>{{ $vehicleLoan->work_unit_snapshot ?: '-' }}</td></tr>
            <tr><th>Nomor Telepon</th><td>{{ $vehicleLoan->phone_snapshot ?: '-' }}</td></tr>
            <tr><th>Kendaraan</th><td>{{ $vehicleLoan->vehicle_code_snapshot }} · {{ $vehicleLoan->license_plate_snapshot }} · {{ $vehicleLoan->vehicle_name_snapshot }}</td></tr>
            <tr><th>Tujuan</th><td>{{ $vehicleLoan->destination }}</td></tr>
            <tr><th>Keperluan</th><td>{{ $vehicleLoan->purpose }}</td></tr>
            <tr>
                <th>Rencana Pemakaian</th>
                <td>
                    {{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                    sampai
                    {{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                </td>
            </tr>
            <tr>
                <th>Aktual Pemakaian</th>
                <td>
                    {{ $vehicleLoan->actual_start_at?->timezone($displayTimezone)->translatedFormat('d M Y, H:i') ?: '-' }}{{ $vehicleLoan->actual_start_at ? ' WIB' : '' }}
                    sampai
                    {{ $vehicleLoan->actual_end_at?->timezone($displayTimezone)->translatedFormat('d M Y, H:i') ?: '-' }}{{ $vehicleLoan->actual_end_at ? ' WIB' : '' }}
                </td>
            </tr>
            <tr><th>Status</th><td class="status">{{ $vehicleLoan->status->label() }}</td></tr>
            <tr>
                <th>Keterlambatan</th>
                <td>
                    @if ($vehicleLoan->overdue_at)
                        Ditandai sejak {{ $vehicleLoan->overdue_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                    @else
                        Tidak ditandai terlambat
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @foreach ([
        ['title' => 'Pemeriksaan Kondisi Awal / Checkout', 'check' => $checkout],
        ['title' => 'Pemeriksaan Kondisi Akhir / Return', 'check' => $return],
    ] as $block)
        <div class="section">
            <div class="section-title">{{ $block['title'] }}</div>
            @if ($block['check'])
                @php $check = $block['check']; @endphp
                <table>
                    <tr>
                        <th>Diperiksa Oleh</th>
                        <td>
                            {{ $check->checker_name_snapshot ?: ($check->checker?->name ?: '-') }}
                        </td>
                    </tr>
                    <tr>
                        <th>NIP/Nomor Pegawai Pemeriksa</th>
                        <td>
                            {{ $check->checker_employee_number_snapshot ?: ($check->checker?->employee_number ?: '-') }}
                        </td>
                    </tr>
                    <tr><th>Waktu Pemeriksaan</th><td>{{ $check->checked_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB</td></tr>
                    <tr><th>Odometer</th><td>{{ number_format((float) $check->odometer, 1, ',', '.') }} km</td></tr>
                    <tr><th>Bahan Bakar</th><td>{{ $check->fuel_level }}%</td></tr>
                    <tr><th>Kondisi Keseluruhan</th><td>{{ $check->overall_condition->label() }}</td></tr>
                    <tr><th>Bodi</th><td>{{ $check->body_condition }}</td></tr>
                    <tr><th>Mesin</th><td>{{ $check->engine_condition }}</td></tr>
                    <tr><th>Ban</th><td>{{ $check->tire_condition }}</td></tr>
                    <tr><th>Kelengkapan</th><td>{{ $check->equipment_condition }}</td></tr>
                    <tr><th>Catatan Kerusakan</th><td>{{ $check->damage_notes ?: '-' }}</td></tr>
                    @if ($check->isCheckout())
                        <tr>
                            <th>Konfirmasi Peminjam</th>
                            <td>
                                {{ $check->borrower_confirmed_at?->timezone($displayTimezone)->translatedFormat('d M Y, H:i') ?: '-' }}{{ $check->borrower_confirmed_at ? ' WIB' : '' }}
                            </td>
                        </tr>
                    @endif
                </table>

                @if ($check->attachments->isNotEmpty())
                    <table class="evidence-grid">
                        @foreach ($check->attachments->chunk(2) as $row)
                            <tr>
                                @foreach ($row as $attachment)
                                    <td class="evidence-cell">
                                        @if ($evidenceData[$attachment->getKey()] ?? null)
                                            <img src="{{ $evidenceData[$attachment->getKey()] }}" alt="{{ $attachment->file_category->label() }}">
                                        @else
                                            <span class="muted">Bukti tidak dapat dirender</span>
                                        @endif
                                        <div class="evidence-label">{{ $attachment->file_category->label() }}</div>
                                    </td>
                                @endforeach
                                @if ($row->count() === 1)
                                    <td class="evidence-cell"></td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @endif
            @else
                <table><tr><td class="muted">Belum tersedia.</td></tr></table>
            @endif
        </div>
    @endforeach

    @php
        $checkoutCheckerName =
            $checkout?->checker_name_snapshot
            ?: $checkout?->checker?->name;

        $checkoutCheckerEmployeeNumber =
            $checkout?->checker_employee_number_snapshot
            ?: $checkout?->checker?->employee_number;

        $returnCheckerName =
            $return?->checker_name_snapshot
            ?: $return?->checker?->name;

        $returnCheckerEmployeeNumber =
            $return?->checker_employee_number_snapshot
            ?: $return?->checker?->employee_number;
    @endphp

    <div class="section">
        <div class="section-title">Pertanggungjawaban Serah Terima</div>

        <table class="signature-table">
            <tr>
                <td><strong>Peminjam</strong></td>
                <td><strong>Petugas/Pengelola</strong></td>
            </tr>

            <tr>
                <td class="signature-space">
                    @if ($pickupSignature)
                        <img
                            class="signature"
                            src="{{ $pickupSignature }}"
                            alt="Tanda tangan peminjam"
                        >
                    @endif
                </td>

                <td class="signature-space">
                    {{-- Ruang tanda tangan basah Petugas/Pengelola --}}
                </td>
            </tr>

            <tr>
                <td>
                    <strong>{{ $vehicleLoan->borrower_name_snapshot }}</strong>
                </td>
                <td>
                    <strong>
                        {{ $checkoutCheckerName ?: '................................' }}
                    </strong>
                </td>
            </tr>

            <tr>
                <td>
                    NIP/Nomor Pegawai:
                    {{ $vehicleLoan->employee_number_snapshot ?: '-' }}
                </td>
                <td>
                    NIP/Nomor Pegawai:
                    {{ $checkoutCheckerEmployeeNumber ?: '................................' }}
                </td>
            </tr>
        </table>
    </div>

    @if ($return)
        <div class="section">
            <div class="section-title">Pertanggungjawaban Pengembalian</div>

            <table class="signature-table">
                <tr>
                    <td><strong>Peminjam</strong></td>
                    <td><strong>Pemeriksa/Pengelola</strong></td>
                </tr>

                <tr>
                    <td class="signature-space">
                        {{-- Ruang tanda tangan basah peminjam --}}
                    </td>
                    <td class="signature-space">
                        {{-- Ruang tanda tangan basah Pemeriksa/Pengelola --}}
                    </td>
                </tr>

                <tr>
                    <td>
                        <strong>{{ $vehicleLoan->borrower_name_snapshot }}</strong>
                    </td>
                    <td>
                        <strong>
                            {{ $returnCheckerName ?: '................................' }}
                        </strong>
                    </td>
                </tr>

                <tr>
                    <td>
                        NIP/Nomor Pegawai:
                        {{ $vehicleLoan->employee_number_snapshot ?: '-' }}
                    </td>
                    <td>
                        NIP/Nomor Pegawai:
                        {{ $returnCheckerEmployeeNumber ?: '................................' }}
                    </td>
                </tr>
            </table>
        </div>
    @endif
    <div class="section page-break">
        <div class="section-title">Riwayat Status</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 18%">Waktu</th>
                    <th style="width: 22%">Status</th>
                    <th style="width: 20%">Pelaksana</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicleLoan->statusHistories as $history)
                    <tr>
                        <td>{{ $history->changed_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB</td>
                        <td>{{ $history->new_status->label() }}</td>
                        <td>{{ $history->changer?->name ?: 'Sistem' }}</td>
                        <td>{{ $history->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada riwayat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
