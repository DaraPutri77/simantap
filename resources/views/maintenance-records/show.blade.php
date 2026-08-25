<x-layouts.app
    :title="$maintenanceRecord->maintenance_number"
    header="Detail Pemeliharaan"
    eyebrow="Aset dan Kendaraan"
>
    @php
        $status = $maintenanceRecord->status;
        $showApproveForm = $status === \App\Enums\MaintenanceStatus::Reported;
        $showStartForm = in_array($status, [
            \App\Enums\MaintenanceStatus::Approved,
            \App\Enums\MaintenanceStatus::FurtherActionRequired,
        ], true);
        $showCompletionForm = $status === \App\Enums\MaintenanceStatus::InProgress;
        $showCancellationForm = in_array($status, [
            \App\Enums\MaintenanceStatus::Reported,
            \App\Enums\MaintenanceStatus::Approved,
            \App\Enums\MaintenanceStatus::InProgress,
            \App\Enums\MaintenanceStatus::FurtherActionRequired,
        ], true);
        $beforeAttachments = $maintenanceRecord->attachments->where('file_category', \App\Enums\AttachmentCategory::MaintenanceBefore);
        $afterAttachments = $maintenanceRecord->attachments->where('file_category', \App\Enums\AttachmentCategory::MaintenanceAfter);
        $otherAttachments = $maintenanceRecord->attachments->reject(fn ($attachment) => in_array($attachment->file_category, [
            \App\Enums\AttachmentCategory::MaintenanceBefore,
            \App\Enums\AttachmentCategory::MaintenanceAfter,
        ], true));
    @endphp

    <section class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Tiket Pemeliharaan</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                    {{ $maintenanceRecord->maintenance_number }}
                </h1>
                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-800 ring-1 ring-inset ring-slate-300">
                    {{ $status->label() }}
                </span>
            </div>
            <p class="mt-2 text-sm font-bold text-slate-700">{{ $maintenanceRecord->subjectSnapshot() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">Daftar Pemeliharaan</a>
            @if ($maintenanceRecord->sourceVehicleLoan)
                <a href="{{ route('vehicle-loans.show', $maintenanceRecord->sourceVehicleLoan) }}" class="secondary-button sm:w-auto">
                    Peminjaman Sumber
                </a>
            @endif
        </div>
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

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <article class="panel p-5 sm:p-6 lg:col-span-2">
            <h2 class="text-lg font-black text-slate-950">Informasi Pemeliharaan</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-black text-slate-500">Jenis</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->maintenance_type }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Tanggal Laporan</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->reported_date->translatedFormat('d M Y') }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Pelapor</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->reporter?->name ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Penanggung Jawab</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->handler?->name ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Penyedia Jasa</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->service_provider ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Biaya</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->cost !== null ? 'Rp '.number_format((float) $maintenanceRecord->cost, 0, ',', '.') : '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Mulai</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->start_date?->translatedFormat('d M Y') ?: '-' }}</dd></div>
                <div><dt class="text-xs font-black text-slate-500">Selesai</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $maintenanceRecord->completion_date?->translatedFormat('d M Y') ?: '-' }}</dd></div>
            </dl>

            <div class="mt-6 grid gap-4">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">Keluhan</p>
                    <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $maintenanceRecord->complaint }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">Kondisi Awal</p>
                    <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $maintenanceRecord->initial_condition }}</p>
                </div>
                @if ($maintenanceRecord->result)
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">Hasil Pekerjaan</p>
                        <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $maintenanceRecord->result }}</p>
                    </div>
                @endif
                @if ($maintenanceRecord->final_condition)
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase tracking-[.12em] text-slate-500">Kondisi Akhir</p>
                        <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-800">{{ $maintenanceRecord->final_condition }}</p>
                    </div>
                @endif
            </div>
        </article>

        <article class="panel p-5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">{{ $maintenanceRecord->subjectType()->label() }}</h2>
            @if ($maintenanceRecord->vehicle)
                <p class="mt-3 text-sm font-black text-slate-900">{{ $maintenanceRecord->vehicle->license_plate }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $maintenanceRecord->vehicle->displayName() }}</p>
                <dl class="mt-5 space-y-3">
                    <div><dt class="text-xs font-black text-slate-500">Status Sebelum</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->vehicle_status_before?->label() ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Status Sekarang</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->vehicle->status->label() }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Odometer</dt><dd class="mt-1 text-sm font-semibold">{{ number_format((float) $maintenanceRecord->vehicle->current_odometer, 1, ',', '.') }} km</dd></div>
                </dl>
                @if ($maintenanceRecord->sourceVehicleLoan)
                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4">
                        <p class="text-xs font-black text-red-800">Masalah Pengembalian</p>
                        <p class="mt-1 text-sm font-black text-red-950">{{ $maintenanceRecord->sourceVehicleLoan->loan_number }}</p>
                        <p class="mt-1 text-xs font-semibold text-red-800">{{ $maintenanceRecord->sourceVehicleLoan->borrower?->name ?: '-' }}</p>
                    </div>
                @endif
            @elseif ($maintenanceRecord->operationalAsset)
                <p class="mt-3 text-sm font-black text-slate-900">{{ $maintenanceRecord->operationalAsset->asset_code }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">{{ $maintenanceRecord->operationalAsset->displayName() }}</p>
                <dl class="mt-5 space-y-3">
                    <div><dt class="text-xs font-black text-slate-500">Referensi BMN</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->operationalAsset->administrativeCode() }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Status Sebelum</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->operational_asset_status_before?->label() ?: '-' }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Status Sekarang</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->operationalAsset->status->label() }}</dd></div>
                    <div><dt class="text-xs font-black text-slate-500">Lokasi</dt><dd class="mt-1 text-sm font-semibold">{{ $maintenanceRecord->operationalAsset->location ?: '-' }}</dd></div>
                </dl>
                <a href="{{ route('operational-assets.show', $maintenanceRecord->operationalAsset) }}" class="secondary-button mt-5">Detail Aset</a>
            @else
                <p class="mt-3 text-sm font-semibold text-slate-600">{{ $maintenanceRecord->subjectSnapshot() }}</p>
            @endif
        </article>
    </section>

    <section class="panel mt-6 p-5 sm:p-6">
        <h2 class="text-lg font-black text-slate-950">Bukti Digital</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($maintenanceRecord->attachments as $attachment)
                <a
                    href="{{ route('maintenance-records.evidence', [$maintenanceRecord, $attachment]) }}"
                    target="_blank"
                    rel="noopener"
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-sky-300"
                >
                    <p class="text-xs font-black text-slate-900">{{ $attachment->file_category->label() }}</p>
                    <p class="mt-1 break-all text-xs font-semibold text-slate-600">{{ $attachment->original_name }}</p>
                    <p class="mt-2 text-[10px] font-bold uppercase tracking-[.12em] text-slate-500">SHA256 tersimpan</p>
                </a>
            @empty
                <p class="text-sm font-semibold text-slate-500">Belum ada bukti digital.</p>
            @endforelse
        </div>
    </section>

    <section class="panel mt-6 p-5 sm:p-6">
        <h2 class="text-lg font-black text-slate-950">Timeline Status</h2>
        <div class="mt-5 space-y-4">
            @foreach ($maintenanceRecord->statusHistories as $history)
                <div class="border-l-2 border-slate-200 pl-4">
                    <p class="text-sm font-black text-slate-900">{{ $history->new_status->label() }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        {{ $history->changed_at->timezone($displayTimezone)->translatedFormat('d M Y, H:i') }} WIB · {{ $history->changer?->name ?: 'Sistem' }}
                    </p>
                    @if ($history->notes)
                        <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-700">{{ $history->notes }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if ($showApproveForm)
        @can('approve', $maintenanceRecord)
            <section class="panel mt-6 p-5 sm:p-6">
                <h2 class="text-lg font-black text-slate-950">Setujui Pemeliharaan</h2>
                <form method="POST" action="{{ route('maintenance-records.approve', $maintenanceRecord) }}" class="mt-4">
                    @csrf
                    <label for="approval_notes" class="form-label">Catatan Persetujuan</label>
                    <textarea id="approval_notes" name="approval_notes" rows="3" class="form-input min-h-24 py-3">{{ old('approval_notes') }}</textarea>
                    <button type="submit" class="primary-button mt-4 sm:w-auto">Setujui</button>
                </form>
            </section>
        @endcan
    @endif

    @if ($showStartForm)
        @can('start', $maintenanceRecord)
            <section class="panel mt-6 p-5 sm:p-6">
                <h2 class="text-lg font-black text-slate-950">Mulai Pengerjaan</h2>
                <form method="POST" action="{{ route('maintenance-records.start', $maintenanceRecord) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', now($displayTimezone)->toDateString()) }}" class="form-input" required>
                    </div>
                    <div>
                        <label for="service_provider" class="form-label">Penyedia Jasa</label>
                        <input id="service_provider" name="service_provider" type="text" value="{{ old('service_provider', $maintenanceRecord->service_provider) }}" class="form-input" maxlength="255">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="primary-button sm:w-auto">Mulai Pemeliharaan</button>
                    </div>
                </form>
            </section>
        @endcan
    @endif

    @if ($showCompletionForm)
        @can('complete', $maintenanceRecord)
            <section class="panel mt-6 p-5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Catat Hasil Pemeliharaan</h2>
            <form method="POST" action="{{ route('maintenance-records.complete', $maintenanceRecord) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 lg:grid-cols-2">
                @csrf
                <div>
                    <label for="outcome_status" class="form-label">Hasil Status</label>
                    <select id="outcome_status" name="outcome_status" class="form-input" required>
                        <option value="">Pilih hasil</option>
                        @foreach ($completionStatuses as $completionStatus)
                            <option value="{{ $completionStatus->value }}" @selected(old('outcome_status') === $completionStatus->value)>
                                {{ $completionStatus->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="completion_date" class="form-label">Tanggal Selesai / Evaluasi</label>
                    <input id="completion_date" name="completion_date" type="date" value="{{ old('completion_date', now($displayTimezone)->toDateString()) }}" class="form-input" required>
                </div>
                <div>
                    <label for="cost" class="form-label">Biaya</label>
                    <input id="cost" name="cost" type="number" min="0" step="0.01" value="{{ old('cost') }}" class="form-input">
                </div>
                <div>
                    <label for="photo_after" class="form-label">Foto Sesudah Pemeliharaan</label>
                    <input id="photo_after" name="photo_after" type="file" accept="image/jpeg,image/png,image/webp" class="form-input py-2" required>
                </div>
                <div class="lg:col-span-2">
                    <label for="result" class="form-label">Hasil Pekerjaan</label>
                    <textarea id="result" name="result" rows="4" class="form-input min-h-28 py-3" required>{{ old('result') }}</textarea>
                </div>
                <div class="lg:col-span-2">
                    <label for="final_condition" class="form-label">Kondisi Akhir</label>
                    <textarea id="final_condition" name="final_condition" rows="4" class="form-input min-h-28 py-3" required>{{ old('final_condition') }}</textarea>
                </div>
                <div class="lg:col-span-2">
                    <label for="receipt" class="form-label">Nota / Bukti Pembayaran</label>
                    <input id="receipt" name="receipt" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="form-input py-2">
                </div>
                <div class="lg:col-span-2">
                    <button type="submit" class="primary-button sm:w-auto">Simpan Hasil</button>
                </div>
            </form>
            </section>
        @endcan
    @endif

    @if ($showCancellationForm)
        @can('cancel', $maintenanceRecord)
            <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
                <h2 class="text-lg font-black text-red-950">Batalkan Pemeliharaan</h2>
                <form method="POST" action="{{ route('maintenance-records.cancel', $maintenanceRecord) }}" class="mt-4">
                    @csrf
                    <label for="cancellation_reason" class="form-label text-red-900">Alasan Pembatalan</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" class="form-input min-h-24 py-3" required>{{ old('cancellation_reason') }}</textarea>
                    <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white hover:bg-red-800">
                        Batalkan Pemeliharaan
                    </button>
                </form>
            </section>
        @endcan
    @endif
</x-layouts.app>
