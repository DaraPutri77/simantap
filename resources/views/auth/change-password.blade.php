<x-layouts.guest title="Ubah Kata Sandi">
    <div class="mb-8">
        <span class="eyebrow">
            Keamanan akun
        </span>

        <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950 sm:text-4xl">
            {{ auth()->user()->must_change_password
                ? 'Ganti kata sandi sementara'
                : 'Perbarui kata sandi' }}
        </h2>

        <p class="mt-3 text-sm font-medium leading-6 text-slate-500">
            {{ auth()->user()->must_change_password
                ? 'Satu langkah lagi sebelum Anda menggunakan seluruh layanan SIMANTAP.'
                : 'Gunakan kata sandi kuat untuk menjaga keamanan akses SIMANTAP Anda.' }}
        </p>
    </div>

    @if (auth()->user()->must_change_password)
        <div class="auth-note mb-6 border-amber-100 bg-amber-50/80 text-amber-800">
            <span class="auth-note-icon text-amber-600 ring-amber-100">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                </svg>
            </span>

            <p>
                Kata sandi sementara wajib diganti agar dashboard dapat diakses.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <x-auth.password-field
            name="current_password"
            label="Kata sandi saat ini"
            autocomplete="current-password"
            placeholder="Masukkan kata sandi saat ini"
        />

        <x-auth.password-field
            name="password"
            label="Kata sandi baru"
            autocomplete="new-password"
            placeholder="Masukkan kata sandi baru"
        />

        <x-auth.password-field
            name="password_confirmation"
            label="Konfirmasi kata sandi baru"
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
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </span>

            <p>
                Minimal 12 karakter dengan kombinasi huruf besar, huruf kecil,
                angka, dan simbol.
            </p>
        </div>

        <button type="submit" class="primary-button">
            <span>Simpan dan Lanjutkan</span>

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

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf

        <button type="submit" class="secondary-button">
            Keluar dari akun
        </button>
    </form>
</x-layouts.guest>
