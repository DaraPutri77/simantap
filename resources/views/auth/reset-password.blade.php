<x-layouts.guest title="Reset Kata Sandi">
    <div class="mb-8">
        <h2 class="text-3xl font-black tracking-tight text-slate-950">
            Buat kata sandi baru
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Gunakan minimal 12 karakter dengan huruf besar, huruf kecil,
            angka, dan simbol.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.store') }}"
        class="space-y-5"
    >
        @csrf

        <input
            type="hidden"
            name="token"
            value="{{ old('token', $token) }}"
        >

        <div>
            <label for="email" class="form-label">
                Alamat email
            </label>

            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="username"
                class="form-input @error('email') form-input-error @enderror"
            >

            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">
                Kata sandi baru
            </label>

            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="form-input pr-12 @error('password') form-input-error @enderror"
                >

                <button
                    type="button"
                    class="password-toggle absolute inset-y-0 right-0 grid w-12 place-items-center text-xs font-bold text-slate-500"
                    data-target="password"
                    aria-label="Tampilkan kata sandi"
                >
                    Lihat
                </button>
            </div>

            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label
                for="password_confirmation"
                class="form-label"
            >
                Konfirmasi kata sandi
            </label>

            <div class="relative">
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="form-input pr-12"
                >

                <button
                    type="button"
                    class="password-toggle absolute inset-y-0 right-0 grid w-12 place-items-center text-xs font-bold text-slate-500"
                    data-target="password_confirmation"
                    aria-label="Tampilkan konfirmasi kata sandi"
                >
                    Lihat
                </button>
            </div>
        </div>

        <button type="submit" class="primary-button">
            Simpan Kata Sandi
            <span aria-hidden="true">→</span>
        </button>
    </form>
</x-layouts.guest>