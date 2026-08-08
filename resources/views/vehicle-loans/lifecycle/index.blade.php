<x-layouts.app
    :title="$isAdminWorkspace ? 'Serah Terima Kendaraan' : 'Pengembalian Kendaraan'"
    :header="$isAdminWorkspace ? 'Serah Terima Kendaraan' : 'Pengembalian Kendaraan'"
    eyebrow="Kendaraan"
>
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">
                {{ $isAdminWorkspace ? 'Kendali Operasional' : 'Layanan Pegawai' }}
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                {{ $isAdminWorkspace ? 'Serah Terima dan Pemeriksaan Kendaraan' : 'Pengambilan dan Pengembalian Kendaraan' }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                @if ($isAdminWorkspace)
                    Catat kondisi awal sebelum kendaraan diserahkan, periksa kondisi akhir setelah dikembalikan, dan pastikan status kendaraan berubah secara tertelusur.
                @else
                    Konfirmasikan serah terima kendaraan yang sudah diperiksa dan ajukan pengembalian kendaraan yang sedang Anda gunakan.
                @endif
            </p>
        </div>

        <a
            href="{{ route($isAdminWorkspace ? 'vehicle-loans.index' : 'my.vehicle-loans.index') }}"
            class="secondary-button sm:w-auto"
        >
            Daftar Peminjaman
        </a>
    </section>

    @if (session('status'))
        <div class="alert-success mt-6">
            <strong>Berhasil.</strong>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-danger mt-6">
            <strong>Tindakan belum dapat diproses.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Menunggu Checkout', 'value' => $summary['ready_for_checkout'], 'tone' => 'bg-sky-100 text-sky-900 ring-sky-300'],
            ['label' => 'Siap Diambil', 'value' => $summary['ready_for_pickup'], 'tone' => 'bg-emerald-100 text-emerald-900 ring-emerald-300'],
            ['label' => 'Sedang Dipinjam', 'value' => $summary['borrowed'], 'tone' => 'bg-cyan-100 text-cyan-950 ring-cyan-300'],
            ['label' => 'Menunggu Return Check', 'value' => $summary['awaiting_return'], 'tone' => 'bg-amber-100 text-amber-950 ring-amber-300'],
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

    <section class="mt-6 space-y-5">
        @forelse ($vehicleLoans as $vehicleLoan)
            @php
                $checkoutCheck = $vehicleLoan->checkoutCheck();
                $returnCheck = $vehicleLoan->returnCheck();
                $status = $vehicleLoan->status;
            @endphp

            <article class="panel overflow-hidden">
                <div class="border-b border-slate-200 p-5 sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl font-black text-slate-950">
                                    {{ $vehicleLoan->loan_number }}
                                </h2>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $status->badgeClasses() }}">
                                    {{ $status->label() }}
                                </span>
                                @if ($vehicleLoan->wasMarkedOverdue())
                                    <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black text-red-800 ring-1 ring-inset ring-red-300">
                                        Terlambat
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm font-bold text-slate-800">
                                {{ $vehicleLoan->license_plate_snapshot }} · {{ $vehicleLoan->vehicle_name_snapshot }}
                            </p>
                            @if ($isAdminWorkspace)
                                <p class="mt-1 text-xs font-semibold text-slate-600">
                                    {{ $vehicleLoan->borrower_name_snapshot }} · {{ $vehicleLoan->employee_number_snapshot ?: '-' }} · {{ $vehicleLoan->work_unit_snapshot ?: '-' }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ route($isAdminWorkspace ? 'vehicle-loans.show' : 'my.vehicle-loans.show', $vehicleLoan) }}"
                                class="secondary-button sm:w-auto"
                            >
                                Detail Peminjaman
                            </a>
                            <a
                                href="{{ route('vehicle-loan-lifecycle.pdf', $vehicleLoan) }}"
                                class="secondary-button sm:w-auto"
                            >
                                PDF Serah Terima
                            </a>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Rencana Mulai</dt>
                            <dd class="mt-1 text-xs font-bold text-slate-900">
                                {{ $vehicleLoan->planned_start_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Rencana Selesai</dt>
                            <dd class="mt-1 text-xs font-bold text-slate-900">
                                {{ $vehicleLoan->planned_end_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Aktual Mulai</dt>
                            <dd class="mt-1 text-xs font-bold text-slate-900">
                                {{ $vehicleLoan->actual_start_at?->timezone($displayTimezone)->translatedFormat('d M Y, H:i') ?: '-' }}{{ $vehicleLoan->actual_start_at ? ' WIB' : '' }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Aktual Kembali</dt>
                            <dd class="mt-1 text-xs font-bold text-slate-900">
                                {{ $vehicleLoan->actual_end_at?->timezone($displayTimezone)->translatedFormat('d M Y, H:i') ?: '-' }}{{ $vehicleLoan->actual_end_at ? ' WIB' : '' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="grid gap-5 p-5 sm:p-6 xl:grid-cols-2">
                    @include('vehicle-loans.lifecycle.partials.check-card', [
                        'check' => $checkoutCheck,
                        'title' => 'Kondisi Awal / Checkout',
                        'vehicleLoan' => $vehicleLoan,
                        'displayTimezone' => $displayTimezone,
                    ])

                    @include('vehicle-loans.lifecycle.partials.check-card', [
                        'check' => $returnCheck,
                        'title' => 'Kondisi Akhir / Return',
                        'vehicleLoan' => $vehicleLoan,
                        'displayTimezone' => $displayTimezone,
                    ])
                </div>

                <div class="border-t border-slate-200 bg-slate-50 p-5 sm:p-6">
                    @if ($isAdminWorkspace)
                        @if ($status === \App\Enums\VehicleLoanStatus::Approved)
                            @include('vehicle-loans.lifecycle.partials.condition-form', [
                                'action' => route('vehicle-loan-lifecycle.admin.checkout', $vehicleLoan),
                                'formKey' => 'checkout_'.$vehicleLoan->id,
                                'heading' => 'Pemeriksaan Kondisi Awal',
                                'description' => 'Pemeriksaan ini menjadi baseline immutable sebelum kendaraan dapat dikonfirmasi dan dipinjam.',
                                'buttonLabel' => 'Simpan Checkout dan Siapkan Kendaraan',
                            ])
                        @elseif ($status === \App\Enums\VehicleLoanStatus::ReadyForPickup)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-950">
                                Pemeriksaan awal sudah selesai. Menunggu peminjam membubuhkan tanda tangan serah terima sebelum kendaraan berubah menjadi Dipinjam.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::Borrowed)
                            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm font-semibold leading-6 text-cyan-950">
                                Kendaraan sedang digunakan. Pemeriksaan akhir baru dapat dilakukan setelah peminjam mengajukan pengembalian.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::AwaitingReturnInspection)
                            @include('vehicle-loans.lifecycle.partials.condition-form', [
                                'action' => route('vehicle-loan-lifecycle.admin.return-inspection', $vehicleLoan),
                                'formKey' => 'return_'.$vehicleLoan->id,
                                'heading' => 'Pemeriksaan Kondisi Akhir',
                                'description' => 'Sistem akan membandingkan odometer terhadap checkout dan master kendaraan. Kondisi selain Baik akan menghasilkan return_issue dan kendaraan Perlu Pemeriksaan.',
                                'buttonLabel' => 'Selesaikan Pemeriksaan Pengembalian',
                            ])
                        @elseif ($status === \App\Enums\VehicleLoanStatus::Completed)
                            <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm font-semibold leading-6 text-teal-950">
                                Pengembalian telah selesai. Odometer master sudah diperbarui dan kendaraan dikembalikan ke status operasional yang sesuai dengan reservasi berikutnya.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::ReturnIssue)
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold leading-6 text-red-950">
                                Pengembalian bermasalah. Kendaraan berstatus Perlu Pemeriksaan dan tidak dapat dipinjam sampai tindak lanjut pemeliharaan pada STEP 18.
                            </div>
                        @endif
                    @else
                        @if ($status === \App\Enums\VehicleLoanStatus::Approved)
                            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold leading-6 text-sky-950">
                                Peminjaman sudah disetujui. Administrator belum menyelesaikan pemeriksaan kondisi awal.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::ReadyForPickup)
                            <form
                                method="POST"
                                action="{{ route('vehicle-loan-lifecycle.employee.confirm-pickup', $vehicleLoan) }}"
                                class="rounded-2xl border border-emerald-200 bg-white p-4 sm:p-5"
                            >
                                @csrf
                                <p class="text-sm font-black text-slate-950">Konfirmasi Serah Terima</p>
                                <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
                                    Periksa data kondisi awal di atas. Setelah ditandatangani, actual_start_at tercatat dan kendaraan berubah menjadi Dipinjam.
                                </p>
                                <div class="mt-5">
                                    @include('inventory-requests.partials.signature-pad', [
                                        'padId' => 'vehicle_pickup_'.$vehicleLoan->id,
                                        'consentName' => 'pickup_consent',
                                        'consentText' => 'Saya telah memeriksa kondisi awal kendaraan dan menerima kendaraan sesuai data serah terima di atas.',
                                    ])
                                </div>
                                <button type="submit" class="primary-button mt-5">
                                    Tanda Tangani dan Ambil Kendaraan
                                </button>
                            </form>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::Borrowed)
                            <form
                                method="POST"
                                action="{{ route('vehicle-loan-lifecycle.employee.request-return', $vehicleLoan) }}"
                                class="rounded-2xl border border-cyan-200 bg-white p-4 sm:p-5"
                            >
                                @csrf
                                <p class="text-sm font-black text-slate-950">Ajukan Pengembalian</p>
                                <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
                                    Waktu pengajuan ini menjadi waktu aktual kendaraan dikembalikan. Kendaraan tetap berstatus Dipinjam sampai Administrator menyelesaikan pemeriksaan akhir.
                                </p>
                                <label for="return_notes_{{ $vehicleLoan->id }}" class="form-label mt-4">
                                    Catatan Pengembalian
                                </label>
                                <textarea
                                    id="return_notes_{{ $vehicleLoan->id }}"
                                    name="return_notes"
                                    rows="3"
                                    class="form-input min-h-24 py-3"
                                    placeholder="Opsional. Jelaskan keterlambatan atau kondisi yang perlu diketahui Administrator."
                                >{{ old('return_notes') }}</textarea>
                                @error('return_notes')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror

                                <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-300 bg-slate-50 p-4">
                                    <input
                                        name="return_confirmation"
                                        type="checkbox"
                                        value="1"
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-400 text-sky-700 focus:ring-sky-300"
                                        required
                                    >
                                    <span class="text-xs font-semibold leading-5 text-slate-700">
                                        Saya menyatakan kendaraan telah saya kembalikan dan siap diperiksa Administrator.
                                    </span>
                                </label>
                                @error('return_confirmation')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror

                                <button type="submit" class="primary-button mt-5">
                                    Ajukan Pengembalian
                                </button>
                            </form>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::AwaitingReturnInspection)
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-950">
                                Pengembalian sudah dicatat. Menunggu pemeriksaan kondisi akhir oleh Administrator.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::Completed)
                            <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm font-semibold leading-6 text-teal-950">
                                Peminjaman selesai dan kendaraan telah melewati pemeriksaan pengembalian.
                            </div>
                        @elseif ($status === \App\Enums\VehicleLoanStatus::ReturnIssue)
                            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold leading-6 text-red-950">
                                Pemeriksaan pengembalian menemukan masalah. Kendaraan sedang menunggu tindak lanjut Administrator.
                            </div>
                        @endif
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state panel">
                <p class="font-extrabold text-slate-700">
                    Tidak ada peminjaman pada tahap serah terima atau pengembalian.
                </p>
            </div>
        @endforelse
    </section>

    @if ($vehicleLoans->hasPages())
        <div class="mt-6">
            {{ $vehicleLoans->links() }}
        </div>
    @endif
</x-layouts.app>
