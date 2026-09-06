<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Kendali Persediaan</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: sans-serif; font-size: 8pt; color: #000; }
        h1 { text-align: center; font-size: 14pt; margin-bottom: 5px; }
        p { text-align: center; margin-top: 0; font-size: 10pt; color: #555; }
        .meta-info { margin-bottom: 15px; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; }
        th { background-color: #f3f4f6; font-weight: bold; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>KARTU KENDALI PERSEDIAAN (LEDGER GLOBAL)</h1>
    <p>Periode: {{ $periodLabel }}</p>

    <div class="meta-info">
        Dicetak pada: {{ $generatedAt->format('d/m/Y H:i') }} WIB<br>
        Berdasarkan Filter: 
        Pencarian ({{ $filters['search'] ?: 'Tidak ada' }}), 
        Arah ({{ $filters['direction'] ? ucfirst($filters['direction']) : 'Semua' }})
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nomor Transaksi</th>
                <th>Barang</th>
                <th>Jenis</th>
                <th>Awal</th>
                <th>Masuk</th>
                <th>Keluar</th>
                <th>Akhir</th>
                <th>Satuan</th>
                <th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $index => $movement)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $movement->transaction_date->timezone($displayTimezone)->format('d/m/Y H:i') }}</td>
                    <td>{{ $movement->transaction_number }}</td>
                    <td class="text-left">
                        {{ $movement->item?->item_code ?: '-' }}<br>
                        <strong>{{ $movement->item?->name ?: 'Barang dihapus' }}</strong>
                    </td>
                    <td>{{ $movement->movement_type?->label() ?: '-' }}</td>
                    <td class="text-right">{{ number_format((float) $movement->stock_before, 2, ',', '.') }}</td>
                    <td class="text-right" style="color: green;">{{ (float) $movement->quantity_in > 0 ? '+'.number_format((float) $movement->quantity_in, 2, ',', '.') : '-' }}</td>
                    <td class="text-right" style="color: red;">{{ (float) $movement->quantity_out > 0 ? '-'.number_format((float) $movement->quantity_out, 2, ',', '.') : '-' }}</td>
                    <td class="text-right"><strong>{{ number_format((float) $movement->stock_after, 2, ',', '.') }}</strong></td>
                    <td>{{ $movement->item?->unit?->symbol ?: '-' }}</td>
                    <td>{{ $movement->creator?->name ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Tidak ada pergerakan stok yang sesuai dengan filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>