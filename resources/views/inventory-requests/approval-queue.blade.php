<x-layouts.app
    title="Approval Permintaan Barang"
    header="Approval Permintaan Barang"
    eyebrow="Persediaan"
>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Meja Kerja Administrator</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Antrean Approval
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-700">
                Dahulukan permintaan yang paling lama, periksa kecukupan stok,
                lalu tetapkan keputusan dengan jejak pemeriksaan yang utuh.
            </p>
        </div>

        <a
            href="{{ route('inventory-requests.index') }}"
            class="secondary-button sm:w-auto"
        >
            Semua Permintaan
        </a>
    </section>

    <section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([
            [
                'label' => 'Total Antrean',
                'value' => $summary['total'],
                'tone' => 'bg-sky-100 text-sky-950 ring-sky-300',
            ],
            [
                'label' => 'Belum Diperiksa',
                'value' => $summary['submitted'],
                'tone' => 'bg-blue-100 text-blue-950 ring-blue-300',
            ],
            [
                'label' => 'Sedang Diperiksa',
                'value' => $summary['under_review'],
                'tone' => 'bg-violet-100 text-violet-950 ring-violet-300',
            ],
            [
                'label' => 'Menunggu Stok',
                'value' => $summary['waiting_stock'],
                'tone' => 'bg-amber-100 text-amber-950 ring-amber-300',
            ],
        ] as $card)
            <article class="stat-card p-4 sm:p-5">
                <span class="inline-flex rounded-xl px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] ring-1 ring-inset {{ $card['tone'] }}">
                    {{ $card['label'] }}
                </span>
                <p class="mt-4 text-3xl font-black tracking-tight text-slate-950">
                    {{ number_format($card['value'], 0, ',', '.') }}
                </p>
            </article>
        @endforeach
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Permintaan yang Memerlukan Tindakan</h2>
                <p class="panel-subtitle">
                    Urutan otomatis: belum diperiksa, sedang diperiksa, lalu menunggu stok.
                </p>
            </div>
        </div>

        <form
            method="GET"
            action="{{ route('inventory-requests.approval-queue') }}"
            class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-[minmax(0,1fr)_minmax(220px,.45fr)_auto] md:items-end sm:p-6"
        >
            <div>
                <label for="q" class="form-label">Cari Permintaan</label>
                <input
                    id="q"
                    name="q"
                    type="search"
                    value="{{ $filters['search'] }}"
                    class="form-input"
                    placeholder="Nomor, pegawai, NIP, unit, atau keperluan"
                >
            </div>
            <div>
                <label for="stage" class="form-label">Tahap Approval</label>
                <select id="stage" name="stage" class="form-input">
                    <option value="">Semua tahap</option>
                    @foreach ($stageOptions as $stageOption)
                        <option
                            value="{{ $stageOption->value }}"
                            @selected($filters['stage'] === $stageOption->value)
                        >
                            {{ $stageOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 md:flex">
                <a
                    href="{{ route('inventory-requests.approval-queue') }}"
                    class="secondary-button"
                >
                    Reset
                </a>
                <button type="submit" class="button-primary-inline">
                    Terapkan
                </button>
            </div>
        </form>

        @if ($inventoryRequests->isEmpty())
            <div class="empty-state">
                <p class="font-extrabold text-slate-800">
                    Tidak ada antrean approval yang sesuai.
                </p>
                <p class="mt-1 text-slate-600">
                    Semua permintaan pada filter ini sudah ditangani.
                </p>
            </div>
        @else
            <div class="hidden overflow-x-auto xl:block">
                <table class="data-table min-w-[1160px]">
                    <thead>
                        <tr>
                            <th>Prioritas & Permintaan</th>
                            <th>Pegawai</th>
                            <th>Keperluan</th>
                            <th>Kecukupan Stok</th>
                            <th>Tahap</th>
                            <th>Pemeriksa</th>
                            <th class="text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventoryRequests as $inventoryRequest)
                            @php
                                $readiness = $stockReadiness[$inventoryRequest->id];
                                $isUnreviewed = $inventoryRequest->status === \App\Enums\InventoryRequestStatus::Submitted;
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-slate-950 text-xs font-black text-white">
                                            {{ $inventoryRequests->firstItem() + $loop->index }}
                                        </span>
                                        <div>
                                            <p class="font-extrabold text-slate-950">
                                                {{ $inventoryRequest->request_number }}
                                            </p>
                                            <p class="mt-1 text-xs font-semibold text-slate-600">
                                                {{ ($inventoryRequest->submitted_at ?? $inventoryRequest->request_date)->copy()->timezone($displayTimezone)->translatedFormat('d F Y, H:i') }} WIB
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="font-extrabold text-slate-950">
                                        {{ $inventoryRequest->requester_name_snapshot }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        {{ $inventoryRequest->employee_number_snapshot ?: 'Tanpa NIP' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-600">
                                        {{ $inventoryRequest->work_unit_snapshot ?: 'Unit belum diisi' }}
                                    </p>
                                </td>
                                <td class="max-w-72">
                                    <p class="line-clamp-2 font-semibold leading-5 text-slate-800">
                                        {{ $inventoryRequest->purpose }}
                                    </p>
                                    <p class="mt-1 text-xs font-bold text-slate-600">
                                        {{ $inventoryRequest->items->count() }} jenis barang
                                    </p>
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $readiness['tone'] }}">
                                        {{ $readiness['label'] }}
                                    </span>
                                    <p class="mt-2 text-xs font-semibold text-slate-600">
                                        {{ $readiness['detail'] }}
                                    </p>
                                </td>
                                <td>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $inventoryRequest->status->badgeClasses() }}">
                                        {{ $inventoryRequest->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($inventoryRequest->reviewer)
                                        <p class="font-extrabold text-slate-900">
                                            {{ $inventoryRequest->reviewer->name }}
                                        </p>
                                        <p class="mt-1 text-xs font-semibold text-slate-600">
                                            {{ $inventoryRequest->reviewed_at?->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                        </p>
                                    @else
                                        <span class="font-bold text-slate-600">
                                            Belum ditugaskan
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (in_array($inventoryRequest->status, [
                                        \App\Enums\InventoryRequestStatus::Submitted,
                                        \App\Enums\InventoryRequestStatus::WaitingStock,
                                    ], true))
                                        <form
                                            method="POST"
                                            action="{{ route('inventory-requests.review', $inventoryRequest) }}"
                                            class="inline-flex"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-xl bg-slate-950 px-4 py-2 text-xs font-extrabold text-white transition hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-200"
                                            >
                                                {{ $isUnreviewed ? 'Mulai Pemeriksaan' : 'Periksa Ulang' }}
                                            </button>
                                        </form>
                                    @else
                                        <a
                                            href="{{ route('inventory-requests.show', $inventoryRequest) }}"
                                            class="inline-flex min-h-10 items-center justify-center whitespace-nowrap rounded-xl bg-slate-950 px-4 py-2 text-xs font-extrabold text-white transition hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-200"
                                        >
                                            Lanjutkan Keputusan
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 xl:hidden">
                @foreach ($inventoryRequests as $inventoryRequest)
                    @php
                        $readiness = $stockReadiness[$inventoryRequest->id];
                        $isUnreviewed = $inventoryRequest->status === \App\Enums\InventoryRequestStatus::Submitted;
                    @endphp
                    <article class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-[10px] font-black uppercase tracking-[.12em] text-sky-800">
                                    Prioritas {{ $inventoryRequests->firstItem() + $loop->index }}
                                    · {{ $inventoryRequest->request_number }}
                                </p>
                                <h3 class="mt-1 line-clamp-2 font-black text-slate-950">
                                    {{ $inventoryRequest->purpose }}
                                </h3>
                                <p class="mt-1 text-xs font-semibold text-slate-700">
                                    {{ $inventoryRequest->requester_name_snapshot }}
                                    · {{ $inventoryRequest->work_unit_snapshot ?: 'Unit belum diisi' }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold ring-1 ring-inset {{ $inventoryRequest->status->badgeClasses() }}">
                                {{ $inventoryRequest->status->label() }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[.1em] text-slate-600">
                                    Diajukan
                                </p>
                                <p class="mt-1 text-sm font-black text-slate-950">
                                    {{ ($inventoryRequest->submitted_at ?? $inventoryRequest->request_date)->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    {{ $inventoryRequest->items->count() }} jenis barang
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[.1em] text-slate-600">
                                    Kecukupan Stok
                                </p>
                                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold ring-1 ring-inset {{ $readiness['tone'] }}">
                                    {{ $readiness['label'] }}
                                </span>
                                <p class="mt-2 text-xs font-semibold text-slate-600">
                                    {{ $readiness['detail'] }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs font-semibold text-slate-700">
                                Pemeriksa:
                                <span class="font-black text-slate-950">
                                    {{ $inventoryRequest->reviewer?->name ?: 'Belum ditugaskan' }}
                                </span>
                            </p>
                            @if (in_array($inventoryRequest->status, [
                                \App\Enums\InventoryRequestStatus::Submitted,
                                \App\Enums\InventoryRequestStatus::WaitingStock,
                            ], true))
                                <form
                                    method="POST"
                                    action="{{ route('inventory-requests.review', $inventoryRequest) }}"
                                >
                                    @csrf
                                    <button type="submit" class="button-primary-inline w-full">
                                        {{ $isUnreviewed ? 'Mulai Pemeriksaan' : 'Periksa Ulang' }}
                                    </button>
                                </form>
                            @else
                                <a
                                    href="{{ route('inventory-requests.show', $inventoryRequest) }}"
                                    class="button-primary-inline"
                                >
                                    Lanjutkan Keputusan
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                {{ $inventoryRequests->links() }}
            </div>
        @endif
    </section>
</x-layouts.app>
