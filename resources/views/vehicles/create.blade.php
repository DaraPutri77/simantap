<x-layouts.app
    title="Tambah Kendaraan"
    header="Tambah Kendaraan"
    eyebrow="Kendaraan"
>
    <section class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Master Kendaraan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                Tambah Kendaraan Operasional
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Catat identitas, dokumen, odometer, lokasi, dan status awal kendaraan secara lengkap.
            </p>
        </div>
        <a href="{{ route('vehicles.index') }}" class="secondary-button sm:w-auto">
            Kembali
        </a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">Data kendaraan belum dapat disimpan.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('vehicles.store') }}"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        @include('vehicles.partials.form')

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('vehicles.index') }}" class="secondary-button sm:w-auto">
                Batal
            </a>
            <button
                type="submit"
                class="button-primary-inline"
                data-submit-label="Menyimpan..."
            >
                Simpan Kendaraan
            </button>
        </div>
    </form>
</x-layouts.app>
