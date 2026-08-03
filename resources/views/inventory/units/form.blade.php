@php
    $isEdit = $unit !== null;
@endphp

<x-layouts.app
    :title="$isEdit ? 'Edit Satuan' : 'Tambah Satuan'"
    :header="$isEdit ? 'Edit Satuan' : 'Tambah Satuan'"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="mx-auto max-w-3xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Referensi Master</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    {{ $isEdit ? 'Edit Satuan' : 'Tambah Satuan' }}
                </h1>
                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Simbol ditampilkan di samping seluruh nilai stok barang.
                </p>
            </div>
            <a href="{{ route('units.index') }}" class="secondary-button sm:w-auto">
                Kembali
            </a>
        </div>

        <article class="panel">
            <form
                method="POST"
                action="{{ $isEdit
                    ? route('units.update', $unit)
                    : route('units.store') }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="name" class="form-label">Nama Satuan</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $unit?->name) }}"
                            class="form-input @error('name') form-input-error @enderror"
                            maxlength="255"
                            placeholder="Contoh: Rim"
                            required
                            autofocus
                        >
                        @error('name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="symbol" class="form-label">Simbol</label>
                        <input
                            id="symbol"
                            name="symbol"
                            type="text"
                            value="{{ old('symbol', $unit?->symbol) }}"
                            class="form-input @error('symbol') form-input-error @enderror"
                            maxlength="30"
                            placeholder="Contoh: rim"
                            required
                        >
                        @error('symbol')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <input
                            name="is_active"
                            type="checkbox"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600"
                            @checked(old('is_active', $unit?->is_active ?? true))
                        >
                        <span>
                            <span class="block text-sm font-extrabold text-slate-800">
                                Satuan aktif
                            </span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Satuan nonaktif tetap tersimpan pada riwayat lama.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('units.index') }}" class="secondary-button sm:w-auto sm:min-w-32">
                        Batal
                    </a>
                    <button type="submit" class="button-primary-inline sm:min-w-40">
                        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Satuan' }}
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
