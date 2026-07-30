<x-layouts.guest title="Lupa Kata Sandi">
    <div class="mb-8">
        <a
            href="{{ route('login') }}"
            class="text-sm font-bold text-sky-700"
        >
            ← Kembali ke login
        </a>

        <h2 class="mt-5 text-3xl font-black tracking-tight text-slate-950">
            Lupa kata sandi?
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Masukkan email akun aktif. Demi keamanan, sistem tidak akan
            menampilkan apakah email terdaftar atau tidak.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.email') }}"
        class="space-y-5"
    >
        @csrf

        <div>
            <label for="email" class="form-label">
                Alamat email
            </label>

            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
                autofocus
                class="form-input @error('email') form-input-error @enderror"
                placeholder="nama@bps.go.id"
            >

            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="primary-button">
            Kirim Tautan Reset
            <span aria-hidden="true">→</span>
        </button>
    </form>
</x-layouts.guest>