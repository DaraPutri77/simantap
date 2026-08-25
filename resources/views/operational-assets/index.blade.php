<x-layouts.app title="Aset Perangkat" header="Aset Perangkat" eyebrow="Pemeliharaan">
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Master Aset Operasional</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">PC, Laptop, dan Printer</h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">Register individual perangkat yang dapat direkonsiliasi dengan Kode Barang, NUP, Kode Register, kondisi, dan lokasi pada daftar aset BMN.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">Daftar Pemeliharaan</a>
            <a href="{{ route('operational-assets.create') }}" class="primary-button sm:w-auto">Tambah Aset</a>
        </div>
    </section>

    @if (session('status'))
        <div class="alert-success mt-6"><strong>Berhasil.</strong><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="alert-danger mt-6"><strong>Tindakan belum dapat diproses.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Aset', 'value' => $summary['total']],
            ['label' => 'Tersedia', 'value' => $summary['available']],
            ['label' => 'Dalam Pemeliharaan', 'value' => $summary['maintenance']],
            ['label' => 'Perlu Perhatian', 'value' => $summary['attention']],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ number_format($card['value'], 0, ',', '.') }}</p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6 p-5 sm:p-6">
        <form method="GET" class="grid gap-4 lg:grid-cols-[1fr_180px_210px_150px_auto]">
            <div>
                <label for="q" class="form-label">Pencarian</label>
                <input id="q" name="q" type="search" value="{{ $filters['search'] }}" class="form-input" placeholder="Kode aset, BMN, NUP, merek, lokasi">
            </div>
            <div>
                <label for="type" class="form-label">Jenis</label>
                <select id="type" name="type" class="form-input">
                    <option value="">Semua jenis</option>
                    @foreach ($typeOptions as $typeOption)
                        <option value="{{ $typeOption->value }}" @selected($filters['type'] === $typeOption->value)>{{ $typeOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="active" class="form-label">Master</label>
                <select id="active" name="active" class="form-input">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['active'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['active'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="primary-button">Terapkan</button>
                <a href="{{ route('operational-assets.index') }}" class="secondary-button">Reset</a>
            </div>
        </form>
    </section>

    <section class="mt-6 space-y-4">
        @forelse ($assets as $asset)
            <article class="panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-black text-slate-950">{{ $asset->asset_code }}</h2>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-800 ring-1 ring-inset ring-slate-300">{{ $asset->type->label() }}</span>
                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-800 ring-1 ring-inset ring-slate-300">{{ $asset->status->label() }}</span>
                            @unless ($asset->is_active)
                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black text-red-800 ring-1 ring-inset ring-red-200">Master Nonaktif</span>
                            @endunless
                        </div>
                        <p class="mt-2 text-sm font-bold text-slate-800">{{ $asset->displayName() }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">{{ $asset->administrativeCode() }} · {{ $asset->location ?: 'Lokasi belum diisi' }}</p>
                    </div>
                    <a href="{{ route('operational-assets.show', $asset) }}" class="secondary-button sm:w-auto">Detail</a>
                </div>
            </article>
        @empty
            <div class="empty-state panel"><p class="font-extrabold text-slate-700">Belum ada aset perangkat pada filter ini.</p></div>
        @endforelse
    </section>

    @if ($assets->hasPages())
        <div class="mt-6">{{ $assets->links() }}</div>
    @endif
</x-layouts.app>
