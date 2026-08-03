<x-layouts.app
    title="Ubah Peminjaman Kendaraan"
    header="Ubah Peminjaman"
    eyebrow="Peminjaman Saya"
>
    <section>
        <p class="eyebrow">Perbaiki Formulir</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            Ubah {{ $vehicleLoan->loan_number }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
            Periksa kendaraan, jadwal, dan keperluan. Setelah tersimpan, buka
            detail untuk membubuhkan tanda tangan dan mengajukan peminjaman.
        </p>
    </section>

    <form
        method="POST"
        action="{{ route('my.vehicle-loans.update', $vehicleLoan) }}"
        class="mt-6 space-y-6"
    >
        @csrf
        @method('PUT')
        @include('vehicle-loans.partials.form')

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a
                href="{{ route('my.vehicle-loans.show', $vehicleLoan) }}"
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
