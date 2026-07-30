@php
    $managedEmployee = $employee ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div>
        <label
            for="employee_number"
            class="form-label"
        >
            NIP / Nomor Pegawai
        </label>

        <input
            id="employee_number"
            name="employee_number"
            type="text"
            value="{{ old(
                'employee_number',
                $managedEmployee?->employee_number,
            ) }}"
            class="form-input @error('employee_number') form-input-error @enderror"
            maxlength="50"
            autocomplete="off"
            required
            autofocus
        >

        @error('employee_number')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="name"
            class="form-label"
        >
            Nama Lengkap
        </label>

        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $managedEmployee?->name) }}"
            class="form-input @error('name') form-input-error @enderror"
            maxlength="255"
            autocomplete="name"
            required
        >

        @error('name')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="email"
            class="form-label"
        >
            Email Pegawai
        </label>

        <input
            id="email"
            name="email"
            type="email"
            value="{{ old('email', $managedEmployee?->email) }}"
            class="form-input @error('email') form-input-error @enderror"
            maxlength="255"
            autocomplete="email"
            required
        >

        @error('email')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror

        <p class="mt-2 text-xs font-medium leading-5 text-slate-400">
            Digunakan untuk login, aktivasi akun, dan pemulihan kata sandi.
        </p>
    </div>

    <div>
        <label
            for="phone"
            class="form-label"
        >
            Nomor Telepon
            <span class="font-medium text-slate-400">
                (opsional)
            </span>
        </label>

        <input
            id="phone"
            name="phone"
            type="tel"
            value="{{ old('phone', $managedEmployee?->phone) }}"
            class="form-input @error('phone') form-input-error @enderror"
            maxlength="30"
            autocomplete="tel"
            inputmode="tel"
            placeholder="Contoh: 081234567890"
        >

        @error('phone')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="work_unit"
            class="form-label"
        >
            Unit Kerja
        </label>

        <input
            id="work_unit"
            name="work_unit"
            type="text"
            value="{{ old(
                'work_unit',
                $managedEmployee?->work_unit,
            ) }}"
            class="form-input @error('work_unit') form-input-error @enderror"
            maxlength="255"
            autocomplete="organization"
            required
        >

        @error('work_unit')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="position"
            class="form-label"
        >
            Jabatan
        </label>

        <input
            id="position"
            name="position"
            type="text"
            value="{{ old(
                'position',
                $managedEmployee?->position,
            ) }}"
            class="form-input @error('position') form-input-error @enderror"
            maxlength="255"
            autocomplete="organization-title"
            required
        >

        @error('position')
            <p class="form-error">
                {{ $message }}
            </p>
        @enderror
    </div>
</div>
