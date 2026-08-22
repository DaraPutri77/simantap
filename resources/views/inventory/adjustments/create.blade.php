<x-layouts.app
    title="Buat Penyesuaian Stok"
    header="Buat Penyesuaian Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="mx-auto max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Stock Opname</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Buat Penyesuaian Stok
                </h1>
                <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-500">
                    Catat jumlah fisik hasil pemeriksaan. Sistem menghitung
                    selisih otomatis dan tidak mengubah saldo sampai Admin
                    melakukan posting.
                </p>
            </div>
            <a href="{{ route('stock-adjustments.index') }}" class="secondary-button sm:w-auto">
                Kembali
            </a>
        </div>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Dokumen Penyesuaian</h2>
                    <p class="panel-subtitle">Selisih dihitung dari stok sistem</p>
                </div>
            </div>
            <div class="mx-5 mt-5 rounded-2xl border border-sky-100 bg-sky-50 p-4 text-xs font-semibold leading-5 text-sky-900 sm:mx-6">
                Penyesuaian bukan untuk mencatat barang datang. Jika ada penerimaan
                dari pemasok/sumber lain, gunakan menu <span class="font-black">Barang Masuk</span>.
                Di sini yang diisi adalah <span class="font-black">jumlah fisik aktual</span>,
                bukan angka selisih.
            </div>

            <form
                method="POST"
                action="{{ route('stock-adjustments.store') }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @include('inventory.adjustments.partials.form')
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('stock-adjustments.index') }}" class="secondary-button sm:w-auto sm:min-w-32">
                        Batal
                    </a>
                    <button type="submit" class="button-primary-inline sm:min-w-40">
                        Simpan Draft
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
