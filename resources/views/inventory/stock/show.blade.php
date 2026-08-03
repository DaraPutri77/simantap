<x-layouts.app
    title="Detail Pergerakan Stok"
    header="Detail Kartu Stok"
    eyebrow="Persediaan"
>
    @include('inventory.partials.tabs')

    @php
        $isInbound = $movement->isInbound();
        $unit = $movement->item->unit->symbol;
        $integrityTone = $integrity['overall']
            ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20'
            : 'bg-amber-400/15 text-amber-200 ring-amber-300/20';
    @endphp

    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-[0_24px_65px_rgba(15,23,42,.18)] sm:p-8">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_16%,rgba(14,165,233,.24),transparent_30%),radial-gradient(circle_at_88%_84%,rgba(37,99,235,.18),transparent_28%)]"></div>
        <div class="relative flex flex-col gap-6 xl:flex-row xl:items-center">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $isInbound
                        ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20'
                        : 'bg-red-400/15 text-red-200 ring-red-300/20' }}">
                        {{ $movement->movement_type->label() }}
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $integrityTone }}">
                        {{ $integrity['overall'] ? 'Saldo Konsisten' : 'Perlu Audit' }}
                    </span>
                </div>
                <h1 class="mt-3 break-words text-3xl font-black tracking-tight sm:text-4xl">
                    {{ $movement->transaction_number }}
                </h1>
                <p class="mt-2 text-sm font-medium text-slate-300">
                    {{ $movement->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }}
                    WIB · dicatat oleh {{ $movement->creator?->name ?: 'akun yang tidak lagi tersedia' }}
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:flex">
                <a href="{{ route('stock.index') }}" class="button-secondary-dark">
                    Kembali ke Kartu Stok
                </a>
                @if ($source['url'])
                    <a href="{{ $source['url'] }}" class="button-primary-inline">
                        Buka Transaksi Sumber
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if (! $integrity['overall'])
        <div class="alert-warning mt-6" role="alert">
            <div>
                <p class="font-black">Rangkaian saldo perlu diperiksa.</p>
                <p class="mt-1 text-xs leading-5">
                    Sistem tidak mengubah data secara otomatis. Periksa transaksi sumber
                    dan pergerakan sebelum atau sesudah catatan ini.
                </p>
            </div>
        </div>
    @endif

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Perhitungan Mutasi</h2>
                        <p class="panel-subtitle">Saldo akhir dihitung dari satu transaksi immutable</p>
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                    <div class="rounded-2xl bg-slate-100 p-4 text-slate-800">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">
                            Stok Awal
                        </p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number_format((float) $movement->stock_before, 2, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">{{ $unit }}</p>
                    </div>
                    <div class="rounded-2xl p-4 {{ $isInbound
                        ? 'bg-emerald-50 text-emerald-800'
                        : 'bg-red-50 text-red-800' }}">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] opacity-70">
                            {{ $isInbound ? 'Barang Masuk' : 'Barang Keluar' }}
                        </p>
                        <p class="mt-2 text-2xl font-black">
                            {{ $isInbound ? '+' : '-' }}{{ number_format($movement->movementQuantity(), 2, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">{{ $unit }}</p>
                    </div>
                    <div class="rounded-2xl bg-sky-50 p-4 text-sky-800">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                            Stok Akhir
                        </p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                        </p>
                        <p class="mt-1 text-xs font-bold">{{ $unit }}</p>
                    </div>
                </div>
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                    <p class="text-center text-xs font-extrabold text-slate-600">
                        {{ number_format((float) $movement->stock_before, 2, ',', '.') }}
                        {{ $isInbound ? '+' : '-' }}
                        {{ number_format($movement->movementQuantity(), 2, ',', '.') }}
                        = {{ number_format((float) $movement->stock_after, 2, ',', '.') }}
                        {{ $unit }}
                    </p>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Kontrol Integritas Ledger</h2>
                        <p class="panel-subtitle">Tiga pemeriksaan tanpa mengubah data transaksi</p>
                    </div>
                </div>
                <div class="grid gap-3 p-5 md:grid-cols-3 sm:p-6">
                    @foreach ([
                        [
                            'label' => 'Rumus transaksi',
                            'valid' => $integrity['formula'],
                            'description' => 'Saldo awal + masuk − keluar = saldo akhir.',
                        ],
                        [
                            'label' => 'Saldo sebelumnya',
                            'valid' => $integrity['previous'],
                            'description' => $previousMovement
                                ? 'Saldo akhir catatan sebelumnya sama dengan stok awal ini.'
                                : 'Catatan ini adalah pergerakan pertama barang.',
                        ],
                        [
                            'label' => 'Saldo berikutnya',
                            'valid' => $integrity['next'],
                            'description' => $nextMovement
                                ? 'Stok akhir ini sama dengan saldo awal catatan berikutnya.'
                                : 'Catatan ini adalah pergerakan terakhir barang.',
                        ],
                    ] as $check)
                        <div class="rounded-2xl p-4 ring-1 ring-inset {{ $check['valid']
                            ? 'bg-emerald-50 text-emerald-900 ring-emerald-200'
                            : 'bg-amber-50 text-amber-950 ring-amber-300' }}">
                            <p class="text-xs font-black">
                                {{ $check['valid'] ? 'Sesuai' : 'Perlu audit' }} · {{ $check['label'] }}
                            </p>
                            <p class="mt-2 text-xs leading-5 opacity-80">
                                {{ $check['description'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Rangkaian Pergerakan</h2>
                        <p class="panel-subtitle">Navigasi transaksi untuk barang yang sama</p>
                    </div>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2 sm:p-6">
                    @foreach ([
                        ['label' => 'Sebelumnya', 'record' => $previousMovement],
                        ['label' => 'Berikutnya', 'record' => $nextMovement],
                    ] as $adjacent)
                        @if ($adjacent['record'])
                            <a
                                href="{{ route('stock.show', $adjacent['record']) }}"
                                class="rounded-2xl border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50"
                            >
                                <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                                    {{ $adjacent['label'] }}
                                </p>
                                <p class="mt-2 font-black text-slate-950">
                                    {{ $adjacent['record']->transaction_number }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Saldo {{ number_format((float) $adjacent['record']->stock_after, 2, ',', '.') }}
                                    {{ $unit }} ·
                                    {{ $adjacent['record']->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </p>
                            </a>
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-slate-500">
                                <p class="text-[10px] font-black uppercase tracking-[.12em]">
                                    {{ $adjacent['label'] }}
                                </p>
                                <p class="mt-2 text-xs font-semibold">
                                    Tidak ada pergerakan lain pada sisi ini.
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>
            </article>
        </div>

        <aside class="space-y-6">
            <article class="panel p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-600">
                            Barang
                        </p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">
                            {{ $movement->item->name }}
                        </h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">
                            {{ $movement->item->item_code }} · {{ $movement->item->category->name }}
                        </p>
                    </div>
                    @if (! $movement->item->is_active || $movement->item->trashed())
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-600">
                            Nonaktif
                        </span>
                    @endif
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 text-sm">
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Stok fisik kini</dt>
                        <dd class="mt-1 font-black text-slate-900">
                            {{ number_format((float) $movement->item->current_stock, 2, ',', '.') }} {{ $unit }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Direservasi</dt>
                        <dd class="mt-1 font-black text-slate-900">
                            {{ number_format((float) $movement->item->reserved_stock, 2, ',', '.') }} {{ $unit }}
                        </dd>
                    </div>
                </dl>
                <a
                    href="{{ route('items.show', $movement->item) }}"
                    class="secondary-button mt-5"
                >
                    Lihat Detail Barang
                </a>
            </article>

            <article class="panel p-5 sm:p-6">
                <h2 class="panel-title">Transaksi Sumber</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    @foreach ([
                        'Jenis sumber' => $source['type'],
                        'Nomor sumber' => $source['number'],
                        'Status sumber' => $source['status'] ?: 'Tidak tersedia',
                        'Penerima barang' => $source['employee'] ?: 'Tidak berlaku',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                            <dd class="mt-1 break-words font-extrabold text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($source['url'])
                    <a href="{{ $source['url'] }}" class="secondary-button mt-5">
                        Buka Dokumen Sumber
                    </a>
                @endif
            </article>

            <article class="panel p-5 sm:p-6">
                <h2 class="panel-title">Jejak Pemrosesan</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Administrator</dt>
                        <dd class="mt-1 font-extrabold text-slate-900">
                            {{ $movement->creator?->name ?: 'Akun tidak tersedia' }}
                        </dd>
                        @if ($movement->creator?->employee_number)
                            <dd class="mt-1 text-xs font-semibold text-slate-500">
                                {{ $movement->creator->employee_number }}
                            </dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Waktu transaksi</dt>
                        <dd class="mt-1 font-extrabold text-slate-900">
                            {{ $movement->transaction_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i:s') }} WIB
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-slate-500">Keterangan</dt>
                        <dd class="mt-1 leading-6 text-slate-700">
                            {{ $movement->description ?: 'Tidak ada keterangan tambahan.' }}
                        </dd>
                    </div>
                </dl>
            </article>
        </aside>
    </section>
</x-layouts.app>
