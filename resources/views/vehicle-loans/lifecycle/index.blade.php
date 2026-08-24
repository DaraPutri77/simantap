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
                $pickupOpensAt = $vehicleLoan->planned_start_at;
                $pickupIsOpen = $pickupOpensAt === null
                    || now()->gte($pickupOpensAt);
                $pickedUpBeforeSchedule = $vehicleLoan->actual_start_at !== null
                    && $vehicleLoan->planned_start_at !== null
                    && $vehicleLoan->actual_start_at->lt(
                        $vehicleLoan->planned_start_at,
                    );
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
                            @if ($checkoutCheck)
                                <a
                                    href="{{ route('vehicle-loan-lifecycle.pdf', $vehicleLoan) }}"
                                    class="secondary-button sm:w-auto"
                                >
                                    PDF Serah Terima
                                </a>
                            @else
                                <span
                                    class="secondary-button cursor-not-allowed opacity-60 sm:w-auto"
                                    aria-disabled="true"
                                    title="PDF tersedia setelah pemeriksaan kondisi awal disimpan"
                                >
                                    PDF setelah Checkout
                                </span>
                            @endif
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

                    @if ($pickedUpBeforeSchedule)
                        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-950">
                            Catatan data historis: pengambilan tercatat sebelum jadwal mulai. Riwayat tidak diubah; alur baru telah mencegah pengambilan sebelum waktu yang direncanakan.
                        </div>
                    @endif
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
                                'description' => 'Pemeriksaan dan tanda tangan ini menjadi baseline immutable sebelum kendaraan dapat dikonfirmasi dan dipinjam.',
                                'buttonLabel' => 'Tanda Tangani Checkout dan Siapkan Kendaraan',
                                'signaturePadId' => 'vehicle_checkout_officer_'.$vehicleLoan->id,
                                'signatureConsentText' => 'Saya telah melakukan pemeriksaan kondisi awal. Setiap foto sesuai label, merupakan foto baru pada pemeriksaan ini, dan seluruh data dapat dipertanggungjawabkan.',
                            ])
                        @elseif ($status === \App\Enums\VehicleLoanStatus::ReadyForPickup)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-950">
                                Pemeriksaan awal sudah selesai. Menunggu peminjam mengunggah foto memegang kunci dan membubuhkan tanda tangan serah terima sebelum kendaraan berubah menjadi Dipinjam.
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
                                'description' => 'Sistem akan membandingkan odometer terhadap checkout dan master kendaraan. Tanda tangan pemeriksa mengesahkan hasil pemeriksaan akhir.',
                                'buttonLabel' => 'Tanda Tangani dan Selesaikan Pemeriksaan',
                                'signaturePadId' => 'vehicle_return_officer_'.$vehicleLoan->id,
                                'signatureConsentText' => 'Saya telah melakukan pemeriksaan kondisi akhir. Setiap foto sesuai label, merupakan foto baru pada pemeriksaan ini, dan seluruh data dapat dipertanggungjawabkan.',
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
                            @if (! $pickupIsOpen)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-950">
                                    Pemeriksaan awal sudah selesai. Kendaraan baru dapat diambil mulai
                                    <strong>{{ $pickupOpensAt->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB</strong>
                                    sesuai jadwal peminjaman.
                                </div>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('vehicle-loan-lifecycle.employee.confirm-pickup', $vehicleLoan) }}"
                                    enctype="multipart/form-data"
                                    class="rounded-2xl border border-emerald-200 bg-white p-4 sm:p-5"
                                    data-signature-form
                                >
                                    @csrf
                                    <p class="text-sm font-black text-slate-950">Konfirmasi Serah Terima</p>
                                    <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
                                        Periksa data kondisi awal di atas. Foto peminjam memegang kunci dan tanda tangan wajib dilengkapi sebelum kendaraan berubah menjadi Dipinjam.
                                    </p>
                                    <div class="mt-5">
                                        <label for="photo_borrower_with_key_{{ $vehicleLoan->id }}" class="form-label">
                                            Foto Peminjam Memegang Kunci Kendaraan
                                        </label>
                                        <input
                                            id="photo_borrower_with_key_{{ $vehicleLoan->id }}"
                                            name="photo_borrower_with_key"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            capture="environment"
                                            class="form-input py-2"
                                            data-evidence-preview-input
                                            required
                                        >
                                        <div class="mt-2 hidden rounded-xl border border-slate-200 bg-slate-50 p-2" data-evidence-preview>
                                            <img
                                                src=""
                                                alt="Pratinjau Foto Peminjam Memegang Kunci Kendaraan"
                                                class="h-48 w-full rounded-lg object-contain"
                                                data-evidence-preview-image
                                            >
                                            <p class="mt-2 truncate text-[10px] font-bold text-slate-600" data-evidence-preview-name></p>
                                        </div>
                                        <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">
                                            Unggah foto peminjam saat memegang kunci kendaraan yang diterima.
                                        </p>
                                        @error('photo_borrower_with_key')
                                            <p class="form-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="mt-5">
                                        @include('inventory-requests.partials.signature-pad', [
                                            'padId' => 'vehicle_pickup_'.$vehicleLoan->id,
                                            'consentName' => 'pickup_consent',
                                            'consentText' => 'Saya telah memeriksa kondisi awal, menerima kendaraan, dan memastikan foto benar menunjukkan saya memegang kunci kendaraan yang diterima.',
                                        ])
                                    </div>
                                    <button type="submit" class="primary-button mt-5">
                                        Tanda Tangani dan Ambil Kendaraan
                                    </button>
                                </form>
                            @endif
                        @elseif ($status === \App\Enums\VehicleLoanStatus::Borrowed)
                            <form
                                method="POST"
                                action="{{ route('vehicle-loan-lifecycle.employee.request-return', $vehicleLoan) }}"
                                class="rounded-2xl border border-cyan-200 bg-white p-4 sm:p-5"
                                data-signature-form
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

                                <div class="mt-5">
                                    @include('inventory-requests.partials.signature-pad', [
                                        'padId' => 'vehicle_return_borrower_'.$vehicleLoan->id,
                                        'consentName' => 'return_confirmation',
                                        'consentText' => 'Saya menyatakan kendaraan telah saya kembalikan, data pengembalian ini benar, dan kendaraan siap diperiksa Administrator.',
                                    ])
                                </div>

                                <button type="submit" class="primary-button mt-5">
                                    Tanda Tangani dan Ajukan Pengembalian
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

    <script>
        document.querySelectorAll('[data-evidence-preview-input]').forEach((input) => {
            const wrapper = input.parentElement?.querySelector('[data-evidence-preview]');
            const image = wrapper?.querySelector('[data-evidence-preview-image]');
            const name = wrapper?.querySelector('[data-evidence-preview-name]');
            let previewUrl = null;

            if (!wrapper || !image || !name) {
                return;
            }

            input.addEventListener('change', () => {
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }

                const file = input.files?.[0];

                if (!file) {
                    image.removeAttribute('src');
                    name.textContent = '';
                    wrapper.classList.add('hidden');

                    return;
                }

                previewUrl = URL.createObjectURL(file);
                image.src = previewUrl;
                name.textContent = file.name;
                wrapper.classList.remove('hidden');
            });
        });
    </script>
</x-layouts.app>
