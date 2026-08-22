@php
    $status = $vehicleLoan->status;
    $startWib = $vehicleLoan->planned_start_at->timezone($displayTimezone);
    $endWib = $vehicleLoan->planned_end_at->timezone($displayTimezone);
@endphp

<x-layouts.app
    :title="$vehicleLoan->loan_number"
    header="Detail Peminjaman"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Form Peminjaman Kendaraan Dinas</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                    {{ $vehicleLoan->loan_number }}
                </h1>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-black ring-1 ring-inset {{ $status->badgeClasses() }}">
                    {{ $status->label() }}
                </span>
            </div>
            <p class="mt-3 max-w-3xl text-sm font-semibold leading-6 text-slate-600">
                {{ $status->description() }}
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">
            <a
                href="{{ route($routePrefix.'.pdf', $vehicleLoan) }}"
                class="secondary-button sm:w-auto"
            >
                Unduh PDF
            </a>
            @if (! $canManage)
                @can('update', $vehicleLoan)
                    <a
                        href="{{ route('my.vehicle-loans.edit', $vehicleLoan) }}"
                        class="button-primary-inline"
                    >
                        Ubah Draft
                    </a>
                @endcan
            @endif
        </div>
    </section>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-950">
            {{ session('status') }}
        </div>
    @endif

    @if (! $canManage)
        @can('submit', $vehicleLoan)
        <div class="mt-6 rounded-3xl border-2 border-amber-300 bg-amber-50 p-5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[.14em] text-amber-800">
                Langkah 2 dari 2 · Belum terkirim
            </p>
            <h2 class="mt-2 text-xl font-black text-amber-950">
                Draft belum dikirim ke Administrator
            </h2>
            <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-amber-900">
                Menyimpan draft belum sama dengan mengajukan peminjaman.
                Bubuhkan tanda tangan lalu klik tombol kirim agar nomor
                {{ $vehicleLoan->loan_number }} masuk ke antrean Administrator.
            </p>
            <a
                href="#kirim-pengajuan"
                class="button-primary-inline mt-4"
            >
                Lanjut Tanda Tangan & Kirim
            </a>
        </div>
        @endcan
    @endif

    @if (! $canManage && in_array($status, [
        \App\Enums\VehicleLoanStatus::Submitted,
        \App\Enums\VehicleLoanStatus::UnderReview,
    ], true))
        <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold leading-6 text-sky-950">
            Pengajuan <strong>{{ $vehicleLoan->loan_number }}</strong> sudah terkirim
            dan sedang berada dalam proses pemeriksaan Administrator.
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-danger mt-6">
            <strong>Tindakan belum dapat diproses.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat-card p-5">
            <p class="eyebrow">Peminjam</p>
            <p class="mt-3 text-lg font-black text-slate-950">
                {{ $vehicleLoan->borrower_name_snapshot }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                {{ $vehicleLoan->employee_number_snapshot ?: '-' }} ·
                {{ $vehicleLoan->work_unit_snapshot ?: '-' }}
            </p>
        </article>
        <article class="stat-card p-5">
            <p class="eyebrow">Kendaraan</p>
            <p class="mt-3 text-lg font-black text-slate-950">
                {{ $vehicleLoan->license_plate_snapshot }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                {{ $vehicleLoan->vehicle_code_snapshot }} ·
                {{ $vehicleLoan->vehicle_name_snapshot }}
            </p>
        </article>
        <article class="stat-card p-5">
            <p class="eyebrow">Mulai</p>
            <p class="mt-3 text-lg font-black text-slate-950">
                {{ $startWib->translatedFormat('d M Y') }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                {{ $startWib->format('H:i') }} WIB
            </p>
        </article>
        <article class="stat-card p-5">
            <p class="eyebrow">Selesai</p>
            <p class="mt-3 text-lg font-black text-slate-950">
                {{ $endWib->translatedFormat('d M Y') }}
            </p>
            <p class="mt-1 text-xs font-semibold text-slate-600">
                {{ $endWib->format('H:i') }} WIB
            </p>
        </article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,.8fr)]">
        <div class="space-y-6">
            <section class="panel p-5 sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="eyebrow">Informasi Perjalanan</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">
                            Keperluan dan Tujuan
                        </h2>
                    </div>
                    <a
                        href="{{ route('vehicles.show', $vehicleLoan->vehicle) }}"
                        class="inline-flex rounded-lg px-3 py-2 text-xs font-black text-sky-700 transition hover:bg-sky-50 hover:text-sky-900"
                    >
                        Lihat Kendaraan
                    </a>
                </div>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Keperluan
                        </dt>
                        <dd class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $vehicleLoan->purpose }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Tujuan Perjalanan
                        </dt>
                        <dd class="mt-2 text-sm font-bold text-slate-900">
                            {{ $vehicleLoan->destination }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Nomor Telepon
                        </dt>
                        <dd class="mt-2 text-sm font-bold text-slate-900">
                            {{ $vehicleLoan->phone_snapshot }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Keterangan Pendukung
                        </dt>
                        <dd class="mt-2 text-sm font-semibold leading-6 text-slate-700">
                            {{ $vehicleLoan->reason ?: '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Catatan Peminjam
                        </dt>
                        <dd class="mt-2 text-sm font-semibold leading-6 text-slate-700">
                            {{ $vehicleLoan->notes ?: '-' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="panel p-5 sm:p-6">
                <div class="border-b border-slate-200 pb-5">
                    <p class="eyebrow">Persetujuan</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">
                        Verifikasi Peminjaman
                    </h2>
                </div>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Diperiksa Oleh
                        </dt>
                        <dd class="mt-2 text-sm font-bold text-slate-900">
                            {{ $vehicleLoan->reviewer?->name ?: '-' }}
                        </dd>
                        @if ($vehicleLoan->reviewed_at)
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{ $vehicleLoan->reviewed_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                            </p>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-black uppercase tracking-[.12em] text-slate-500">
                            Disetujui Oleh
                        </dt>
                        <dd class="mt-2 text-sm font-bold text-slate-900">
                            {{ $vehicleLoan->approver?->name ?: '-' }}
                        </dd>
                        @if ($vehicleLoan->approved_at)
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{ $vehicleLoan->approved_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                            </p>
                        @endif
                    </div>
                    @if ($vehicleLoan->admin_notes)
                        <div class="sm:col-span-2 rounded-2xl border border-sky-200 bg-sky-50 p-4">
                            <dt class="text-xs font-black uppercase tracking-[.12em] text-sky-700">
                                Catatan Administrator
                            </dt>
                            <dd class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-sky-950">{{ $vehicleLoan->admin_notes }}</dd>
                        </div>
                    @endif
                    @if ($vehicleLoan->rejection_reason)
                        <div class="sm:col-span-2 rounded-2xl border border-red-200 bg-red-50 p-4">
                            <dt class="text-xs font-black uppercase tracking-[.12em] text-red-700">
                                Alasan Penolakan
                            </dt>
                            <dd class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-red-950">{{ $vehicleLoan->rejection_reason }}</dd>
                        </div>
                    @endif
                    @if ($vehicleLoan->cancellation_reason)
                        <div class="sm:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <dt class="text-xs font-black uppercase tracking-[.12em] text-amber-800">
                                Alasan Pembatalan
                            </dt>
                            <dd class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-amber-950">{{ $vehicleLoan->cancellation_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="panel p-5 sm:p-6">
                <div class="border-b border-slate-200 pb-5">
                    <p class="eyebrow">Jejak Proses</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">
                        Riwayat Status
                    </h2>
                </div>

                <ol class="mt-5 space-y-4">
                    @forelse ($vehicleLoan->statusHistories->sortByDesc('changed_at') as $history)
                        <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $history->new_status->badgeClasses() }}">
                                        {{ $history->new_status->label() }}
                                    </span>
                                    <p class="mt-2 text-sm font-bold text-slate-900">
                                        {{ $history->changer?->name ?: 'Sistem' }}
                                    </p>
                                </div>
                                <time class="text-xs font-semibold text-slate-500">
                                    {{ $history->changed_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                                </time>
                            </div>
                            @if ($history->notes)
                                <p class="mt-3 whitespace-pre-line text-sm font-semibold leading-6 text-slate-600">{{ $history->notes }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="empty-state">Belum ada riwayat status.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="panel p-5 sm:p-6">
                <p class="eyebrow">Tanda Tangan Peminjam</p>
                @if ($submissionSignature)
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                        <img
                            src="{{ $submissionSignature }}"
                            alt="Tanda tangan {{ $vehicleLoan->borrower_name_snapshot }}"
                            class="mx-auto max-h-32 max-w-full"
                        >
                    </div>
                    <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">
                        Ditandatangani saat pengajuan dan dilindungi checksum transaksi.
                    </p>
                @else
                    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold leading-6 text-slate-600">
                        Tanda tangan akan tersimpan ketika draft diajukan.
                    </div>
                @endif
            </section>

            @if (! $canManage)
                @can('submit', $vehicleLoan)
                <form
                    id="kirim-pengajuan"
                    method="POST"
                    action="{{ route('my.vehicle-loans.submit', $vehicleLoan) }}"
                    class="panel p-5 sm:p-6 scroll-mt-28"
                    data-signature-form
                >
                    @csrf
                    <p class="eyebrow">Ajukan Draft</p>
                    <h2 class="mt-2 text-lg font-black text-slate-950">
                        Tanda Tangan dan Kirim
                    </h2>
                    <p class="mt-2 text-xs font-semibold leading-5 text-slate-600">
                        Jadwal akan diperiksa ulang sebelum masuk antrean Administrator.
                    </p>
                    <div class="mt-5">
                        @include('inventory-requests.partials.signature-pad', [
                            'padId' => 'vehicle_loan_submission',
                            'consentName' => 'signature_consent',
                            'consentText' => 'Saya menyatakan data peminjaman benar dan tanda tangan ini dibubuhkan oleh saya.',
                        ])
                    </div>
                    <button
                        type="submit"
                        class="primary-button mt-5"
                        data-submit-label="Mengajukan..."
                    >
                        Tanda Tangani & Kirim ke Administrator
                    </button>
                </form>
                @endcan
            @endif

            @if ($canManage && $status === \App\Enums\VehicleLoanStatus::Submitted)
                <form
                    method="POST"
                    action="{{ route('vehicle-loans.review', $vehicleLoan) }}"
                    class="panel p-5 sm:p-6"
                >
                    @csrf
                    <p class="eyebrow">Antrean Approval</p>
                    <h2 class="mt-2 text-lg font-black text-slate-950">
                        Mulai Pemeriksaan
                    </h2>
                    <p class="mt-2 text-xs font-semibold leading-5 text-slate-600">
                        Pastikan jadwal, tujuan, STNK, dan kondisi operasional kendaraan sesuai.
                    </p>
                    <button type="submit" class="primary-button mt-5">
                        Mulai Periksa
                    </button>
                </form>
            @endif

            @if ($canManage && $status === \App\Enums\VehicleLoanStatus::UnderReview)
                <form
                    method="POST"
                    action="{{ route('vehicle-loans.approve', $vehicleLoan) }}"
                    class="panel p-5 sm:p-6"
                    data-signature-form
                >
                    @csrf
                    <p class="eyebrow">Persetujuan</p>
                    <h2 class="mt-2 text-lg font-black text-slate-950">
                        Reservasi Kendaraan
                    </h2>
                    <label for="admin_notes" class="form-label mt-4">
                        Catatan Administrator
                    </label>
                    <textarea
                        id="admin_notes"
                        name="admin_notes"
                        rows="3"
                        class="form-input min-h-24 py-4"
                        placeholder="Opsional"
                    >{{ old('admin_notes') }}</textarea>

                    <div class="mt-5">
                        @include('inventory-requests.partials.signature-pad', [
                            'padId' => 'vehicle_loan_approval',
                            'consentName' => 'approval_consent',
                            'consentText' => 'Saya menyatakan telah memeriksa dan menyetujui peminjaman ini serta tanda tangan ini dibubuhkan oleh saya.',
                        ])
                    </div>

                    <button type="submit" class="primary-button mt-5">
                        Tanda Tangani dan Setujui Peminjaman
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('vehicle-loans.reject', $vehicleLoan) }}"
                    class="panel p-5 sm:p-6"
                    data-confirm-message="Tolak peminjaman ini?"
                >
                    @csrf
                    <h2 class="font-black text-red-800">Tolak Peminjaman</h2>
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
                        Tolak Peminjaman
                    </button>
                </form>
            @endif

            @can('cancel', $vehicleLoan)
                <form
                    method="POST"
                    action="{{ route($routePrefix.'.cancel', $vehicleLoan) }}"
                    class="panel p-5 sm:p-6"
                    data-confirm-message="Batalkan peminjaman ini?"
                >
                    @csrf
                    @method('PATCH')
                    <h2 class="font-black text-amber-900">Batalkan Peminjaman</h2>
                    <label for="cancellation_reason" class="form-label mt-4">
                        Alasan Pembatalan
                    </label>
                    <textarea
                        id="cancellation_reason"
                        name="cancellation_reason"
                        rows="3"
                        class="form-input min-h-24 py-4"
                        required
                    >{{ old('cancellation_reason') }}</textarea>
                    <button type="submit" class="secondary-button mt-4">
                        Batalkan
                    </button>
                </form>
            @endcan

            @if ($status === \App\Enums\VehicleLoanStatus::Approved)
                <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="font-black text-emerald-950">Jadwal Terkunci</p>
                    <p class="mt-2 text-xs font-semibold leading-5 text-emerald-900">
                        Kendaraan sudah direservasi. Proses serah terima dan pemeriksaan awal dilanjutkan pada tahap berikutnya.
                    </p>
                </div>
            @endif
        </aside>
    </div>

    @if (! $canManage)
        <script data-role-session-guard>
            (() => {
                const adminDetailUrl = @json(route('vehicle-loans.show', $vehicleLoan));
                let roleCheckInFlight = false;

                const redirectIfSessionBecameAdministrator = async () => {
                    if (roleCheckInFlight || document.visibilityState !== 'visible') {
                        return;
                    }

                    roleCheckInFlight = true;

                    try {
                        const response = await fetch(adminDetailUrl, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'text/html',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (response.ok) {
                            window.location.replace(adminDetailUrl);
                        }
                    } catch (error) {
                        // Network errors must never block the employee workflow.
                    } finally {
                        roleCheckInFlight = false;
                    }
                };

                window.addEventListener('focus', redirectIfSessionBecameAdministrator);
                window.addEventListener('pageshow', redirectIfSessionBecameAdministrator);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        redirectIfSessionBecameAdministrator();
                    }
                });
            })();
        </script>
    @endif

</x-layouts.app>
