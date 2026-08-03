<x-layouts.app
    title="Tambah Barang"
    header="Tambah Barang"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="mx-auto max-w-5xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Master Baru</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Tambah Barang
                </h1>
                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Stok awal akan otomatis masuk ke kartu stok dan tidak dapat
                    diedit langsung setelah barang disimpan.
                </p>
            </div>
            <a href="{{ route('items.index') }}" class="secondary-button sm:w-auto">
                Kembali
            </a>
        </div>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Identitas Barang</h2>
                    <p class="panel-subtitle">Lengkapi master dan batas stok minimum</p>
                </div>
            </div>
            <form
                method="POST"
                action="{{ route('items.store') }}"
                enctype="multipart/form-data"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @include('inventory.items.partials.form')
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('items.index') }}" class="secondary-button sm:w-auto sm:min-w-32">
                        Batal
                    </a>
                    <button type="submit" class="button-primary-inline sm:min-w-40">
                        Simpan Barang
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
