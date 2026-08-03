<x-layouts.app
    title="Satuan Barang"
    header="Satuan Barang"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Referensi Master</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Satuan Barang
            </h1>
            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Kelola nama dan simbol satuan yang dipakai pada stok serta
                seluruh transaksi persediaan.
            </p>
        </div>
        <a href="{{ route('units.create') }}" class="button-primary-inline">
            Tambah Satuan
        </a>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Satuan</h2>
                <p class="panel-subtitle">{{ $units->total() }} satuan tersimpan</p>
            </div>
        </div>

        @if ($units->isEmpty())
            <div class="empty-state">Belum ada satuan barang.</div>
        @else
            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($units as $unit)
                    <article class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-black text-slate-950">{{ $unit->name }}</h2>
                                <p class="mt-1 text-xs font-bold text-sky-600">
                                    Simbol: {{ $unit->symbol }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $unit->is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-600' }}">
                                {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">
                            Digunakan oleh
                            <span class="font-extrabold text-slate-800">
                                {{ $unit->items_count }} barang
                            </span>
                        </p>
                        <a
                            href="{{ route('units.edit', $unit) }}"
                            class="secondary-button mt-4"
                        >
                            Edit Satuan
                        </a>
                    </article>
                @endforeach
            </div>
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $units->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
