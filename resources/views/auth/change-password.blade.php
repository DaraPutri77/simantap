<x-layouts.guest title="Ubah Kata Sandi">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[.16em] text-orange-600">
            Keamanan akun
        </p>

        <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">
            {{ auth()->user()->must_change_password
                ? 'Ubah kata sandi sementara'
                : 'Ubah kata sandi' }}
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Gunakan minimal 12 karakter dengan huruf besar, huruf kecil,
            angka, dan simbol.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.update') }}"
        class="space-y-5"
    >
        @csrf
        @method('PUT')

        <div>
            <label
                for="current_password"
                class="form-label"
            >
                Kata sandi saat ini
            </label>

            <div class="relative">
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="form-input pr-12 @error('current_password') form-input-error @enderror"
                >

                <button
                    type="button"
                    class="password-toggle absolute inset-y-0 right-0 grid w-12 place-items-center text-xs font-bold text-slate-500"
                    data-target="current_password"
                    aria-label="Tampilkan kata sandi saat ini"
                >
                    Lihat
                </button>
            </div>

            @error('current_password')
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
                    aria-label="Tampilkan kata sandi baru"
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
                Konfirmasi kata sandi baru
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
            Simpan dan Lanjutkan
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <form
        method="POST"
        action="{{ route('logout') }}"
        class="mt-4"
    >
        @csrf

        <button
            type="submit"
            class="w-full rounded-xl px-4 py-3 text-sm font-bold text-slate-500 hover:bg-slate-50"
        >
            Keluar dari akun
        </button>
    </form>
</x-layouts.guest>