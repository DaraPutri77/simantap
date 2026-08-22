<x-layouts.app
    title="Ajukan Peminjaman Kendaraan"
    header="Peminjaman Kendaraan"
    eyebrow="Peminjaman Saya"
>
    <section>
        <p class="eyebrow">Langkah 1 dari 2 · Formulir Baru</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            Ajukan Peminjaman Kendaraan
        </h1>
        <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
            Isi data peminjaman terlebih dahulu. Setelah draft tersimpan, sistem
            akan membawa Anda ke Langkah 2 untuk tanda tangan dan mengirim
            pengajuan ke Administrator.
        </p>
    </section>

    <form
        method="POST"
        action="{{ route('my.vehicle-loans.store') }}"
        class="mt-6 space-y-6"
    >
        @csrf
        @include('vehicle-loans.partials.form')

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a
                href="{{ route('my.vehicle-loans.index') }}"
                class="secondary-button sm:w-auto"
            >
                Batal
            </a>
            <button
                type="submit"
                class="button-primary-inline"
                data-submit-label="Menyimpan..."
            >
                Simpan Draft & Lanjut Tanda Tangan
            </button>
        </div>
    </form>
</x-layouts.app>
