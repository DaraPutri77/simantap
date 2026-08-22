@php
    $defaultLines = $inventoryRequest
        ? $inventoryRequest->items->map(fn ($line) => [
            'item_id' => $line->item_id,
            'requested_quantity' => $line->requested_quantity,
            'notes' => $line->notes,
        ])->values()->all()
        : [[
            'item_id' => '',
            'requested_quantity' => '',
            'notes' => '',
        ]];
    $formLines = old('items', $defaultLines);
    $requestDate = old(
        'request_date',
        $inventoryRequest
            ? $inventoryRequest->request_date->copy()->timezone($displayTimezone)->format('Y-m-d')
            : now()->timezone($displayTimezone)->format('Y-m-d'),
    );
@endphp

@if ($errors->any())
    <div class="alert-danger">
        <div>
            <p class="font-black">Formulir belum dapat disimpan.</p>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Informasi Permintaan</h2>
            <p class="panel-subtitle">
                Data pegawai diambil otomatis dari profil akun.
            </p>
        </div>
    </div>

    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
        <div>
            <label for="request_date" class="form-label">
                Tanggal Permintaan
            </label>
            <input
                id="request_date"
                name="request_date"
                type="date"
                value="{{ $requestDate }}"
                class="form-input @error('request_date') form-input-error @enderror"
                required
            >
            @error('request_date')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
            <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-600">
                Pemohon
            </p>
            <p class="mt-2 font-black text-slate-950">
                {{ auth()->user()->name }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                {{ auth()->user()->employee_number ?: 'NIP belum diisi' }}
                ·
                {{ auth()->user()->work_unit ?: 'Unit kerja belum diisi' }}
            </p>
        </div>

        <div class="sm:col-span-2">
            <label for="purpose" class="form-label">Keperluan</label>
            <textarea
                id="purpose"
                name="purpose"
                rows="4"
                class="form-input min-h-32 py-4 @error('purpose') form-input-error @enderror"
                placeholder="Contoh: Kebutuhan ATK kegiatan pendataan bulan Agustus"
                required
            >{{ old('purpose', $inventoryRequest?->purpose) }}</textarea>
            @error('purpose')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="form-label">
                Catatan
                <span class="font-medium text-slate-600">(opsional)</span>
            </label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                class="form-input min-h-24 py-4 @error('notes') form-input-error @enderror"
                placeholder="Tambahkan informasi yang perlu diketahui Administrator"
            >{{ old('notes', $inventoryRequest?->notes) }}</textarea>
            @error('notes')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section
    class="panel"
    data-inventory-lines
    data-max-lines="20"
    data-stock-label="Stok tersedia"
>
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Daftar Barang</h2>
            <p class="panel-subtitle">
                Maksimal 20 jenis barang dalam satu permintaan.
                Barang dengan stok tersedia yang sudah mencapai batas minimum tidak dapat diminta.
            </p>
        </div>
        <button
            type="button"
            class="button-primary-inline"
            data-add-inventory-line
        >
            Tambah Barang
        </button>
    </div>

    @error('items')
        <div class="mx-5 mt-5 alert-danger sm:mx-6">
            {{ $message }}
        </div>
    @enderror

    <div class="space-y-4 p-5 sm:p-6" data-inventory-line-list>
        @foreach ($formLines as $index => $line)
            <article
                class="rounded-2xl border border-slate-300 bg-slate-50 p-4"
                data-inventory-line
            >
                <div class="inventory-line-grid">
                    <div class="sm:col-span-2">
                        <label
                            class="form-label"
                            for="items_{{ $index }}_item_id"
                        >
                            Barang
                        </label>
                        <select
                            id="items_{{ $index }}_item_id"
                            name="items[{{ $index }}][item_id]"
                            class="form-input @error("items.$index.item_id") form-input-error @enderror"
                            data-inventory-item-select
                            required
                        >
                            <option value="">Pilih barang</option>
                            @foreach ($items as $masterItem)
                                <option
                                    value="{{ $masterItem->id }}"
                                    data-stock="{{ $masterItem->available_stock }}"
                                    data-minimum="{{ $masterItem->minimum_stock }}"
                                    data-category="{{ $masterItem->category?->name }}"
                                    data-unit="{{ $masterItem->unit->symbol }}"
                                    @disabled(
                                        (float) $masterItem->available_stock <= (float) $masterItem->minimum_stock
                                        && (string) ($line['item_id'] ?? '') !== (string) $masterItem->id
                                    )
                                    @selected((string) ($line['item_id'] ?? '') === (string) $masterItem->id)
                                >
                                    {{ $masterItem->item_code }} · {{ $masterItem->name }} · {{ $masterItem->category?->name ?: 'Tanpa kategori' }}{{ (float) $masterItem->available_stock <= (float) $masterItem->minimum_stock ? ' · STOK MINIMUM' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-[11px] font-semibold text-slate-600" data-inventory-stock-label>
                            Pilih barang untuk melihat stok tersedia
                        </p>
                        @error("items.$index.item_id")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label
                            class="form-label"
                            for="items_{{ $index }}_requested_quantity"
                        >
                            Jumlah Diminta
                        </label>
                        <input
                            id="items_{{ $index }}_requested_quantity"
                            name="items[{{ $index }}][requested_quantity]"
                            type="number"
                            value="{{ $line['requested_quantity'] ?? '' }}"
                            class="form-input @error("items.$index.requested_quantity") form-input-error @enderror"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            required
                        >
                        @error("items.$index.requested_quantity")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label
                            class="form-label"
                            for="items_{{ $index }}_notes"
                        >
                            Catatan
                            <span class="font-medium text-slate-600">(opsional)</span>
                        </label>
                        <input
                            id="items_{{ $index }}_notes"
                            name="items[{{ $index }}][notes]"
                            type="text"
                            value="{{ $line['notes'] ?? '' }}"
                            class="form-input"
                            maxlength="1000"
                        >
                    </div>

                    <button
                        type="button"
                        class="danger-button"
                        data-remove-inventory-line
                    >
                        Hapus
                    </button>
                </div>
            </article>
        @endforeach
    </div>

    <template data-inventory-line-template>
        <article
            class="rounded-2xl border border-slate-300 bg-slate-50 p-4"
            data-inventory-line
        >
            <div class="inventory-line-grid">
                <div class="sm:col-span-2">
                    <label class="form-label" for="items___INDEX___item_id">
                        Barang
                    </label>
                    <select
                        id="items___INDEX___item_id"
                        name="items[__INDEX__][item_id]"
                        class="form-input"
                        data-inventory-item-select
                        required
                    >
                        <option value="">Pilih barang</option>
                        @foreach ($items as $masterItem)
                            <option
                                value="{{ $masterItem->id }}"
                                data-stock="{{ $masterItem->available_stock }}"
                                data-minimum="{{ $masterItem->minimum_stock }}"
                                data-category="{{ $masterItem->category?->name }}"
                                data-unit="{{ $masterItem->unit->symbol }}"
                                @disabled(
                                    (float) $masterItem->available_stock <= (float) $masterItem->minimum_stock
                                )
                            >
                                {{ $masterItem->item_code }} · {{ $masterItem->name }} · {{ $masterItem->category?->name ?: 'Tanpa kategori' }}{{ (float) $masterItem->available_stock <= (float) $masterItem->minimum_stock ? ' · STOK MINIMUM' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-[11px] font-semibold text-slate-600" data-inventory-stock-label>
                        Pilih barang untuk melihat stok tersedia
                    </p>
                </div>
                <div>
                    <label
                        class="form-label"
                        for="items___INDEX___requested_quantity"
                    >
                        Jumlah Diminta
                    </label>
                    <input
                        id="items___INDEX___requested_quantity"
                        name="items[__INDEX__][requested_quantity]"
                        type="number"
                        class="form-input"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___notes">
                        Catatan
                        <span class="font-medium text-slate-600">(opsional)</span>
                    </label>
                    <input
                        id="items___INDEX___notes"
                        name="items[__INDEX__][notes]"
                        type="text"
                        class="form-input"
                        maxlength="1000"
                    >
                </div>
                <button
                    type="button"
                    class="danger-button"
                    data-remove-inventory-line
                >
                    Hapus
                </button>
            </div>
        </article>
    </template>
</section>

<div class="alert-warning">
    Menyimpan formulir hanya membuat draft. Tanda tangan dibubuhkan pada halaman
    detail ketika kamu siap mengajukannya.
</div>
