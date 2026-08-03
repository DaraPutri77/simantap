<x-layouts.app
    title="Kategori Barang"
    header="Kategori Barang"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Referensi Master</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Kategori Barang
            </h1>
            <p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-500">
                Kelompokkan barang agar pencarian, pemantauan stok, dan laporan
                persediaan tetap konsisten.
            </p>
        </div>
        <a href="{{ route('item-categories.create') }}" class="button-primary-inline">
            Tambah Kategori
        </a>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Kategori</h2>
                <p class="panel-subtitle">
                    {{ $categories->total() }} kategori tersimpan
                </p>
            </div>
        </div>

        @if ($categories->isEmpty())
            <div class="empty-state">Belum ada kategori barang.</div>
        @else
            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($categories as $category)
                    <article class="rounded-2xl border border-slate-200 p-5 transition hover:border-sky-200 hover:shadow-lg hover:shadow-slate-200/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate font-black text-slate-950">
                                    {{ $category->name }}
                                </h2>
                                <p class="mt-1 text-xs font-bold text-slate-500">
                                    {{ $category->items_count }} barang
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $category->is_active
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-600' }}">
                                {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <p class="mt-4 line-clamp-3 min-h-15 text-sm leading-5 text-slate-500">
                            {{ $category->description ?: 'Belum ada keterangan kategori.' }}
                        </p>
                        <a
                            href="{{ route('item-categories.edit', $category) }}"
                            class="secondary-button mt-4"
                        >
                            Edit Kategori
                        </a>
                    </article>
                @endforeach
            </div>
            <div class="border-t border-slate-100 px-5 py-4">
                {{ $categories->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
