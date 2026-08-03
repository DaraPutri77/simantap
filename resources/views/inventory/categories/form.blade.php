@php
    $isEdit = $category !== null;
@endphp

<x-layouts.app
    :title="$isEdit ? 'Edit Kategori' : 'Tambah Kategori'"
    :header="$isEdit ? 'Edit Kategori' : 'Tambah Kategori'"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    <section class="mx-auto max-w-3xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Referensi Master</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950">
                    {{ $isEdit ? 'Edit Kategori' : 'Tambah Kategori' }}
                </h1>
                <p class="mt-2 text-sm font-medium leading-6 text-slate-500">
                    Kategori nonaktif tetap tersimpan pada riwayat barang lama.
                </p>
            </div>
            <a href="{{ route('item-categories.index') }}" class="secondary-button sm:w-auto">
                Kembali
            </a>
        </div>

        <article class="panel">
            <form
                method="POST"
                action="{{ $isEdit
                    ? route('item-categories.update', $category)
                    : route('item-categories.store') }}"
                class="space-y-6 p-5 sm:p-6"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div>
                    <label for="name" class="form-label">Nama Kategori</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name', $category?->name) }}"
                        class="form-input @error('name') form-input-error @enderror"
                        maxlength="255"
                        required
                        autofocus
                    >
                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="form-label">
                        Keterangan
                        <span class="font-medium text-slate-500">(opsional)</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        class="form-input py-4 @error('description') form-input-error @enderror"
                        maxlength="3000"
                    >{{ old('description', $category?->description) }}</textarea>
                    @error('description')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <input
                            name="is_active"
                            type="checkbox"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600"
                            @checked(old('is_active', $category?->is_active ?? true))
                        >
                        <span>
                            <span class="block text-sm font-extrabold text-slate-800">
                                Kategori aktif
                            </span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Hanya kategori aktif yang dapat dipilih pada barang baru.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('item-categories.index') }}" class="secondary-button sm:w-auto sm:min-w-32">
                        Batal
                    </a>
                    <button type="submit" class="button-primary-inline sm:min-w-40">
                        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kategori' }}
                    </button>
                </div>
            </form>
        </article>
    </section>
</x-layouts.app>
