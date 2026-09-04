@php
    $managedItem = $item ?? null;
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="item_code" class="form-label">Kode Barang</label>
        <input
            id="item_code"
            name="item_code"
            type="text"
            value="{{ old('item_code', $managedItem?->item_code) }}"
            class="form-input @error('item_code') form-input-error @enderror"
            maxlength="80"
            placeholder="Contoh: ATK-001"
            required
            autofocus
        >
        @error('item_code')
            <p class="form-error">{{ $message }}</p>
        @enderror
        <p class="mt-2 text-xs font-medium text-slate-500">
            Gunakan huruf, angka, titik, garis miring, atau tanda hubung.
        </p>
    </div>

    <div>
        <label for="name" class="form-label">Nama Barang</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $managedItem?->name) }}"
            class="form-input @error('name') form-input-error @enderror"
            maxlength="255"
            required
        >
        @error('name')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    @role('Administrator')
    <div>
        <label for="harga" class="form-label">
            Harga Barang (Rp)
            <span class="font-medium text-slate-500">(khusus Admin)</span>
        </label>
        <input
            id="harga"
            name="harga"
            type="number"
            value="{{ old('harga', $managedItem?->harga) }}"
            class="form-input @error('harga') form-input-error @enderror"
            min="0"
            step="0.01"
            placeholder="Contoh: 50000"
        >
        @error('harga')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
    @endrole

    <div>
        <label for="category_id" class="form-label">Kategori</label>
        <select
            id="category_id"
            name="category_id"
            class="form-input @error('category_id') form-input-error @enderror"
            required
        >
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option
                    value="{{ $category->id }}"
                    @selected((int) old('category_id', $managedItem?->category_id) === $category->id)
                >
                    {{ $category->name }}
                    {{ $category->is_active ? '' : '(nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="unit_id" class="form-label">Satuan</label>
        <select
            id="unit_id"
            name="unit_id"
            class="form-input @error('unit_id') form-input-error @enderror"
            required
        >
            <option value="">Pilih satuan</option>
            @foreach ($units as $unit)
                <option
                    value="{{ $unit->id }}"
                    @selected((int) old('unit_id', $managedItem?->unit_id) === $unit->id)
                >
                    {{ $unit->name }} ({{ $unit->symbol }})
                    {{ $unit->is_active ? '' : '(nonaktif)' }}
                </option>
            @endforeach
        </select>
        @error('unit_id')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    @if ($managedItem === null)
        <div>
            <label for="initial_stock" class="form-label">Stok Awal</label>
            <input
                id="initial_stock"
                name="initial_stock"
                type="number"
                value="{{ old('initial_stock', '0') }}"
                class="form-input @error('initial_stock') form-input-error @enderror"
                min="0"
                max="9999999999999.99"
                step="0.01"
                inputmode="decimal"
                required
            >
            @error('initial_stock')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-medium text-slate-500">
                Nilai di atas nol otomatis dicatat sebagai transaksi stok awal.
            </p>
        </div>
    @else
        <div>
            <label class="form-label">Stok Saat Ini</label>
            <div class="flex min-h-13 items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-black text-slate-700">
                {{ number_format((float) $managedItem->current_stock, 2, ',', '.') }}
                {{ $managedItem->unit->symbol }}
            </div>
            <p class="mt-2 text-xs font-medium text-slate-500">
                Stok hanya berubah melalui barang masuk atau penyesuaian.
            </p>
        </div>
    @endif

    <div>
        <label for="minimum_stock" class="form-label">Stok Minimum</label>
        <input
            id="minimum_stock"
            name="minimum_stock"
            type="number"
            value="{{ old('minimum_stock', $managedItem?->minimum_stock ?? '0') }}"
            class="form-input @error('minimum_stock') form-input-error @enderror"
            min="0"
            max="9999999999999.99"
            step="0.01"
            inputmode="decimal"
            required
        >
        @error('minimum_stock')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="storage_location" class="form-label">
            Lokasi Penyimpanan
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <input
            id="storage_location"
            name="storage_location"
            type="text"
            value="{{ old('storage_location', $managedItem?->storage_location) }}"
            class="form-input @error('storage_location') form-input-error @enderror"
            maxlength="255"
            placeholder="Contoh: Gudang A, Rak 02"
        >
        @error('storage_location')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="image" class="form-label">
            Foto Barang
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <input
            id="image"
            name="image"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            class="form-input py-3 @error('image') form-input-error @enderror"
        >
        @error('image')
            <p class="form-error">{{ $message }}</p>
        @enderror
        <p class="mt-2 text-xs font-medium text-slate-500">
            JPG, PNG, atau WebP. Maksimal 2 MB.
        </p>

        @if ($managedItem?->image_path)
            <label class="mt-3 flex items-center gap-3 text-xs font-bold text-slate-600">
                <input
                    name="remove_image"
                    type="checkbox"
                    value="1"
                    class="h-4 w-4 rounded border-slate-300 text-sky-600"
                    @checked(old('remove_image'))
                >
                Hapus foto lama
            </label>
        @elseif ($managedItem !== null)
            <input name="remove_image" type="hidden" value="0">
        @endif
    </div>

    <div class="md:col-span-2">
        <label for="description" class="form-label">
            Keterangan
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <textarea
            id="description"
            name="description"
            rows="4"
            class="form-input py-4 @error('description') form-input-error @enderror"
            maxlength="3000"
        >{{ old('description', $managedItem?->description) }}</textarea>
        @error('description')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <input
                name="is_active"
                type="checkbox"
                value="1"
                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600"
                @checked(old('is_active', $managedItem?->is_active ?? true))
            >
            <span>
                <span class="block text-sm font-extrabold text-slate-800">
                    Barang aktif dan dapat digunakan
                </span>
                <span class="mt-1 block text-xs leading-5 text-slate-500">
                    Barang nonaktif tidak dapat dipilih pada transaksi baru.
                </span>
            </span>
        </label>
    </div>
</div>