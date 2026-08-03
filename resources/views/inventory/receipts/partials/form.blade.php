@php
    $managedReceipt = $receipt ?? null;
    $receiptLines = old('items');

    if ($receiptLines === null && $managedReceipt !== null) {
        $receiptLines = $managedReceipt->items->map(
            fn ($line) => [
                'item_id' => $line->item_id,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
                'notes' => $line->notes,
            ],
        )->all();
    }

    $receiptLines ??= [
        [
            'item_id' => '',
            'quantity' => '',
            'unit_cost' => '',
            'notes' => '',
        ],
    ];
    $displayTimezone = 'Asia/Jakarta';
    $receiptDate = old(
        'receipt_date',
        $managedReceipt?->receipt_date
            ?->copy()
            ->timezone($displayTimezone)
            ->format('Y-m-d\TH:i')
        ?? now($displayTimezone)->format('Y-m-d\TH:i'),
    );
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="receipt_date" class="form-label">Tanggal Penerimaan</label>
        <input
            id="receipt_date"
            name="receipt_date"
            type="datetime-local"
            value="{{ $receiptDate }}"
            class="form-input @error('receipt_date') form-input-error @enderror"
            required
        >
        @error('receipt_date')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="source" class="form-label">Sumber Barang</label>
        <input
            id="source"
            name="source"
            type="text"
            value="{{ old('source', $managedReceipt?->source) }}"
            class="form-input @error('source') form-input-error @enderror"
            maxlength="255"
            placeholder="Contoh: Pengadaan APBN 2026"
            required
        >
        @error('source')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="reference_number" class="form-label">
            Nomor Referensi
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <input
            id="reference_number"
            name="reference_number"
            type="text"
            value="{{ old('reference_number', $managedReceipt?->reference_number) }}"
            class="form-input @error('reference_number') form-input-error @enderror"
            maxlength="255"
            placeholder="Nomor faktur, SPK, atau BAST"
        >
        @error('reference_number')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="notes" class="form-label">
            Catatan Transaksi
            <span class="font-medium text-slate-500">(opsional)</span>
        </label>
        <input
            id="notes"
            name="notes"
            type="text"
            value="{{ old('notes', $managedReceipt?->notes) }}"
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
    data-inventory-lines="receipt"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="font-black text-slate-950">Detail Barang Masuk</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Satu dokumen dapat memuat hingga 50 jenis barang.
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
        @foreach ($receiptLines as $lineIndex => $line)
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
                                    data-stock="{{ $masterItem->available_stock }}"
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
                            Pilih barang untuk melihat stok
                        </p>
                    </div>

                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_quantity">
                            Jumlah
                        </label>
                        <input
                            id="items_{{ $lineIndex }}_quantity"
                            name="items[{{ $lineIndex }}][quantity]"
                            type="number"
                            value="{{ $line['quantity'] ?? '' }}"
                            class="form-input @error("items.{$lineIndex}.quantity") form-input-error @enderror"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            required
                        >
                        @error("items.{$lineIndex}.quantity")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_unit_cost">
                            Harga Satuan
                            <span class="font-medium text-slate-500">(opsional)</span>
                        </label>
                        <input
                            id="items_{{ $lineIndex }}_unit_cost"
                            name="items[{{ $lineIndex }}][unit_cost]"
                            type="number"
                            value="{{ $line['unit_cost'] ?? '' }}"
                            class="form-input @error("items.{$lineIndex}.unit_cost") form-input-error @enderror"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                        >
                        @error("items.{$lineIndex}.unit_cost")
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="items_{{ $lineIndex }}_notes">
                            Catatan
                            <span class="font-medium text-slate-500">(opsional)</span>
                        </label>
                        <input
                            id="items_{{ $lineIndex }}_notes"
                            name="items[{{ $lineIndex }}][notes]"
                            type="text"
                            value="{{ $line['notes'] ?? '' }}"
                            class="form-input @error("items.{$lineIndex}.notes") form-input-error @enderror"
                            maxlength="1000"
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
                                data-stock="{{ $masterItem->available_stock }}"
                                data-unit="{{ $masterItem->unit->symbol }}"
                            >
                                {{ $masterItem->item_code }} · {{ $masterItem->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-[11px] font-semibold text-slate-500" data-inventory-stock-label>
                        Pilih barang untuk melihat stok
                    </p>
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___quantity">Jumlah</label>
                    <input
                        id="items___INDEX___quantity"
                        name="items[__INDEX__][quantity]"
                        type="number"
                        class="form-input"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        required
                    >
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___unit_cost">
                        Harga Satuan
                        <span class="font-medium text-slate-500">(opsional)</span>
                    </label>
                    <input
                        id="items___INDEX___unit_cost"
                        name="items[__INDEX__][unit_cost]"
                        type="number"
                        class="form-input"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                    >
                </div>
                <div>
                    <label class="form-label" for="items___INDEX___notes">
                        Catatan
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
    Menyimpan formulir hanya membuat draft. Stok baru bertambah setelah draft
    diperiksa dan diposting oleh Administrator.
</div>
