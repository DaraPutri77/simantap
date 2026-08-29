@php
    $officials = $documentSignatories ?? [];
    $kasubbag = $officials['kasubbag'] ?? null;
    $administrator = $officials['administrator']
        ?? $officials['inventory_manager']
        ?? null;
@endphp

<section style="margin-top: 10px; page-break-inside: avoid;">

    <table style="
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
    ">
        <tr>
            @foreach ([$kasubbag, $administrator] as $official)

                    <td style="
                width:50%;
                padding: 6px 25px 8px;
                text-align:center;
                vertical-align:top;
                border:none;
            ">

                <div style="
                    color:#334155;
                    font-size:7pt;
                    font-weight:bold;
                    margin-bottom:18px;
                ">
                    {{ $official['role_label'] ?? 'Pejabat belum dikonfigurasi' }}
                </div>


               <div style="
                height:30px;
            ">
            </div>


                <div style="
                    color:#172033;
                    font-size:7pt;
                    font-weight:bold;
                    margin-bottom:4px;
                ">
                    {{ $official['name'] ?? '-' }}
                </div>


                <div style="
                    color:#64748b;
                    font-size:6.2pt;
                ">
                    NIP/Nomor Pegawai:
                    {{ $official['employee_number'] ?? '-' }}
                </div>

            </td>

            @endforeach
        </tr>
    </table>


   <div style="margin-top: 2px; color: #64748b; font-size: 5.8pt; text-align: center;">
    Pengesahan administratif.
</div>

</section>