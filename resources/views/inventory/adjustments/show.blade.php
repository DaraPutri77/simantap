<x-layouts.app
    title="Detail Penyesuaian Stok"
    header="Detail Penyesuaian Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    @php
        $statusTone = match ($adjustment->status) {
            \App\Enums\StockAdjustmentStatus::Draft => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
            \App\Enums\StockAdjustmentStatus::Posted => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
            \App\Enums\StockAdjustmentStatus::Cancelled => 'bg-red-400/15 text-red-200 ring-red-300/20',
        };
    @endphp

    @if ($errors->any())
        <div class="alert-danger mb-6" role="alert">
            <div>
                <p class="font-extrabold">Tindakan belum dapat diproses.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-extrabold uppercase tracking-[.16em] text-sky-300">
                        Penyesuaian Stok
                    </p>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $statusTone }}">
                        {{ $adjustment->status->label() }}
                    </span>
                </div>
                <h1 class="mt-2 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $adjustment->adjustment_number }}
                </h1>
                <p class="mt-2 text-sm font-medium text-slate-300">
                    {{ $adjustment->adjustment_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }}
                    WIB · {{ $adjustment->creator->name }}
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:flex">
                <a href="{{ route('stock-adjustments.index') }}" class="button-secondary-dark">
                    Kembali
                </a>
                @if ($adjustment->status === \App\Enums\StockAdjustmentStatus::Draft)
                    <a href="{{ route('stock-adjustments.edit', $adjustment) }}" class="button-primary-inline">
                        Edit Draft
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Hasil Pemeriksaan</h2>
                        <p class="panel-subtitle">{{ $adjustment->items->count() }} barang diperiksa</p>
                    </div>
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="data-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Stok Sistem</th>
                                <th>Jumlah Fisik</th>
                                <th>Selisih</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adjustment->items as $line)
                                @php
                                    $difference = (float) $line->difference_quantity;
                                @endphp
                                <tr>
                                    <td>
                                        <p class="font-extrabold text-slate-900">{{ $line->item_name_snapshot }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $line->item_code_snapshot }}</p>
                                    </td>
                                    <td>
                                        {{ number_format((float) $line->system_quantity, 2, ',', '.') }}
                                        {{ $line->unit_snapshot }}
                                    </td>
                                    <td>
                                        {{ number_format((float) $line->physical_quantity, 2, ',', '.') }}
                                        {{ $line->unit_snapshot }}
                                    </td>
                                    <td>
                                        <span class="font-black {{ $difference > 0
                                            ? 'text-emerald-700'
                                            : ($difference < 0 ? 'text-red-700' : 'text-slate-500') }}">
                                            {{ $difference > 0 ? '+' : '' }}
                                            {{ number_format($difference, 2, ',', '.') }}
                                            {{ $line->unit_snapshot }}
                                        </span>
                                    </td>
                                    <td>{{ $line->notes ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($adjustment->items as $line)
                        @php
                            $difference = (float) $line->difference_quantity;
                        @endphp
                        <article class="p-5">
                            <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                {{ $line->item_code_snapshot }}
                            </p>
                            <h3 class="mt-1 font-black text-slate-950">{{ $line->item_name_snapshot }}</h3>
                            <div class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 p-4 text-center">
                                <div>
                                    <p class="text-[9px] font-bold uppercase text-slate-500">Sistem</p>
                                    <p class="mt-1 text-xs font-black text-slate-800">{{ $line->system_quantity }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold uppercase text-slate-500">Fisik</p>
                                    <p class="mt-1 text-xs font-black text-slate-800">{{ $line->physical_quantity }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-bold uppercase text-slate-500">Selisih</p>
                                    <p class="mt-1 text-xs font-black {{ $difference < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                                        {{ $difference > 0 ? '+' : '' }}{{ $line->difference_quantity }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </article>

            @if ($adjustment->status === \App\Enums\StockAdjustmentStatus::Draft)
                <article class="panel p-5 sm:p-6">
                    <h2 class="panel-title">Posting Penyesuaian</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Posting akan menyamakan stok sistem dengan jumlah fisik
                        dan mencatat selisih ke kartu stok. Tindakan tidak dapat
                        diulang atau diedit.
                    </p>
                    <form
                        method="POST"
                        action="{{ route('stock-adjustments.post', $adjustment) }}"
                        class="mt-5"
                        data-confirm-message="Posting penyesuaian stok ini?"
                    >
                        @csrf
                        <button type="submit" class="button-primary-inline w-full">
                            Posting Penyesuaian
                        </button>
                    </form>
                </article>
            @endif
        </div>

        <aside class="space-y-6">
            <article class="panel p-5 sm:p-6">
                <h2 class="panel-title">Dasar Penyesuaian</h2>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $adjustment->reason }}</p>
                <dl class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    @foreach ([
                        'Dibuat oleh' => $adjustment->creator->name,
                        'Diposting oleh' => $adjustment->postedBy?->name ?: 'Belum diposting',
                        'Catatan' => $adjustment->notes ?: 'Tidak ada',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-extrabold text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            @if ($adjustment->status === \App\Enums\StockAdjustmentStatus::Draft)
                <article class="panel p-5 sm:p-6">
                    <h2 class="panel-title">Batalkan Draft</h2>
                    <form
                        method="POST"
                        action="{{ route('stock-adjustments.cancel', $adjustment) }}"
                        class="mt-4 space-y-4"
                        data-confirm-message="Batalkan draft penyesuaian ini?"
                    >
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="cancellation_reason" class="form-label">Alasan Pembatalan</label>
                            <textarea
                                id="cancellation_reason"
                                name="cancellation_reason"
                                rows="3"
                                class="form-input py-3"
                                maxlength="2000"
                                required
                            ></textarea>
                        </div>
                        <button type="submit" class="secondary-button text-red-700">
                            Batalkan Draft
                        </button>
                    </form>
                </article>
            @elseif ($adjustment->status === \App\Enums\StockAdjustmentStatus::Cancelled)
                <article class="alert-danger">
                    <div>
                        <p class="font-extrabold">Penyesuaian dibatalkan</p>
                        <p class="mt-1 text-xs">{{ $adjustment->cancellation_reason }}</p>
                    </div>
                </article>
            @endif
        </aside>
    </section>
</x-layouts.app>
