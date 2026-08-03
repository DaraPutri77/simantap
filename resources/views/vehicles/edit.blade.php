<x-layouts.app
    title="Edit Kendaraan"
    header="Edit Kendaraan"
    eyebrow="Kendaraan"
>
    <section class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Master Kendaraan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                Edit {{ $vehicle->displayName() }}
            </h1>
            <p class="mt-2 text-sm font-bold text-sky-700">
                {{ $vehicle->vehicle_code }} · {{ $vehicle->license_plate }}
            </p>
        </div>
        <a href="{{ route('vehicles.show', $vehicle) }}" class="secondary-button sm:w-auto">
            Kembali ke Detail
        </a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">Perubahan belum dapat disimpan.</p>
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
        action="{{ route('vehicles.update', $vehicle) }}"
        enctype="multipart/form-data"
        class="space-y-6"
    >
        @csrf
        @method('PUT')
        @include('vehicles.partials.form')

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('vehicles.show', $vehicle) }}" class="secondary-button sm:w-auto">
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
