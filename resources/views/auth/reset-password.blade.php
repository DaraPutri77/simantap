<x-layouts.guest title="Reset Kata Sandi">
    <div class="mb-8">
        <span class="eyebrow">Kata sandi baru</span>

        <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950 sm:text-4xl">
            Amankan kembali akun Anda
        </h2>

        <p class="mt-3 text-sm font-medium leading-6 text-slate-500">
            Buat kata sandi baru yang kuat dan berbeda dari kata sandi yang
            pernah digunakan sebelumnya.
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
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

            <div class="field-control relative">
                <span class="field-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <rect x="3" y="5" width="18" height="14" rx="3"/>
                        <path d="m4 7 8 6 8-6"/>
                    </svg>
                </span>

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $email) }}"
                    required
                    autocomplete="username"
                    class="form-input form-input-with-icon @error('email') form-input-error @enderror"
                    placeholder="nama@bps.go.id"
                >
            </div>

            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <x-auth.password-field
            name="password"
            label="Kata sandi baru"
            autocomplete="new-password"
            placeholder="Masukkan kata sandi baru"
        />

        <x-auth.password-field
            name="password_confirmation"
            label="Konfirmasi kata sandi"
            autocomplete="new-password"
            placeholder="Ulangi kata sandi baru"
        />

        <div class="auth-note border-sky-100 bg-sky-50/70 text-sky-800">
            <span class="auth-note-icon text-sky-600 ring-sky-100">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M12 3 4 7v5c0 5 3.4 8 8 10 4.6-2 8-5 8-10V7l-8-4Z"/>
                    <path d="M9 12h6"/>
                </svg>
            </span>

            <p>
                Minimal 12 karakter dengan kombinasi huruf besar, huruf kecil,
                angka, dan simbol.
            </p>
        </div>

        <button type="submit" class="primary-button">
            <span>Simpan Kata Sandi</span>

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M5 12h14m-5-5 5 5-5 5"/>
            </svg>
        </button>
    </form>
</x-layouts.guest>
