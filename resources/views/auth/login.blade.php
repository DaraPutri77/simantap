<x-layouts.guest title="Masuk">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[.16em] text-sky-600">
            Selamat datang
        </p>

        <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">
            Masuk ke SIMANTAP
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Gunakan email atau NIP akun internal yang telah diberikan
            administrator.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('login.store') }}"
        class="space-y-5"
    >
        @csrf

        <div>
            <label for="login" class="form-label">
                Email atau NIP
            </label>

            <input
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                autocomplete="username"
                required
                autofocus
                class="form-input @error('login') form-input-error @enderror"
                placeholder="nama@bps.go.id atau NIP"
            >

            @error('login')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <label for="password" class="form-label">
                    Kata sandi
                </label>

                <a
                    href="{{ route('password.request') }}"
                    class="mb-2 text-xs font-bold text-sky-700 hover:text-sky-900"
                >
                    Lupa kata sandi?
                </a>
            </div>

            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="form-input pr-12 @error('password') form-input-error @enderror"
                    placeholder="Masukkan kata sandi"
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

        <button type="submit" class="primary-button">
            Masuk ke Sistem
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <div class="mt-7 rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-500">
        Belum memiliki akun atau akun ditangguhkan? Hubungi administrator
        SIMANTAP pada unit kerja Anda.
    </div>
</x-layouts.guest>