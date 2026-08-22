<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Stok Persediaan - {{ $item->item_code }}</title>
    <style>
        @page {
            margin: 12mm 11mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            line-height: 1.35;
        }

        h1,
        p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            text-align: center;
        }

        .logo {
            display: block;
            width: 220px;
            height: auto;
            margin: 0 auto 6px;
        }

        .header h1 {
            margin-top: 4px;
            font-size: 14pt;
            letter-spacing: .2px;
        }

        .header p {
            margin-top: 3px;
            color: #4b5563;
            font-size: 8pt;
        }

        .meta {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        .meta .label {
            width: 22%;
            color: #4b5563;
            font-weight: bold;
        }

        .summary {
            width: 100%;
            margin-top: 9px;
            border-collapse: collapse;
        }

        .summary td {
            width: 25%;
            border: 1px solid #9ca3af;
            padding: 6px 7px;
            vertical-align: top;
        }

        .summary .label {
            color: #6b7280;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .summary .value {
            margin-top: 3px;
            font-size: 9pt;
            font-weight: bold;
        }

        .ledger {
            width: 100%;
            margin-top: 11px;
            border-collapse: collapse;
        }

        .ledger thead {
            display: table-header-group;
        }

        .ledger tr {
            page-break-inside: avoid;
        }

        .ledger th,
        .ledger td {
            border: 1px solid #6b7280;
            padding: 4px 4px;
            vertical-align: top;
        }

        .ledger th {
            background: #e5e7eb;
            font-size: 7pt;
            text-align: center;
        }

        .ledger .date {
            width: 15%;
        }

        .ledger .document {
            width: 22%;
        }

        .ledger .description {
            width: 31%;
        }

        .ledger .number {
            width: 10.66%;
            text-align: right;
        }

        .muted {
            color: #6b7280;
            font-size: 6.8pt;
        }

        .empty {
            text-align: center;
            color: #6b7280;
        }

        .signature-section {
            margin-top: 18mm;
            page-break-inside: avoid;
        }

        .signature-date {
            margin-bottom: 5mm;
            text-align: right;
            font-size: 8pt;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 50%;
            padding: 0 10mm;
            text-align: center;
            vertical-align: top;
        }

        .signature-role {
            min-height: 10mm;
            font-weight: bold;
            line-height: 1.4;
        }

        .signature-space {
            height: 24mm;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-nip {
            margin-top: 2px;
            font-size: 7pt;
        }

        .footer {
            position: fixed;
            right: 0;
            bottom: -8mm;
            left: 0;
            color: #6b7280;
            font-size: 6.5pt;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <img
            class="logo"
            src="{{ public_path(config('simantap.institution.logo')) }}"
            alt="{{ config('simantap.institution.name') }}"
        >

        <h1>KARTU STOK PERSEDIAAN</h1>
        <p>{{ config('simantap.institution.name') }}</p>
    </header>

    <table class="meta">
        <tr>
            <td class="label">Kode Barang</td>
            <td>: {{ $item->item_code }}</td>
        </tr>
        <tr>
            <td class="label">Nama Barang</td>
            <td>: {{ $item->name }}</td>
        </tr>
        <tr>
            <td class="label">Kategori</td>
            <td>: {{ $item->category?->name ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Satuan</td>
            <td>: {{ $item->unit?->symbol ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Lokasi Penyimpanan</td>
            <td>: {{ $item->storage_location ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td>: {{ $periodLabel }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <p class="label">Saldo Awal</p>
                <p class="value">
                    {{ number_format($openingBalance, 2, ',', '.') }}
                    {{ $item->unit?->symbol }}
                </p>
            </td>
            <td>
                <p class="label">Masuk</p>
                <p class="value">
                    {{ number_format($totalIn, 2, ',', '.') }}
                    {{ $item->unit?->symbol }}
                </p>
            </td>
            <td>
                <p class="label">Keluar</p>
                <p class="value">
                    {{ number_format($totalOut, 2, ',', '.') }}
                    {{ $item->unit?->symbol }}
                </p>
            </td>
            <td>
                <p class="label">Saldo Akhir</p>
                <p class="value">
                    {{ number_format($closingBalance, 2, ',', '.') }}
                    {{ $item->unit?->symbol }}
                </p>
            </td>
        </tr>
    </table>

    <table class="ledger">
        <thead>
            <tr>
                <th class="date">Tanggal</th>
                <th class="document">Nomor Dokumen</th>
                <th class="description">Uraian</th>
                <th class="number">Masuk</th>
                <th class="number">Keluar</th>
                <th class="number">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>
                        {{ $movement->transaction_date
                            ->copy()
                            ->timezone($displayTimezone)
                            ->translatedFormat('d/m/Y H:i') }}
                        WIB
                    </td>

                    <td>
                        {{ $movement->reference_number
                            ?: $movement->transaction_number }}
                    </td>

                    <td>
                        <strong>{{ $movement->movement_type->label() }}</strong>

                        @if ($movement->description)
                            <br>
                            <span class="muted">
                                {{ $movement->description }}
                            </span>
                        @endif
                    </td>

                    <td class="number">
                        {{ (float) $movement->quantity_in > 0
                            ? number_format((float) $movement->quantity_in, 2, ',', '.')
                            : '-' }}
                    </td>

                    <td class="number">
                        {{ (float) $movement->quantity_out > 0
                            ? number_format((float) $movement->quantity_out, 2, ',', '.')
                            : '-' }}
                    </td>

                    <td class="number">
                        {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">
                        Tidak ada pergerakan stok pada periode yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 8px; font-size: 7pt;">
        Validasi saldo:
        <strong>
            {{ $balanceConsistent ? 'Konsisten' : 'PERLU AUDIT' }}
        </strong>
    </p>

    <p class="muted" style="margin-top: 4px;">
        Sumber data: ledger transaksi SIMANTAP.
        Kartu ini dibentuk otomatis dari stock_movements dan bukan hasil input manual.
    </p>

    @php
        $kasubbag = $documentSignatories['kasubbag'] ?? null;
        $inventoryManager = $documentSignatories['inventory_manager'] ?? null;
    @endphp

    <section class="signature-section">
        <p class="signature-date">
            Jombang, {{ $generatedAt->translatedFormat('d F Y') }}
        </p>

        <table class="signature-table">
            <tbody>
                <tr>
                    <td>
                        <div>Mengetahui,</div>
                        <div class="signature-role">
                            {{ $kasubbag['role_label'] ?? 'Kasubbag Umum' }}
                        </div>
                    </td>

                    <td>
                        <div>&nbsp;</div>
                        <div class="signature-role">
                            {{ $inventoryManager['role_label'] ?? 'Pengelola Barang' }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td>
                        <div class="signature-space"></div>
                    </td>
                    <td>
                        <div class="signature-space"></div>
                    </td>
                </tr>

                <tr>
                    <td>
                        <div class="signature-name">
                            {{ $kasubbag['name'] ?? '........................................' }}
                        </div>
                        <div class="signature-nip">
                            NIP. ....................................
                        </div>
                    </td>

                    <td>
                        <div class="signature-name">
                            {{ $inventoryManager['name'] ?? '........................................' }}
                        </div>
                        <div class="signature-nip">
                            NIP. ....................................
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>

    <div class="footer">
        Dicetak dari SIMANTAP ·
        {{ $generatedAt->translatedFormat('d/m/Y H:i:s') }} WIB
    </div>
</body>
</html>