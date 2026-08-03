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
                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Catat jumlah fisik tanpa mengubah saldo stok secara langsung.
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
