<x-layouts.app
    title="Tambah Pemeliharaan"
    header="Tambah Pemeliharaan"
    eyebrow="Aset dan Kendaraan"
>
    @php
        $selectedVehiclePublicId = old(
            'vehicle_public_id',
            $selectedLoan?->vehicle?->public_id,
        );
        $selectedLoanPublicId = old(
            'source_vehicle_loan_public_id',
            $selectedLoan?->public_id,
        );
        $selectedAssetPublicId = old(
            'operational_asset_public_id',
            $selectedAsset?->public_id,
        );
        $selectedSubjectTypeValue = old(
            'subject_type',
            $selectedSubjectType->value,
        );
        $selectedReturnCheck = $selectedLoan?->returnCheck();
    @endphp

    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Pelaporan Pemeliharaan</p>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                Buat Tiket Pemeliharaan
            </h1>
            <p class="mt-2 max-w-3xl text-sm font-medium leading-6 text-slate-600">
                Pilih satu subjek pemeliharaan: kendaraan atau aset perangkat PC, laptop, dan printer. Gunakan sumber masalah pengembalian bila tersedia agar histori kendaraan tetap terhubung.
            </p>
        </div>
        <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">
            Kembali
        </a>
    </section>

    @if ($errors->any())
        <div class="alert-danger mt-6">
            <strong>Data belum dapat disimpan.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($returnIssues->isNotEmpty())
        <section class="panel mt-6 p-5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Masalah Pengembalian yang Belum Ditindaklanjuti</h2>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach ($returnIssues as $loan)
                    <a
                        href="{{ route('maintenance-records.create-from-loan', $loan) }}"
                        class="rounded-2xl border border-red-200 bg-red-50 p-4 transition hover:border-red-400"
                    >
                        <p class="text-sm font-black text-red-950">{{ $loan->loan_number }}</p>
                        <p class="mt-1 text-xs font-bold text-red-800">
                            {{ $loan->vehicle?->license_plate }} · {{ $loan->vehicle?->displayName() }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-red-700">
                            Peminjam: {{ $loan->borrower?->name ?: '-' }}
                        </p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($selectedLoan)
        <section class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-xs font-black uppercase tracking-[.12em] text-red-700">Sumber Masalah Pengembalian</p>
            <p class="mt-2 text-lg font-black text-red-950">{{ $selectedLoan->loan_number }}</p>
            <p class="mt-1 text-sm font-semibold text-red-800">
                {{ $selectedLoan->vehicle_snapshot }} · {{ $selectedLoan->borrower_name_snapshot }}
            </p>
            @if ($selectedReturnCheck?->damage_notes)
                <p class="mt-3 text-sm font-semibold leading-6 text-red-900">
                    {{ $selectedReturnCheck->damage_notes }}
                </p>
            @endif
        </section>
    @endif

    <form
        method="POST"
        action="{{ route('maintenance-records.store') }}"
        enctype="multipart/form-data"
        class="panel mt-6 p-5 sm:p-6"
    >
        @csrf

        <input
            type="hidden"
            name="source_vehicle_loan_public_id"
            value="{{ $selectedLoanPublicId }}"
        >

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label for="subject_type" class="form-label">Subjek Pemeliharaan</label>
                @if ($selectedLoan)
                    <input type="hidden" name="subject_type" value="{{ \App\Enums\MaintenanceSubjectType::Vehicle->value }}">
                    <div class="flex min-h-13 items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-black text-slate-700">Kendaraan dari masalah pengembalian</div>
                @else
                    <select id="subject_type" name="subject_type" class="form-input" required>
                        @foreach (\App\Enums\MaintenanceSubjectType::cases() as $subjectTypeOption)
                            <option value="{{ $subjectTypeOption->value }}" @selected($selectedSubjectTypeValue === $subjectTypeOption->value)>{{ $subjectTypeOption->label() }}</option>
                        @endforeach
                    </select>
                @endif
                @error('subject_type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div id="vehicle-subject-field" data-subject-field="vehicle">
                <label for="vehicle_public_id" class="form-label">Kendaraan</label>
                <select
                    id="vehicle_public_id"
                    name="vehicle_public_id"
                    class="form-input"
                    required
                    @disabled($selectedLoan)
                >
                    <option value="">Pilih kendaraan</option>
                    @foreach ($vehicles as $vehicle)
                        <option
                            value="{{ $vehicle->public_id }}"
                            @selected($selectedVehiclePublicId === $vehicle->public_id)
                        >
                            {{ $vehicle->vehicle_code }} · {{ $vehicle->license_plate }} · {{ $vehicle->displayName() }}
                        </option>
                    @endforeach
                </select>
                @if ($selectedLoan)
                    <input type="hidden" name="vehicle_public_id" value="{{ $selectedVehiclePublicId }}">
                @endif
                @error('vehicle_public_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            @unless ($selectedLoan)
                <div id="operational-asset-subject-field" data-subject-field="operational_asset">
                    <label for="operational_asset_public_id" class="form-label">Aset Perangkat</label>
                    <select id="operational_asset_public_id" name="operational_asset_public_id" class="form-input">
                        <option value="">Pilih PC, laptop, atau printer</option>
                        @foreach ($operationalAssets as $operationalAsset)
                            <option value="{{ $operationalAsset->public_id }}" @selected($selectedAssetPublicId === $operationalAsset->public_id)>
                                {{ $operationalAsset->asset_code }} · {{ $operationalAsset->displayName() }} · {{ $operationalAsset->location ?: 'Lokasi belum diisi' }}
                            </option>
                        @endforeach
                    </select>
                    @error('operational_asset_public_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            @endunless

            <div>
                <label for="reported_date" class="form-label">Tanggal Laporan</label>
                <input
                    id="reported_date"
                    name="reported_date"
                    type="date"
                    value="{{ old('reported_date', now($displayTimezone)->toDateString()) }}"
                    class="form-input"
                    required
                >
                @error('reported_date')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="maintenance_type" class="form-label">Jenis Pemeliharaan</label>
                <input
                    id="maintenance_type"
                    name="maintenance_type"
                    type="text"
                    value="{{ old('maintenance_type', $selectedLoan ? 'Perbaikan akibat masalah pengembalian' : '') }}"
                    class="form-input"
                    maxlength="100"
                    required
                >
                @error('maintenance_type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="complaint" class="form-label">Jenis / Uraian</label>
                <textarea
                    id="complaint"
                    name="complaint"
                    rows="4"
                    class="form-input min-h-28 py-3"
                    required
                >{{ old('complaint', $selectedReturnCheck?->damage_notes) }}</textarea>
                @error('complaint')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="initial_condition" class="form-label">Pelaksana / Penyedia</label>
                <textarea
                    id="initial_condition"
                    name="initial_condition"
                    rows="4"
                    class="form-input min-h-28 py-3"
                    required
                >{{ old('initial_condition', $selectedReturnCheck ? 'Bodi: '.$selectedReturnCheck->body_condition."\nMesin: ".$selectedReturnCheck->engine_condition."\nBan: ".$selectedReturnCheck->tire_condition."\nKelengkapan: ".$selectedReturnCheck->equipment_condition : '') }}</textarea>
                @error('initial_condition')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="cost" class="form-label">Biaya (Rp) <span class="font-medium text-slate-500">(Opsional)</span></label>
                <input 
                    type="number" 
                    id="cost" 
                    name="cost" 
                    class="form-input" 
                    value="{{ old('cost') }}" 
                    step="0.01" 
                    min="0"
                    placeholder="Contoh: 150000"
                >
                @error('cost')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="supporting_document" class="form-label">Dokumen Pendukung</label>
                <input
                    id="supporting_document"
                    name="supporting_document"
                    type="file"
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    class="form-input py-2"
                >
                <p class="mt-1 text-xs font-semibold text-slate-500">Opsional. PDF atau gambar, maksimal 5 MB.</p>
                @error('supporting_document')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('maintenance-records.index') }}" class="secondary-button sm:w-auto">Batal</a>
            <button type="submit" class="primary-button sm:w-auto">Simpan Laporan</button>
        </div>
    </form>

    @unless ($selectedLoan)
        <script>
            (() => {
                const type = document.getElementById('subject_type');
                const fields = document.querySelectorAll('[data-subject-field]');

                const synchronize = () => {
                    fields.forEach((field) => {
                        const active = field.dataset.subjectField === type.value;
                        field.hidden = !active;
                        field.querySelectorAll('select, input').forEach((control) => {
                            control.disabled = !active;
                            if (control.tagName === 'SELECT') {
                                control.required = active;
                            }
                        });
                    });
                };

                type.addEventListener('change', synchronize);
                synchronize();
            })();
        </script>
    @endunless
</x-layouts.app>