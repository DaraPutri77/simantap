<x-layouts.app
    title="Ubah Permintaan Barang"
    header="Ubah Permintaan"
    eyebrow="Permintaan Saya"
>
    <section>
        <p class="eyebrow">Perbaiki Formulir</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            Ubah {{ $inventoryRequest->request_number }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
            Periksa kembali barang, jumlah, dan keperluan. Setelah tersimpan,
            buka detail untuk membubuhkan tanda tangan dan mengirim ulang.
        </p>
    </section>

    @if ($inventoryRequest->revision_note)
        <div class="alert-warning mt-6">
            <strong>Catatan Administrator:</strong>
            <span>{{ $inventoryRequest->revision_note }}</span>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('my.inventory-requests.update', $inventoryRequest) }}"
        class="mt-6 space-y-6"
    >
        @csrf
        @method('PUT')
        @include('inventory-requests.partials.form')

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a
                href="{{ route('my.inventory-requests.show', $inventoryRequest) }}"
                class="secondary-button sm:w-auto"
            >
                Batal
            </a>
            <button
                type="submit"
                class="button-primary-inline"
                data-submit-label="Menyimpan..."
            >
                Simpan Perubahan
            </button>
        </div>
    </form>
</x-layouts.app>
