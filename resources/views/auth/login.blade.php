<x-layouts.guest title="Masuk">
    <div class="mb-8">
        <span class="eyebrow">Selamat datang</span>

        <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950 sm:text-4xl">
            Masuk ke SIMANTAP
        </h2>

        <p class="mt-3 max-w-md text-sm font-medium leading-6 text-slate-500">
            Gunakan email atau NIP akun internal yang telah diberikan oleh
            administrator unit kerja Anda.
        </p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="login" class="form-label">
                Email atau NIP
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
                        <path d="M20 21a8 8 0 0 0-16 0"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>

                <input
                    id="login"
                    name="login"
                    type="text"
                    value="{{ old('login') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="form-input form-input-with-icon @error('login') form-input-error @enderror"
                    placeholder="nama@bps.go.id atau NIP"
                >
            </div>

            @error('login')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-2.5 flex items-center justify-between gap-4">
                <label for="password" class="block text-[13px] font-extrabold text-slate-700">
                    Kata sandi
                </label>

                <a
                    href="{{ route('password.request') }}"
                    class="rounded-lg text-xs font-extrabold text-sky-700 transition hover:text-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-sky-100"
                >
                    Lupa kata sandi?
                </a>
            </div>

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
                        <rect x="4" y="10" width="16" height="11" rx="3"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                    </svg>
                </span>

                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="form-input form-input-with-icon pr-14 @error('password') form-input-error @enderror"
                    placeholder="Masukkan kata sandi"
                >

                <button
                    type="button"
                    class="password-toggle"
                    data-target="password"
                    data-show-label="Tampilkan kata sandi"
                    data-hide-label="Sembunyikan kata sandi"
                    aria-label="Tampilkan kata sandi"
                >
                    <svg
                        data-password-show
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                        <circle cx="12" cy="12" r="2.5"/>
                    </svg>

                    <svg
                        data-password-hide
                        viewBox="0 0 24 24"
                        class="hidden"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="m3 3 18 18M10.6 6.2A10.6 10.6 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-2.1 2.8M6.6 6.6A16.5 16.5 0 0 0 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-1"/>
                    </svg>
                </button>
            </div>

            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="primary-button">
            <span>Masuk ke Sistem</span>

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

    <div class="auth-note mt-7">
        <span class="auth-note-icon">
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                <path d="M9 12h6"/>
            </svg>
        </span>

        <p>
            <strong class="block font-extrabold text-slate-700">
                Akses khusus pengguna internal
            </strong>

            Belum memiliki akun atau akun ditangguhkan? Hubungi administrator
            SIMANTAP pada unit kerja Anda.
        </p>
    </div>
</x-layouts.guest>
