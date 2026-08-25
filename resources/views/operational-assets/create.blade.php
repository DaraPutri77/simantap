<x-layouts.app title="Tambah Aset Perangkat" header="Tambah Aset Perangkat" eyebrow="Pemeliharaan">
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Master Aset Operasional</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Tambah PC, Laptop, atau Printer</h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">Catat perangkat sebagai aset individual agar identitas dan riwayat pemeliharaannya tidak bercampur dengan stok persediaan.</p>
        </div>
        <a href="{{ route('operational-assets.index') }}" class="secondary-button sm:w-auto">Kembali</a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mt-6"><strong>Data belum dapat disimpan.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form method="POST" action="{{ route('operational-assets.store') }}" class="mt-6 space-y-6">
        @csrf
        @include('operational-assets.partials.form')
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('operational-assets.index') }}" class="secondary-button sm:w-auto">Batal</a>
            <button type="submit" class="primary-button sm:w-auto">Simpan Aset</button>
        </div>
    </form>
</x-layouts.app>
