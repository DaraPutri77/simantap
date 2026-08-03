@php
    $selectedVehicleId = (string) old(
        'vehicle_id',
        $vehicleLoan?->vehicle_id,
    );
    $plannedStart = old(
        'planned_start_at',
        $vehicleLoan?->planned_start_at
            ?->timezone($displayTimezone)
            ->format('Y-m-d\TH:i'),
    );
    $plannedEnd = old(
        'planned_end_at',
        $vehicleLoan?->planned_end_at
            ?->timezone($displayTimezone)
            ->format('Y-m-d\TH:i'),
    );
@endphp

@if ($errors->has('phone'))
    <div class="alert-danger">
        <strong>Profil belum lengkap.</strong>
        <span>{{ $errors->first('phone') }}</span>
        <a href="{{ route('profile.edit') }}" class="font-black underline">
            Lengkapi profil
        </a>
    </div>
@endif

@if ($errors->has('loan'))
    <div class="alert-danger">
        <strong>Formulir tidak dapat diproses.</strong>
        <span>{{ $errors->first('loan') }}</span>
    </div>
@endif

<section class="panel p-5 sm:p-6">
    <div class="border-b border-slate-200 pb-5">
        <p class="eyebrow">Bagian 1</p>
        <h2 class="mt-2 text-xl font-black text-slate-950">
            Kendaraan dan Jadwal
        </h2>
        <p class="mt-1 text-sm font-medium leading-6 text-slate-600">
            Jadwal menggunakan waktu WIB. Sistem akan menolak waktu yang
            beririsan dengan peminjaman aktif lainnya.
        </p>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <label for="vehicle_id" class="form-label">Kendaraan</label>
            <select
                id="vehicle_id"
                name="vehicle_id"
                class="form-input"
                required
            >
                <option value="">Pilih kendaraan</option>
                @foreach ($vehicles as $vehicle)
                    <option
                        value="{{ $vehicle->getKey() }}"
                        @selected($selectedVehicleId === (string) $vehicle->getKey())
                    >
                        {{ $vehicle->vehicle_code }} · {{ $vehicle->license_plate }} ·
                        {{ $vehicle->displayName() }} · {{ $vehicle->status->label() }}
                    </option>
                @endforeach
            </select>
            @error('vehicle_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-semibold leading-5 text-slate-500">
                Kendaraan berstatus Dipesan tetap dapat dipilih jika jadwalnya
                tidak berbenturan.
            </p>
        </div>

        <div>
            <label for="planned_start_at" class="form-label">
                Mulai Peminjaman
            </label>
            <input
                id="planned_start_at"
                name="planned_start_at"
                type="datetime-local"
                value="{{ $plannedStart }}"
                class="form-input"
                required
            >
            @error('planned_start_at')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="planned_end_at" class="form-label">
                Selesai Peminjaman
            </label>
            <input
                id="planned_end_at"
                name="planned_end_at"
                type="datetime-local"
                value="{{ $plannedEnd }}"
                class="form-input"
                required
            >
            @error('planned_end_at')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs font-semibold leading-5 text-slate-500">
                Durasi maksimal {{ config('simantap.vehicle.max_loan_days', 3) }} hari.
            </p>
        </div>
    </div>
</section>

<section class="panel p-5 sm:p-6">
    <div class="border-b border-slate-200 pb-5">
        <p class="eyebrow">Bagian 2</p>
        <h2 class="mt-2 text-xl font-black text-slate-950">
            Keperluan Perjalanan
        </h2>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="lg:col-span-2">
            <label for="purpose" class="form-label">Keperluan</label>
            <textarea
                id="purpose"
                name="purpose"
                rows="4"
                class="form-input min-h-28 py-4"
                placeholder="Jelaskan kegiatan dinas yang memerlukan kendaraan"
                required
            >{{ old('purpose', $vehicleLoan?->purpose) }}</textarea>
            @error('purpose')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="destination" class="form-label">
                Tujuan Perjalanan
            </label>
            <input
                id="destination"
                name="destination"
                type="text"
                value="{{ old('destination', $vehicleLoan?->destination) }}"
                class="form-input"
                placeholder="Contoh: Kantor Kecamatan Sukolilo"
                required
            >
            @error('destination')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="reason" class="form-label">
                Keterangan Pendukung
            </label>
            <input
                id="reason"
                name="reason"
                type="text"
                value="{{ old('reason', $vehicleLoan?->reason) }}"
                class="form-input"
                placeholder="Opsional"
            >
            @error('reason')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="lg:col-span-2">
            <label for="notes" class="form-label">Catatan Peminjam</label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                class="form-input min-h-24 py-4"
                placeholder="Catatan tambahan bila diperlukan"
            >{{ old('notes', $vehicleLoan?->notes) }}</textarea>
            @error('notes')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>

<section class="rounded-3xl border border-sky-200 bg-sky-50 p-5 sm:p-6">
    <h2 class="font-black text-sky-950">Sebelum Disimpan</h2>
    <ul class="mt-3 space-y-2 text-sm font-semibold leading-6 text-sky-900">
        <li>Nomor telepon diambil dari profil sebagai kontak peminjaman.</li>
        <li>Draft belum mereservasi kendaraan dan masih dapat diubah.</li>
        <li>Reservasi jadwal diperiksa ulang ketika formulir diajukan dan disetujui.</li>
    </ul>
</section>
