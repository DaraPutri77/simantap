<x-layouts.guest title="Aktivasi Akun">
    @if ($user !== null)
        <div class="mb-8">
            <p class="text-sm font-bold uppercase tracking-[.16em] text-emerald-600">
                Aktivasi akun
            </p>

            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">
                Buat kata sandi pribadi
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Halo, {{ $user->name }}. Tautan ini hanya dapat digunakan
                sekali. Kata sandi Anda tidak akan diketahui administrator.
            </p>
        </div>

        <div class="mb-6 rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Akun yang akan diaktifkan
            </p>

            <p class="mt-1 font-extrabold text-slate-900">
                {{ $user->email }}
            </p>
        </div>

        @error('token')
            <div
                class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                role="alert"
            >
                {{ $message }}
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

            <div>
                <label for="password" class="form-label">
                    Kata sandi
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

            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-xs leading-5 text-sky-800">
                Minimal 12 karakter serta memiliki huruf besar, huruf kecil,
                angka, dan simbol.
            </div>

            <button type="submit" class="primary-button">
                Aktifkan Akun
                <span aria-hidden="true">→</span>
            </button>
        </form>
    @else
        <div class="text-center">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-red-50 text-2xl text-red-600">
                !
            </span>

            <h2 class="mt-5 text-3xl font-black tracking-tight text-slate-950">
                Tautan tidak dapat digunakan
            </h2>

            <p class="mt-3 text-sm leading-6 text-slate-500">
                Tautan aktivasi tidak valid, sudah kedaluwarsa, atau telah
                digunakan. Hubungi administrator untuk mengirim tautan baru.
            </p>

            <a
                href="{{ route('login') }}"
                class="primary-button mt-7"
            >
                Kembali ke Login
            </a>
        </div>
    @endif
</x-layouts.guest>