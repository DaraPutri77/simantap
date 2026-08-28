@php
    $officials = $documentSignatories ?? [];
    $kasubbag = $officials['kasubbag'] ?? null;
    $administrator = $officials['administrator']
        ?? $officials['inventory_manager']
        ?? null;
@endphp

<section style="margin-top: 8px; page-break-inside: avoid;">
    <div style="margin-bottom: 3px; color: #526078; font-size: 6.5pt; font-weight: bold; letter-spacing: .3px; text-transform: uppercase;">
        Pengesahan Pejabat Aktif
    </div>
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
        <tr>
            @foreach ([$kasubbag, $administrator] as $official)
                <td style="width: 50%; border: 1px solid #cbd5e1; padding: 4px 8px; text-align: center; vertical-align: top;">
                    <div style="min-height: 15px; color: #334155; font-size: 7pt; font-weight: bold;">
                        {{ $official['role_label'] ?? 'Pejabat belum dikonfigurasi' }}
                    </div>
                    <div style="height: 27px;"></div>
                    <div style="color: #172033; font-size: 7pt; font-weight: bold; text-decoration: underline;">
                        {{ $official['name'] ?? '........................................' }}
                    </div>
                    <div style="margin-top: 1px; color: #64748b; font-size: 6.2pt;">
                        NIP/Nomor Pegawai: {{ $official['employee_number'] ?? '................................' }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
    <div style="margin-top: 2px; color: #64748b; font-size: 5.8pt; text-align: center;">
        Ruang pengesahan manual; tidak menggantikan tanda tangan digital dan riwayat transaksi SIMANTAP.
    </div>
</section>
