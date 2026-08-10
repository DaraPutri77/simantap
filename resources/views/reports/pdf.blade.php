<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 13mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 8pt; line-height: 1.35; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 9px; text-align: center; }
        .institution-logo { display: block; width: 230px; height: auto; margin: 0 0 6px; }
        .header h1 { margin-top: 5px; font-size: 15pt; letter-spacing: .2px; }
        .header p { margin-top: 3px; color: #475569; font-size: 8pt; }
        .meta { width: 100%; margin-top: 11px; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta .label { width: 18%; color: #475569; font-weight: bold; }
        .summary { width: 100%; margin-top: 12px; border-collapse: separate; border-spacing: 5px 0; }
        .summary td { width: 25%; border: 1px solid #cbd5e1; padding: 7px 9px; vertical-align: top; }
        .summary .label { color: #64748b; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .summary .value { margin-top: 3px; font-size: 11pt; font-weight: bold; }
        .report { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .report thead { display: table-header-group; }
        .report tr { page-break-inside: avoid; }
        .report th, .report td { border: 1px solid #64748b; padding: 5px 5px; vertical-align: top; }
        .report th { background: #e2e8f0; color: #0f172a; font-size: 7pt; text-align: left; }
        .report td { color: #1e293b; }
        .empty { margin-top: 22px; border: 1px solid #cbd5e1; padding: 18px; text-align: center; color: #64748b; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; color: #64748b; font-size: 7pt; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <img class="institution-logo" src="{{ public_path(config('simantap.institution.logo')) }}" alt="{{ $institutionName ?? config('simantap.institution.name') }}">
        <h1>{{ $title }}</h1>
        <p>{{ $description }}</p>
    </header>

    <table class="meta">
        <tr><td class="label">Periode</td><td>: {{ $periodLabel }} (WIB)</td></tr>
        <tr><td class="label">Tanggal Cetak</td><td>: {{ $generatedAt->translatedFormat('d F Y, H:i:s') }} WIB</td></tr>
        <tr><td class="label">Sumber</td><td>: SIMANTAP · data transaksi tersimpan</td></tr>
    </table>

    <table class="summary">
        <tr>
            @foreach ($summary as $card)
                <td>
                    <p class="label">{{ $card['label'] }}</p>
                    <p class="value">{{ $card['value'] }}</p>
                </td>
            @endforeach
            @for ($index = count($summary); $index < 4; $index++)
                <td></td>
            @endfor
        </tr>
    </table>

    @if ($rows === [])
        <div class="empty">Tidak ada data yang sesuai dengan parameter laporan.</div>
    @else
        <table class="report">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td>{{ $row[$column['key']] ?? '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Dokumen dibuat otomatis oleh SIMANTAP · {{ $generatedAt->translatedFormat('d/m/Y H:i:s') }} WIB</div>
</body>
</html>
