<x-layouts.guest title="Aktivasi Akun">
    @if ($user !== null)
        <div class="mb-8">
            <span class="eyebrow">
                Aktivasi akun
            </span>

            <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950 sm:text-4xl">
                Selamat datang, {{ $user->name }}!
            </h2>

            <p class="mt-3 text-sm font-medium leading-6 text-slate-500">
                Buat kata sandi pribadi untuk mengaktifkan akun dan mulai
                menggunakan SIMANTAP.
            </p>
        </div>

        <div class="mb-6 flex items-center gap-4 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-700 text-sm font-black text-white shadow-lg shadow-sky-500/20">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>

            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[.16em] text-slate-400">
                    Akun yang akan diaktifkan
                </p>

                <p class="mt-1 truncate text-sm font-extrabold text-slate-900">
                    {{ $user->email }}
                </p>
            </div>

            <span class="ml-auto hidden rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700 ring-1 ring-inset ring-emerald-100 sm:inline-flex">
                TERVERIFIKASI
            </span>
        </div>

        @error('token')
            <div class="alert-danger mb-5" role="alert">
                <svg
                    viewBox="0 0 24 24"
                    class="mt-0.5 h-5 w-5 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/>
                </svg>

                <span>{{ $message }}</span>
            </div>
        @enderror

        <form
            method="POST"
            action="{{ route('activation.store') }}"
            class="space-y-5"
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $token }}"
            >

            <x-auth.password-field
                name="password"
                label="Kata sandi"
                autocomplete="new-password"
                placeholder="Buat kata sandi pribadi"
            />

            <x-auth.password-field
                name="password_confirmation"
                label="Konfirmasi kata sandi"
                autocomplete="new-password"
                placeholder="Ulangi kata sandi pribadi"
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
                    Minimal 12 karakter dengan huruf besar, huruf kecil, angka,
                    dan simbol. Tautan ini hanya dapat digunakan sekali.
                </p>
            </div>

            <button
                type="submit"
                class="primary-button"
            >
                <span>Aktifkan Akun</span>

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
    @else
        <div class="py-3 text-center">
            <span class="mx-auto grid h-18 w-18 place-items-center rounded-3xl bg-red-50 text-red-600 ring-8 ring-red-50/60">
                <svg
                    viewBox="0 0 24 24"
                    class="h-8 w-8"
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

            <span class="eyebrow mt-8">
                Aktivasi gagal
            </span>

            <h2 class="mt-4 text-3xl font-black tracking-[-.035em] text-slate-950">
                Tautan tidak dapat digunakan
            </h2>

            <p class="mx-auto mt-3 max-w-sm text-sm font-medium leading-6 text-slate-500">
                Tautan aktivasi tidak valid, sudah kedaluwarsa, atau telah
                digunakan. Hubungi administrator untuk meminta tautan baru.
            </p>

            <a
                href="{{ route('login') }}"
                class="primary-button mt-7"
            >
                <span>Kembali ke Login</span>

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
            </a>
        </div>
    @endif
</x-layouts.guest>