<x-layouts.app :title="$asset->asset_code" header="Detail Aset Perangkat" eyebrow="Pemeliharaan">
    <section class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Master Aset Operasional</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{{ $asset->asset_code }}</h1>
                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 ring-1 ring-inset ring-slate-300">{{ $asset->type->label() }}</span>
                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 ring-1 ring-inset ring-slate-300">{{ $asset->status->label() }}</span>
            </div>
            <p class="mt-2 text-sm font-bold text-slate-700">{{ $asset->displayName() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('operational-assets.index') }}" class="secondary-button sm:w-auto">Daftar Aset</a>
            <a href="{{ route('operational-assets.edit', $asset) }}" class="secondary-button sm:w-auto">Edit</a>
            @if ($asset->is_active && ! $hasActiveMaintenance)
                <a href="{{ route('maintenance-records.create', ['operational_asset' => $asset->public_id]) }}" class="primary-button sm:w-auto">Buat Pemeliharaan</a>
            @endif
        </div>
    </section>

    @if (session('status'))
        <div class="alert-success mt-6"><strong>Berhasil.</strong><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="alert-danger mt-6"><strong>Tindakan belum dapat diproses.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="panel p-5 sm:p-6 lg:col-span-2">
            <h2 class="text-lg font-black text-slate-950">Identitas dan Referensi BMN</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-black text-slate-500">Kode Barang BMN</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->bmn_code ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">NUP</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->nup ?: '-' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-black text-slate-500">Kode Register</dt><dd class="mt-1 break-all text-sm font-semibold text-slate-900">{{ $asset->register_code ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Merek</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->brand }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Model</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->model ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Nomor Seri</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->serial_number ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Tahun Perolehan</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->acquisition_year ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Lokasi Ruang</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->location ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Penanggung Jawab</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $asset->responsible_person ?: '-' }}</dd></div>
            </dl>
            @if ($asset->notes)
                <div class="mt-6 rounded-2xl bg-slate-50 p-4"><p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">Catatan</p><p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $asset->notes }}</p></div>
            @endif
        </article>

        <aside class="space-y-4">
            <article class="panel p-5 sm:p-6">
                <h2 class="text-lg font-black text-slate-950">Status Master</h2>
                <dl class="mt-5 space-y-3">
                    <div><dt class="text-xs font-black text-slate-500">Operasional</dt><dd class="mt-1 text-sm font-semibold">{{ $asset->status->label() }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Master</dt><dd class="mt-1 text-sm font-semibold">{{ $asset->is_active ? 'Aktif' : 'Nonaktif' }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Riwayat Pemeliharaan</dt><dd class="mt-1 text-sm font-semibold">{{ number_format($asset->maintenance_records_count, 0, ',', '.') }} tiket</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Terakhir Diperbarui</dt><dd class="mt-1 text-sm font-semibold">{{ $asset->updated_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB</dd></div>
                </dl>

                @if ($asset->is_active)
                    <form method="POST" action="{{ route('operational-assets.deactivate', $asset) }}" class="mt-5" data-confirm-message="Nonaktifkan aset perangkat ini?">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="secondary-button text-red-700">Nonaktifkan Aset</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('operational-assets.activate', $asset) }}" class="mt-5">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="primary-button">Aktifkan Kembali</button>
                    </form>
                @endif
            </article>
        </aside>
    </section>

    <section class="panel mt-6 p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-black text-slate-950">Riwayat Pemeliharaan</h2>
            <a href="{{ route('maintenance-records.index', ['q' => $asset->asset_code]) }}" class="text-xs font-black text-sky-700 hover:text-sky-900">Lihat Semua</a>
        </div>
        <div class="mt-5 divide-y divide-slate-100">
            @forelse ($asset->maintenanceRecords as $record)
                <a href="{{ route('maintenance-records.show', $record) }}" class="flex flex-col gap-2 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-sm font-black text-slate-900">{{ $record->maintenance_number }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $record->maintenance_type }} · {{ $record->reported_date->translatedFormat('d M Y') }}</p></div>
                    <span class="status-badge">{{ $record->status->label() }}</span>
                </a>
            @empty
                <p class="empty-state">Belum ada riwayat pemeliharaan.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
