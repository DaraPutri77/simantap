<x-layouts.app :title="'Edit '.$asset->asset_code" header="Edit Aset Perangkat" eyebrow="Pemeliharaan">
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Master Aset Operasional</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Edit {{ $asset->asset_code }}</h1>
            <p class="mt-2 text-sm font-medium text-slate-600">Perbarui identitas administratif dan penempatan tanpa menghapus histori pemeliharaan.</p>
        </div>
        <a href="{{ route('operational-assets.show', $asset) }}" class="secondary-button sm:w-auto">Kembali</a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mt-6"><strong>Data belum dapat diperbarui.</strong><span>{{ $errors->first() }}</span></div>
    @endif

    <form method="POST" action="{{ route('operational-assets.update', $asset) }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')
        @include('operational-assets.partials.form')
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('operational-assets.show', $asset) }}" class="secondary-button sm:w-auto">Batal</a>
            <button type="submit" class="primary-button sm:w-auto">Simpan Perubahan</button>
        </div>
    </form>
</x-layouts.app>
