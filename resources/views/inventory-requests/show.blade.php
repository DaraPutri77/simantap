@php
    $status = $inventoryRequest->status;
    $isEmployeeView = $routePrefix === 'my.inventory-requests';
@endphp

<x-layouts.app
    title="Detail Permintaan Barang"
    header="Detail Permintaan"
    eyebrow="Persediaan"
>
    <section class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div class="min-w-0">
            <p class="eyebrow">Form Permintaan Persediaan</p>
            <h1 class="mt-3 break-words text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                {{ $inventoryRequest->request_number }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                {{ $status->description() }}
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a
                href="{{ route($routePrefix.'.index') }}"
                class="secondary-button sm:w-auto"
            >
                Kembali
            </a>
            <a
                href="{{ route($routePrefix.'.pdf', $inventoryRequest) }}"
                class="button-primary-inline"
            >
                Unduh PDF
            </a>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert-danger mt-6">
            <div>
                <p class="font-black">Tindakan belum dapat diproses.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($status === \App\Enums\InventoryRequestStatus::RevisionRequired)
        <div class="alert-warning mt-6">
            <div>
                <p class="font-black">Perbaikan diperlukan</p>
                <p class="mt-1">{{ $inventoryRequest->revision_note }}</p>
            </div>
        </div>
    @elseif ($status === \App\Enums\InventoryRequestStatus::Rejected)
        <div class="alert-danger mt-6">
            <div>
                <p class="font-black">Permintaan ditolak</p>
                <p class="mt-1">{{ $inventoryRequest->rejection_reason }}</p>
            </div>
        </div>
    @elseif ($status === \App\Enums\InventoryRequestStatus::Cancelled)
        <div class="alert-danger mt-6">
            <div>
                <p class="font-black">Permintaan dibatalkan</p>
                <p class="mt-1">{{ $inventoryRequest->cancellation_reason }}</p>
            </div>
        </div>
    @elseif ($status === \App\Enums\InventoryRequestStatus::WaitingStock)
        <div class="alert-warning mt-6">
            <div>
                <p class="font-black">Menunggu ketersediaan stok</p>
                <p class="mt-1">{{ $inventoryRequest->admin_notes }}</p>
            </div>
        </div>
    @endif

    <section class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,.55fr)]">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Informasi Permintaan</h2>
                    <p class="panel-subtitle">
                        Identitas tersimpan sesuai kondisi saat formulir dibuat.
                    </p>
                </div>
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black ring-1 ring-inset {{ $status->badgeClasses() }}">
                    {{ $status->label() }}
                </span>
            </div>

            <dl class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
                @foreach ([
                    ['label' => 'Nama Pegawai', 'value' => $inventoryRequest->requester_name_snapshot],
                    ['label' => 'NIP / Identitas', 'value' => $inventoryRequest->employee_number_snapshot ?: 'Belum diisi'],
                    ['label' => 'Unit Kerja', 'value' => $inventoryRequest->work_unit_snapshot ?: 'Belum diisi'],
                    ['label' => 'Tanggal Permintaan', 'value' => $inventoryRequest->request_date->copy()->timezone($displayTimezone)->translatedFormat('d F Y')],
                ] as $detail)
                    <div>
                        <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-600">
                            {{ $detail['label'] }}
                        </dt>
                        <dd class="mt-1.5 font-extrabold text-slate-950">
                            {{ $detail['value'] }}
                        </dd>
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-600">
                        Keperluan
                    </dt>
                    <dd class="mt-1.5 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $inventoryRequest->purpose }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-600">
                        Catatan Pegawai
                    </dt>
                    <dd class="mt-1.5 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $inventoryRequest->notes ?: 'Tidak ada catatan.' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Waktu Proses</h2>
                    <p class="panel-subtitle">Semua waktu ditampilkan dalam WIB.</p>
                </div>
            </div>
            <dl class="space-y-4 p-5 sm:p-6">
                @foreach ([
                    ['label' => 'Diajukan', 'value' => $inventoryRequest->submitted_at],
                    ['label' => 'Diperiksa', 'value' => $inventoryRequest->reviewed_at],
                    ['label' => 'Disetujui', 'value' => $inventoryRequest->approved_at],
                    ['label' => 'Diserahkan', 'value' => $inventoryRequest->delivered_at],
                    ['label' => 'Diterima', 'value' => $inventoryRequest->received_at],
                ] as $moment)
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                        <dt class="text-xs font-bold text-slate-600">
                            {{ $moment['label'] }}
                        </dt>
                        <dd class="text-right text-xs font-black text-slate-950">
                            {{ $moment['value']
                                ? $moment['value']->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i')
                                : '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </article>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Daftar Barang</h2>
                <p class="panel-subtitle">
                    {{ $inventoryRequest->items->count() }} jenis barang dalam formulir
                </p>
            </div>
        </div>

        <div class="hidden overflow-x-auto lg:block">
            <table class="data-table min-w-[980px]">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Stok Tersedia</th>
                        <th>Diminta</th>
                        <th>Disetujui</th>
                        <th>Diserahkan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inventoryRequest->items as $line)
                        <tr>
                            <td>
                                <p class="font-extrabold text-slate-950">
                                    {{ $line->item_name_snapshot }}
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    {{ $line->item_code_snapshot }}
                                    ·
                                    {{ $line->unit_snapshot }}
                                </p>
                            </td>
                            <td class="font-black text-slate-900">
                                {{ number_format((float) $line->item->available_stock, 2, ',', '.') }}
                                {{ $line->unit_snapshot }}
                            </td>
                            <td class="font-black text-sky-800">
                                {{ number_format((float) $line->requested_quantity, 2, ',', '.') }}
                            </td>
                            <td class="font-black text-emerald-800">
                                {{ $line->approved_quantity !== null
                                    ? number_format((float) $line->approved_quantity, 2, ',', '.')
                                    : '—' }}
                            </td>
                            <td class="font-black text-cyan-900">
                                {{ $line->delivered_quantity !== null
                                    ? number_format((float) $line->delivered_quantity, 2, ',', '.')
                                    : '—' }}
                            </td>
                            <td class="max-w-72">
                                <p class="text-xs leading-5 text-slate-700">
                                    {{ $line->notes ?: '—' }}
                                </p>
                                @if ($line->admin_notes)
                                    <p class="mt-2 text-xs font-bold leading-5 text-amber-800">
                                        Admin: {{ $line->admin_notes }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-200 lg:hidden">
            @foreach ($inventoryRequest->items as $line)
                <article class="p-5">
                    <p class="text-[10px] font-black uppercase tracking-[.12em] text-sky-700">
                        {{ $line->item_code_snapshot }}
                    </p>
                    <h3 class="mt-1 font-black text-slate-950">
                        {{ $line->item_name_snapshot }}
                    </h3>
                    <div class="mt-4 grid grid-cols-3 gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <div>
                            <p class="text-[9px] font-bold uppercase text-slate-600">Diminta</p>
                            <p class="mt-1 text-xs font-black text-sky-800">
                                {{ number_format((float) $line->requested_quantity, 2, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase text-slate-600">Disetujui</p>
                            <p class="mt-1 text-xs font-black text-emerald-800">
                                {{ $line->approved_quantity !== null
                                    ? number_format((float) $line->approved_quantity, 2, ',', '.')
                                    : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase text-slate-600">Diserahkan</p>
                            <p class="mt-1 text-xs font-black text-cyan-900">
                                {{ $line->delivered_quantity !== null
                                    ? number_format((float) $line->delivered_quantity, 2, ',', '.')
                                    : '—' }}
                            </p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    @if ($isEmployeeView && $status->isEditable())
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Ajukan Permintaan</h2>
                    <p class="panel-subtitle">
                        Periksa data, bubuhkan tanda tangan, lalu kirim.
                    </p>
                </div>
                <a
                    href="{{ route('my.inventory-requests.edit', $inventoryRequest) }}"
                    class="secondary-button sm:w-auto"
                >
                    Ubah Draft
                </a>
            </div>
            <form
                method="POST"
                action="{{ route('my.inventory-requests.submit', $inventoryRequest) }}"
                class="space-y-5 p-5 sm:p-6"
                data-signature-form
            >
                @csrf
                @include('inventory-requests.partials.signature-pad', [
                    'padId' => 'submission',
                    'consentName' => 'signature_consent',
                    'consentText' => 'Saya menyatakan data permintaan ini benar dan tanda tangan dibubuhkan oleh saya.',
                ])
                <button
                    type="submit"
                    class="button-primary-inline w-full sm:w-auto"
                    data-submit-label="Mengajukan..."
                >
                    Tanda Tangan & Ajukan
                </button>
            </form>
        </section>
    @endif

    @if ($canManage && in_array($status, [
        \App\Enums\InventoryRequestStatus::Submitted,
        \App\Enums\InventoryRequestStatus::WaitingStock,
    ], true))
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Mulai Pemeriksaan</h2>
                    <p class="panel-subtitle">
                        Status akan berubah menjadi Menunggu Persetujuan.
                    </p>
                </div>
            </div>
            <form
                method="POST"
                action="{{ route('inventory-requests.review', $inventoryRequest) }}"
                class="p-5 sm:p-6"
            >
                @csrf
                <button type="submit" class="button-primary-inline">
                    Mulai Pemeriksaan
                </button>
            </form>
        </section>
    @endif

    @if ($canManage && $status === \App\Enums\InventoryRequestStatus::UnderReview)
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Keputusan Persetujuan</h2>
                    <p class="panel-subtitle">
                        Jumlah yang disetujui akan direservasi, tetapi stok fisik belum berkurang.
                    </p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('inventory-requests.approve', $inventoryRequest) }}"
                class="space-y-5 p-5 sm:p-6"
                data-signature-form
            >
                @csrf
                <div class="space-y-4">
                    @foreach ($inventoryRequest->items as $line)
                        <article class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
                            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_minmax(0,.8fr)]">
                                <div>
                                    <p class="font-black text-slate-950">
                                        {{ $line->item_name_snapshot }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        Diminta:
                                        {{ number_format((float) $line->requested_quantity, 2, ',', '.') }}
                                        {{ $line->unit_snapshot }}
                                        · Stok tersedia:
                                        {{ number_format((float) $line->item->available_stock, 2, ',', '.') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="form-label" for="approved_{{ $line->id }}">
                                        Jumlah Disetujui
                                    </label>
                                    <input
                                        id="approved_{{ $line->id }}"
                                        name="items[{{ $line->id }}][approved_quantity]"
                                        type="number"
                                        value="{{ old("items.{$line->id}.approved_quantity", $line->requested_quantity) }}"
                                        class="form-input"
                                        min="0"
                                        max="{{ $line->requested_quantity }}"
                                        step="0.01"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="form-label" for="admin_note_{{ $line->id }}">
                                        Catatan Barang
                                    </label>
                                    <input
                                        id="admin_note_{{ $line->id }}"
                                        name="items[{{ $line->id }}][admin_notes]"
                                        type="text"
                                        value="{{ old("items.{$line->id}.admin_notes") }}"
                                        class="form-input"
                                        maxlength="1000"
                                    >
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div>
                    <label for="admin_notes" class="form-label">
                        Catatan Persetujuan
                        <span class="font-medium text-slate-600">(opsional)</span>
                    </label>
                    <textarea
                        id="admin_notes"
                        name="admin_notes"
                        rows="3"
                        class="form-input min-h-24 py-4"
                    >{{ old('admin_notes') }}</textarea>
                </div>

                @include('inventory-requests.partials.signature-pad', [
                    'padId' => 'approval',
                    'consentName' => 'signature_consent',
                    'consentText' => 'Saya telah memeriksa permintaan dan menyetujui jumlah yang tercantum.',
                ])

                <button
                    type="submit"
                    class="button-primary-inline w-full sm:w-auto"
                    data-submit-label="Menyimpan Persetujuan..."
                >
                    Setujui Permintaan
                </button>
            </form>
        </section>

        <section class="mt-6 grid gap-4 xl:grid-cols-3">
            <form
                method="POST"
                action="{{ route('inventory-requests.revision', $inventoryRequest) }}"
                class="panel p-5 sm:p-6"
            >
                @csrf
                <h3 class="font-black text-slate-950">Minta Perbaikan</h3>
                <label for="revision_note" class="form-label mt-4">
                    Catatan Perbaikan
                </label>
                <textarea
                    id="revision_note"
                    name="revision_note"
                    rows="4"
                    class="form-input min-h-28 py-4"
                    required
                >{{ old('revision_note') }}</textarea>
                <button type="submit" class="secondary-button mt-4">
                    Kembalikan ke Pegawai
                </button>
            </form>

            <form
                method="POST"
                action="{{ route('inventory-requests.await-stock', $inventoryRequest) }}"
                class="panel p-5 sm:p-6"
            >
                @csrf
                <h3 class="font-black text-slate-950">Menunggu Stok</h3>
                <label for="await_admin_notes" class="form-label mt-4">
                    Catatan Ketersediaan
                </label>
                <textarea
                    id="await_admin_notes"
                    name="admin_notes"
                    rows="4"
                    class="form-input min-h-28 py-4"
                    required
                >{{ old('admin_notes') }}</textarea>
                <button type="submit" class="secondary-button mt-4">
                    Tandai Menunggu Stok
                </button>
            </form>

            <form
                method="POST"
                action="{{ route('inventory-requests.reject', $inventoryRequest) }}"
                class="panel p-5 sm:p-6"
                data-confirm-message="Tolak permintaan ini?"
            >
                @csrf
                <h3 class="font-black text-red-800">Tolak Permintaan</h3>
                <label for="rejection_reason" class="form-label mt-4">
                    Alasan Penolakan
                </label>
                <textarea
                    id="rejection_reason"
                    name="rejection_reason"
                    rows="4"
                    class="form-input min-h-28 py-4"
                    required
                >{{ old('rejection_reason') }}</textarea>
                <button type="submit" class="danger-button mt-4">
                    Tolak Permintaan
                </button>
            </form>
        </section>
    @endif

    @if ($canManage && in_array($status, [
        \App\Enums\InventoryRequestStatus::Approved,
        \App\Enums\InventoryRequestStatus::PartiallyApproved,
    ], true))
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Persiapan Penyerahan</h2>
                    <p class="panel-subtitle">
                        Pastikan barang fisik sudah dikumpulkan sebelum melanjutkan.
                    </p>
                </div>
            </div>
            <form
                method="POST"
                action="{{ route('inventory-requests.ready', $inventoryRequest) }}"
                class="p-5 sm:p-6"
            >
                @csrf
                <button type="submit" class="button-primary-inline">
                    Tandai Siap Diserahkan
                </button>
            </form>
        </section>
    @endif

    @if ($canManage && $status === \App\Enums\InventoryRequestStatus::ReadyForDelivery)
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Catat Penyerahan Barang</h2>
                    <p class="panel-subtitle">
                        Tindakan ini mengurangi stok fisik dan membuat kartu stok.
                    </p>
                </div>
            </div>
            <form
                method="POST"
                action="{{ route('inventory-requests.deliver', $inventoryRequest) }}"
                class="space-y-5 p-5 sm:p-6"
                data-confirm-message="Catat penyerahan dan kurangi stok sekarang?"
            >
                @csrf
                <div class="space-y-4">
                    @foreach ($inventoryRequest->items as $line)
                        <article class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
                            <div class="grid items-end gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                                <div>
                                    <p class="font-black text-slate-950">
                                        {{ $line->item_name_snapshot }}
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">
                                        Disetujui:
                                        {{ number_format((float) $line->approved_quantity, 2, ',', '.') }}
                                        {{ $line->unit_snapshot }}
                                    </p>
                                </div>
                                <div>
                                    <label class="form-label" for="delivered_{{ $line->id }}">
                                        Jumlah Diserahkan
                                    </label>
                                    <input
                                        id="delivered_{{ $line->id }}"
                                        name="items[{{ $line->id }}][delivered_quantity]"
                                        type="number"
                                        value="{{ old("items.{$line->id}.delivered_quantity", $line->approved_quantity) }}"
                                        class="form-input"
                                        min="0"
                                        max="{{ $line->approved_quantity }}"
                                        step="0.01"
                                        required
                                    >
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div>
                    <label for="delivery_notes" class="form-label">
                        Catatan Penyerahan
                        <span class="font-medium text-slate-600">(opsional)</span>
                    </label>
                    <textarea
                        id="delivery_notes"
                        name="delivery_notes"
                        rows="3"
                        class="form-input min-h-24 py-4"
                    >{{ old('delivery_notes') }}</textarea>
                </div>
                <button
                    type="submit"
                    class="button-primary-inline w-full sm:w-auto"
                    data-submit-label="Mencatat Penyerahan..."
                >
                    Serahkan & Kurangi Stok
                </button>
            </form>
        </section>
    @endif

    @if ($isEmployeeView && $status === \App\Enums\InventoryRequestStatus::Delivered)
        <section class="panel mt-6">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Konfirmasi Penerimaan</h2>
                    <p class="panel-subtitle">
                        Pastikan jumlah fisik sesuai sebelum menyelesaikan permintaan.
                    </p>
                </div>
            </div>
            <form
                method="POST"
                action="{{ route('my.inventory-requests.confirm-receipt', $inventoryRequest) }}"
                class="space-y-5 p-5 sm:p-6"
                data-signature-form
            >
                @csrf
                @include('inventory-requests.partials.signature-pad', [
                    'padId' => 'receipt',
                    'consentName' => 'receipt_consent',
                    'consentText' => 'Saya telah menerima barang sesuai jumlah penyerahan yang tercantum.',
                ])
                <button
                    type="submit"
                    class="button-primary-inline w-full sm:w-auto"
                    data-submit-label="Mengonfirmasi..."
                >
                    Konfirmasi Barang Diterima
                </button>
            </form>
        </section>
    @endif

    @if (! $status->isFinal() && $status !== \App\Enums\InventoryRequestStatus::Delivered)
        @can('cancel', $inventoryRequest)
            <section class="panel mt-6 border-red-300">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title text-red-800">Batalkan Permintaan</h2>
                        <p class="panel-subtitle">
                            Reservasi stok akan dilepas otomatis jika sudah ada persetujuan.
                        </p>
                    </div>
                </div>
                <form
                    method="POST"
                    action="{{ route($routePrefix.'.cancel', $inventoryRequest) }}"
                    class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-6"
                    data-confirm-message="Batalkan permintaan ini?"
                >
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="cancellation_reason" class="form-label">
                            Alasan Pembatalan
                        </label>
                        <input
                            id="cancellation_reason"
                            name="cancellation_reason"
                            type="text"
                            class="form-input"
                            minlength="5"
                            maxlength="3000"
                            required
                        >
                    </div>
                    <button type="submit" class="danger-button sm:w-auto">
                        Batalkan Permintaan
                    </button>
                </form>
            </section>
        @endcan
    @endif

    <section class="mt-6 grid gap-4 xl:grid-cols-2">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Riwayat Status</h2>
                    <p class="panel-subtitle">Jejak perubahan tidak dapat dihapus.</p>
                </div>
            </div>
            <ol class="divide-y divide-slate-200">
                @forelse ($inventoryRequest->statusHistories->sortByDesc('changed_at') as $history)
                    <li class="flex gap-4 p-5 sm:p-6">
                        <span class="mt-1 h-3 w-3 shrink-0 rounded-full bg-sky-600 ring-4 ring-sky-100"></span>
                        <div class="min-w-0">
                            <p class="font-black text-slate-950">
                                {{ $history->new_status->label() }}
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">
                                {{ $history->changed_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                WIB
                                ·
                                {{ $history->changer?->name ?: 'Sistem' }}
                            </p>
                            @if ($history->notes)
                                <p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-700">{{ $history->notes }}</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="empty-state">Riwayat belum tersedia.</li>
                @endforelse
            </ol>
        </article>

        <article class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Bukti Tanda Tangan</h2>
                    <p class="panel-subtitle">
                        Gambar dan identitas penanda tangan tersimpan bersama waktu transaksi.
                    </p>
                </div>
            </div>
            <div class="space-y-4 p-5 sm:p-6">
                @foreach ([
                    ['label' => 'Pengajuan Pegawai', 'image' => $submissionSignature, 'signature' => $inventoryRequest->submissionSignature()],
                    ['label' => 'Persetujuan Administrator', 'image' => $approvalSignature, 'signature' => $inventoryRequest->approvalSignature()],
                    ['label' => 'Konfirmasi Penerimaan', 'image' => $receiptSignature, 'signature' => $inventoryRequest->receiptSignature()],
                ] as $signatureCard)
                    <div class="rounded-2xl border border-slate-300 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-600">
                            {{ $signatureCard['label'] }}
                        </p>
                        @if ($signatureCard['image'] && $signatureCard['signature'])
                            <img
                                src="{{ $signatureCard['image'] }}"
                                alt="Tanda tangan {{ $signatureCard['signature']->signer_name_snapshot }}"
                                class="mt-3 h-24 w-full rounded-xl border border-slate-300 bg-white object-contain"
                            >
                            <p class="mt-2 text-xs font-black text-slate-950">
                                {{ $signatureCard['signature']->signer_name_snapshot }}
                            </p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-600">
                                {{ $signatureCard['signature']->signed_at->copy()->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }}
                                WIB
                            </p>
                        @else
                            <p class="mt-3 text-xs font-semibold text-slate-600">
                                Belum tersedia.
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    </section>
</x-layouts.app>
