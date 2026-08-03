@php
    $managedAdjustment = $adjustment ?? null;
    $adjustmentLines = old('items');

    if ($adjustmentLines === null && $managedAdjustment !== null) {
        $adjustmentLines = $managedAdjustment->items->map(
            fn ($line) => [
                'item_id' => $line->item_id,
                'physical_quantity' => $line->physical_quantity,
                'notes' => $line->notes,
            ],
        )->all();
    }

    $adjustmentLines ??= [
        [
            'item_id' => '',
            'physical_quantity' => '',
            'notes' => '',
        ],
    ];
    $displayTimezone = (string) config(
        'simantap.display_timezone',
        'Asia/Jakarta',
    );
    $adjustmentDate = old(
        'adjustment_date',
        $managedAdjustment?->adjustment_date
            ?->copy()
            ->timezone($displayTimezone)
            ->format('Y-m-d\TH:i')
        ?? now($displayTimezone)->format('Y-m-d\TH:i'),
    );
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="adjustment_date" class="form-label">Tanggal Pemeriksaan</label>
        <input
            id="adjustment_date"
            name="adjustment_date"
            type="datetime-local"
            value="{{ $adjustmentDate }}"
            class="form-input @error('adjustment_date') form-input-error @enderror"
            required
        >
        @error('adjustment_date')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="reason" class="form-label">Alasan Penyesuaian</label>
        <textarea
            id="reason"
            name="reason"
            rows="3"
            class="form-input py-4 @error('reason') form-input-error @enderror"
            maxlength="3000"
            placeholder="Contoh: Hasil stock opname bulanan"
            required
        >{{ old('reason', $managedAdjustment?->reason) }}</textarea>
        @error('reason')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="form-label">
            Catatan Tambahan
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <input
            id="notes"
            name="notes"
            type="text"
            value="{{ old('notes', $managedAdjustment?->notes) }}"
            class="form-input @error('notes') form-input-error @enderror"
            maxlength="3000"
        >
        @error('notes')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<section
    class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
    data-inventory-lines="adjustment"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="font-black text-slate-950">Hasil Pemeriksaan Fisik</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Masukkan jumlah fisik aktual. Sistem menghitung selisih otomatis.
            </p>
        </div>
        <button
            type="button"
            class="secondary-button sm:w-auto"
            data-add-inventory-line
        >
            Tambah Baris
        </button>
    </div>

    @error('items')
        <p class="form-error">{{ $message }}</p>
    @enderror

    <div class="mt-4 space-y-4" data-inventory-line-list>
        @foreach ($adjustmentLines as $lineIndex => $line)
            <article
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                data-inventory-line
            >
                <div class="inventory-line-grid">
                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_item_id">
                            Barang
                        </label>
                        <select
                            id="items_{{ $lineIndex }}_item_id"
                            name="items[{{ $lineIndex }}][item_id]"
                            class="form-input @error("items.{$lineIndex}.item_id") form-input-error @enderror"
                            data-inventory-item-select
                            required
                        >
                            <option value="">Pilih barang</option>
                            @foreach ($items as $masterItem)
                                <option
                                    value="{{ $masterItem->id }}"
                                    data-stock="{{ $masterItem->current_stock }}"
                                    data-unit="{{ $masterItem->unit->symbol }}"
                                    @selected((int) ($line['item_id'] ?? 0) === $masterItem->id)
                                >
                                    {{ $masterItem->item_code }} · {{ $masterItem->name }}
                                </option>
                            @endforeach
                        </select>
                        @error("items.{$lineIndex}.item_id")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-[11px] font-semibold text-slate-500" data-inventory-stock-label>
                            Pilih barang untuk melihat stok sistem
                        </p>
                    </div>

                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_physical_quantity">
                            Jumlah Fisik
                        </label>
                        <input
                            id="items_{{ $lineIndex }}_physical_quantity"
                            name="items[{{ $lineIndex }}][physical_quantity]"
                            type="number"
                            value="{{ $line['physical_quantity'] ?? '' }}"
                            class="form-input @error("items.{$lineIndex}.physical_quantity") form-input-error @enderror"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            data-physical-quantity
                            required
                        >
                        @error("items.{$lineIndex}.physical_quantity")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-[11px] font-bold text-sky-700" data-inventory-difference>
                            Selisih: —
                        </p>
                    </div>

                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_notes">
                            Catatan Barang
                            <span class="font-medium text-slate-500">(opsional)</span>
                        </label>
                        <input
                            id="items_{{ $lineIndex }}_notes"
                            name="items[{{ $lineIndex }}][notes]"
                            type="text"
                            value="{{ $line['notes'] ?? '' }}"
                            class="form-input @error("items.{$lineIndex}.notes") form-input-error @enderror"
                            maxlength="1000"
                            placeholder="Contoh: 2 buah rusak"
                        >
                        @error("items.{$lineIndex}.notes")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
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
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            data-inventory-line
        >
            <div class="inventory-line-grid">
                <div>
                    <label class="form-label" for="items___INDEX___item_id">Barang</label>
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
                                data-stock="{{ $masterItem->current_stock }}"
                                data-unit="{{ $masterItem->unit->symbol }}"
                            >
                                {{ $masterItem->item_code }} · {{ $masterItem->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-[11px] font-semibold text-slate-500" data-inventory-stock-label>
                        Pilih barang untuk melihat stok sistem
                    </p>
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___physical_quantity">
                        Jumlah Fisik
                    </label>
                    <input
                        id="items___INDEX___physical_quantity"
                        name="items[__INDEX__][physical_quantity]"
                        type="number"
                        class="form-input"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        data-physical-quantity
                        required
                    >
                    <p class="mt-2 text-[11px] font-bold text-sky-700" data-inventory-difference>
                        Selisih: —
                    </p>
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___notes">
                        Catatan Barang
                        <span class="font-medium text-slate-500">(opsional)</span>
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
    Sistem menyimpan stok saat draft dibuat. Jika stok berubah sebelum posting,
    draft wajib diperbarui agar hasil stock opname tetap akurat.
</div>
