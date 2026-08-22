<x-layouts.app
    title="Persediaan Barang"
    header="Persediaan Barang"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Master Persediaan</p>

            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Daftar Barang
            </h1>

            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Pantau stok tersedia, batas minimum, kategori, satuan, dan
                lokasi penyimpanan setiap barang.
            </p>
        </div>

        @if ($canManage)
            <a
                href="{{ route('items.create') }}"
                class="button-primary-inline"
            >
                Tambah Barang
            </a>
        @endif
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Barang', 'value' => $summary['total'], 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
            ['label' => 'Barang Aktif', 'value' => $summary['active'], 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            ['label' => 'Stok Minimum', 'value' => $summary['low'], 'tone' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            ['label' => 'Nonaktif', 'value' => $summary['inactive'], 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200'],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>

                <p class="mt-4 text-3xl font-black tracking-tight text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Master Barang</h2>
                <p class="panel-subtitle">
                    {{ $items->total() }} barang ditemukan
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('items.index') }}"
            class="inventory-filter-grid"
        >
            <div>
                <label for="q" class="form-label">Cari Barang</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['q'] }}"
                    class="form-input"
                    placeholder="Kode, nama, atau lokasi"
                >
            </div>

            <div>
                <label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-input">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)
                        <option
                            value="{{ $category->id }}"
                            @selected($filters['category'] === $category->id)
                        >
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="unit" class="form-label">Satuan</label>
                <select id="unit" name="unit" class="form-input">
                    <option value="">Semua satuan</option>
                    @foreach ($units as $unit)
                        <option
                            value="{{ $unit->id }}"
                            @selected($filters['unit'] === $unit->id)
                        >
                            {{ $unit->name }} ({{ $unit->symbol }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($canManage)
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>
                            Aktif
                        </option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>
                            Nonaktif
                        </option>
                    </select>
                </div>
            @endif

            <div>
                <label for="stock" class="form-label">Kondisi Stok</label>
                <select id="stock" name="stock" class="form-input">
                    <option value="">Semua kondisi</option>
                    <option value="available" @selected($filters['stock'] === 'available')>
                        Tersedia
                    </option>
                    <option value="low" @selected($filters['stock'] === 'low')>
                        Hampir habis
                    </option>
                    <option value="out" @selected($filters['stock'] === 'out')>
                        Habis
                    </option>
                </select>
            </div>

            <div class="inventory-filter-actions">
                <a href="{{ route('items.index') }}" class="secondary-button">
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">
                    Terapkan
                </button>
            </div>
        </form>

        @if ($items->isEmpty())
            <div class="empty-state">
                <p class="font-extrabold text-slate-600">
                    Barang tidak ditemukan.
                </p>
                <p class="mt-1">Ubah filter atau tambahkan master barang baru.</p>
            </div>
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="data-table min-w-[900px]">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Kategori</th>
                            <th>Stok Tersedia</th>
                            <th>Stok Minimum</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php
                                $available = (float) $item->available_stock;
                                $minimum = (float) $item->minimum_stock;
                                $stockTone = $available <= 0
                                    ? 'bg-red-50 text-red-700 ring-red-100'
                                    : ($available <= $minimum
                                        ? 'bg-amber-50 text-amber-700 ring-amber-100'
                                        : 'bg-emerald-50 text-emerald-700 ring-emerald-100');
                            @endphp
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('items.show', $item) }}"
                                        class="font-extrabold text-slate-950 hover:text-sky-700"
                                    >
                                        {{ $item->name }}
                                    </a>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">
                                        {{ $item->item_code }} · {{ $item->unit->symbol }}
                                    </p>
                                </td>
                                <td>{{ $item->category->name }}</td>
                                <td>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $stockTone }}">
                                        {{ number_format($available, 2, ',', '.') }}
                                        {{ $item->unit->symbol }}
                                    </span>
                                    @if ((float) $item->reserved_stock > 0)
                                        <p class="mt-1 text-[11px] text-slate-500">
                                            Reservasi:
                                            {{ number_format((float) $item->reserved_stock, 2, ',', '.') }}
                                        </p>
                                    @endif
                                </td>
                                <td>
                                    {{ number_format($minimum, 2, ',', '.') }}
                                    {{ $item->unit->symbol }}
                                </td>
                                <td>{{ $item->storage_location ?: '—' }}</td>
                                <td>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $item->is_active
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a
                                        href="{{ route('items.show', $item) }}"
                                        class="text-xs font-extrabold text-sky-700 hover:text-sky-900"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @foreach ($items as $item)
                    @php
                        $available = (float) $item->available_stock;
                        $minimum = (float) $item->minimum_stock;
                    @endphp
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                    {{ $item->item_code }}
                                </p>
                                <h3 class="mt-1 truncate font-black text-slate-950">
                                    {{ $item->name }}
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $item->category->name }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $item->is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-600' }}">
                                {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase text-slate-500">
                                    Stok tersedia
                                </p>
                                <p class="mt-1 font-black text-slate-950">
                                    {{ number_format($available, 2, ',', '.') }}
                                    {{ $item->unit->symbol }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase text-slate-500">
                                    Stok minimum
                                </p>
                                <p class="mt-1 font-black text-slate-950">
                                    {{ number_format($minimum, 2, ',', '.') }}
                                    {{ $item->unit->symbol }}
                                </p>
                            </div>
                        </div>
                        <a
                            href="{{ route('items.show', $item) }}"
                            class="secondary-button mt-4"
                        >
                            Lihat Detail
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                {{ $items->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
