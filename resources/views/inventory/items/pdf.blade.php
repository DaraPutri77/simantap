<!DOCTYPE html>
<html>
<head>
    <title>Daftar Persediaan Barang - SIMANTAP</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        
        /* Layout Header KOP Surat */
        .header-table { width: 100%; border-bottom: 2px solid #0f172a; margin-bottom: 15px; padding-bottom: 10px; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .logo { width: 180px; } 
        .system-name { color: #475569; font-size: 10px; margin-top: 4px; display: block; }
        
        /* Judul Dokumen */
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 20px 0 15px 0; letter-spacing: 1px; }
        
        /* Layout Tabel Konten */
        .content-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .content-table th, .content-table td { border: 1px solid #cbd5e1; padding: 7px; text-align: left; }
        .content-table th { background-color: #f1f5f9; font-weight: bold; color: #0f172a; text-transform: uppercase; font-size: 10px; }
        
        /* Utilitas */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <!-- Memanggil logo BPS dari public/images sesuai folder SIMANTAP -->
                <img src="{{ public_path('images/bps-kabupaten-jombang.png') }}" class="logo" alt="Logo BPS Jombang">
                <span class="system-name">Sistem Manajemen Aset dan Persediaan</span>
            </td>
            <td style="width: 40%; text-align: right; font-size: 10px; line-height: 1.5;">
                <span class="font-bold">Tanggal Cetak</span><br>
                {{ \Carbon\Carbon::now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB
            </td>
        </tr>
    </table>

    <div class="title">DAFTAR PERSEDIAAN BARANG</div>

    <table class="content-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 20%;">Kode Barang</th>
                <th style="width: 30%;">Nama Barang</th>
                <th style="width: 15%;">Kategori</th>
                <th class="text-right" style="width: 10%;">Stok</th>
                <th style="width: 10%;">Satuan</th>
                <th class="text-center" style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td style="font-family: monospace;">{{ $item->item_code }}</td>
                <td class="font-bold">{{ $item->name }}</td>
                <td>{{ $item->category->name ?? '-' }}</td>
                <td class="text-right">{{ number_format((float) $item->available_stock, 2, ',', '.') }}</td>
                <td>{{ $item->unit->name ?? '-' }}</td>
                <td class="text-center">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>