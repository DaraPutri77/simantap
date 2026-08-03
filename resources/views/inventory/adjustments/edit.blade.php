<x-layouts.app
    title="Edit Penyesuaian Stok"
    header="Edit Penyesuaian Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="mx-auto max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">{{ $adjustment->adjustment_number }}</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    Edit Draft Penyesuaian
                </h1>
                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Stok sistem dan selisih akan dihitung ulang saat disimpan.
                </p>
            </div>
            <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="secondary-button sm:w-auto">
                Kembali
            </a>
        </div>

        <article class="panel">
            <form
                method="POST"
                action="{{ route('stock-adjustments.update', $adjustment) }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @method('PUT')
                @include('inventory.adjustments.partials.form')
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="secondary-button sm:w-auto sm:min-w-32">
                        Batal
                    </a>
                    <button type="submit" class="button-primary-inline sm:min-w-40">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
